<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

final class Classroom
{
    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT class_id, class_num, title, sessions, s_date, e_date,
                    user_id AS teacher_id, l_id AS location_id, created_at
             FROM classes
             WHERE class_id = :class_id'
        );
        $statement->execute(['class_id' => $id]);
        $class = $statement->fetch();

        return $class === false ? null : $class;
    }

    public static function numberExists(string $classNum, int $exceptId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM classes WHERE class_num = :class_num AND class_id <> :except_id'
        );
        $statement->execute(['class_num' => $classNum, 'except_id' => $exceptId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function insert(array $data): int
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            'INSERT INTO classes (title, class_num, sessions, s_date, e_date, user_id, l_id)
             VALUES (:title, :class_num, :sessions, :s_date, :e_date, :user_id, :l_id)'
        );
        $statement->execute([
            'title' => $data['title'],
            'class_num' => $data['class_num'],
            'sessions' => $data['sessions'],
            's_date' => $data['s_date'],
            'e_date' => $data['e_date'],
            'user_id' => $data['teacher_id'],
            'l_id' => $data['location_id'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function replaceStudents(int $classId, array $studentIds): void
    {
        $pdo = Database::connection();

        $delete = $pdo->prepare('DELETE FROM class_student WHERE class_id = :class_id');
        $delete->execute(['class_id' => $classId]);

        if ($studentIds === []) {
            return;
        }

        $rows = implode(',', array_fill(0, count($studentIds), '(?, ?)'));
        $params = [];

        foreach ($studentIds as $studentId) {
            $params[] = $classId;
            $params[] = $studentId;
        }

        $insert = $pdo->prepare('INSERT INTO class_student (class_id, user_id) VALUES ' . $rows);
        $insert->execute($params);
    }

    public static function replaceTimes(int $classId, array $times): void
    {
        $pdo = Database::connection();

        $delete = $pdo->prepare('DELETE FROM class_time WHERE class_id = :class_id');
        $delete->execute(['class_id' => $classId]);

        if ($times === []) {
            return;
        }

        $rows = implode(',', array_fill(0, count($times), '(?, ?, ?, ?)'));
        $params = [];

        foreach ($times as $time) {
            $params[] = $classId;
            $params[] = $time['day'];
            $params[] = $time['s_clock'];
            $params[] = $time['e_clock'];
        }

        $insert = $pdo->prepare('INSERT INTO class_time (class_id, day, s_clock, e_clock) VALUES ' . $rows);
        $insert->execute($params);
    }

    public static function details(int $id): ?array
    {
        $class = self::find($id);

        if ($class === null) {
            return null;
        }

        $pdo = Database::connection();

        $teacher = $pdo->prepare('SELECT user_id, fullname, skill FROM users WHERE user_id = :user_id');
        $teacher->execute(['user_id' => $class['teacher_id']]);

        $location = $pdo->prepare('SELECT l_id AS location_id, location FROM class_location WHERE l_id = :l_id');
        $location->execute(['l_id' => $class['location_id']]);

        $students = $pdo->prepare(
            'SELECT u.user_id, u.fullname, u.national_code, u.phone
             FROM class_student cs
             JOIN users u ON u.user_id = cs.user_id
             WHERE cs.class_id = :class_id
             ORDER BY u.fullname, u.user_id'
        );
        $students->execute(['class_id' => $id]);

        $times = $pdo->prepare(
            "SELECT day, TIME_FORMAT(s_clock, '%H:%i') AS s_clock, TIME_FORMAT(e_clock, '%H:%i') AS e_clock
             FROM class_time
             WHERE class_id = :class_id
             ORDER BY day, s_clock"
        );
        $times->execute(['class_id' => $id]);

        return $class + [
            'teacher' => $teacher->fetch() ?: null,
            'location' => $location->fetch() ?: null,
            'students' => $students->fetchAll(),
            'times' => $times->fetchAll(),
        ];
    }

        public static function lock(int $id): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT class_id FROM classes WHERE class_id = :class_id FOR UPDATE'
        );
        $statement->execute(['class_id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public static function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE classes
             SET title = :title, class_num = :class_num, sessions = :sessions,
                 s_date = :s_date, e_date = :e_date, user_id = :user_id, l_id = :l_id
             WHERE class_id = :class_id'
        );
        $statement->execute([
            'title' => $data['title'],
            'class_num' => $data['class_num'],
            'sessions' => $data['sessions'],
            's_date' => $data['s_date'],
            'e_date' => $data['e_date'],
            'user_id' => $data['teacher_id'],
            'l_id' => $data['location_id'],
            'class_id' => $id,
        ]);
    }

        public static function paginate(string $search, int $limit, int $offset): array
    {
        $pdo = Database::connection();

        $from = 'FROM classes c
                 JOIN users u ON u.user_id = c.user_id
                 JOIN class_location l ON l.l_id = c.l_id';
        $where = '';
        $params = [];

        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';

            $where = ' WHERE (c.title LIKE :search_title OR c.class_num LIKE :search_num'
                . ' OR u.fullname LIKE :search_teacher OR l.location LIKE :search_location)';
            $params = [
                ':search_title' => $like,
                ':search_num' => $like,
                ':search_teacher' => $like,
                ':search_location' => $like,
            ];
        }

        $count = $pdo->prepare('SELECT COUNT(*) ' . $from . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $statement = $pdo->prepare(
            'SELECT c.class_id, c.class_num, c.title, c.sessions, c.s_date, c.e_date,
                    c.user_id AS teacher_id, u.fullname AS teacher_name,
                    c.l_id AS location_id, l.location AS location,
                    (SELECT COUNT(*) FROM class_student cs WHERE cs.class_id = c.class_id) AS student_count '
            . $from . $where . ' ORDER BY c.class_id DESC LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

        public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM classes WHERE class_id = :class_id');
        $statement->execute(['class_id' => $id]);
    }

        public static function studentsOfTeacher(int $teacherId, string $search, int $limit, int $offset): array
    {
        $pdo = Database::connection();

        $where = "u.role = 'student'
                  AND EXISTS (
                      SELECT 1
                      FROM class_student cs
                      JOIN classes c ON c.class_id = cs.class_id
                      WHERE cs.user_id = u.user_id AND c.user_id = :teacher_id
                  )";
        $params = [':teacher_id' => $teacherId];

        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';

            $where .= ' AND (u.fullname LIKE :search_name OR u.phone LIKE :search_phone)';
            $params[':search_name'] = $like;
            $params[':search_phone'] = $like;
        }

        $count = $pdo->prepare('SELECT COUNT(*) FROM users u WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $statement = $pdo->prepare(
            'SELECT u.user_id, u.fullname, u.phone FROM users u WHERE ' . $where
            . ' ORDER BY u.fullname, u.user_id LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $students = $statement->fetchAll();

        if ($students === []) {
            return ['items' => [], 'total' => $total];
        }

        $ids = array_map(static fn (array $student): int => (int) $student['user_id'], $students);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $classes = $pdo->prepare(
            'SELECT cs.user_id, c.class_id, c.title, c.class_num
             FROM class_student cs
             JOIN classes c ON c.class_id = cs.class_id
             WHERE c.user_id = ? AND cs.user_id IN (' . $placeholders . ')
             ORDER BY c.class_id'
        );
        $classes->execute([$teacherId, ...$ids]);

        $byStudent = [];

        foreach ($classes->fetchAll() as $row) {
            $byStudent[(int) $row['user_id']][] = [
                'class_id' => (int) $row['class_id'],
                'title' => $row['title'],
                'class_num' => $row['class_num'],
            ];
        }

        $items = array_map(
            static fn (array $student): array => $student + ['classes' => $byStudent[(int) $student['user_id']] ?? []],
            $students
        );

        return ['items' => $items, 'total' => $total];
    }
}
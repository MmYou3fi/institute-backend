<?php

declare(strict_types=1);

namespace Models;

use Core\Database;

final class Timetable
{
    private const BASE_QUERY = "SELECT c.class_id, c.title, c.class_num, c.s_date, c.e_date,
            c.user_id AS teacher_id, u.fullname AS teacher_name, l.location AS location,
            (SELECT COUNT(*) FROM class_student cc WHERE cc.class_id = c.class_id) AS student_count,
            t.day,
            TIME_FORMAT(t.s_clock, '%H:%i') AS s_clock,
            TIME_FORMAT(t.e_clock, '%H:%i') AS e_clock
        FROM classes c
        JOIN users u ON u.user_id = c.user_id
        JOIN class_location l ON l.l_id = c.l_id
        JOIN class_time t ON t.class_id = c.class_id";

    public static function forStudent(int $studentId, ?string $date): array
    {
        return self::fetch(
            ' JOIN class_student cs ON cs.class_id = c.class_id',
            ['cs.user_id = :user_id'],
            [':user_id' => $studentId],
            $date
        );
    }

    public static function forTeacher(int $teacherId, ?string $date): array
    {
        return self::fetch('', ['c.user_id = :user_id'], [':user_id' => $teacherId], $date);
    }

    public static function forAll(?string $date): array
    {
        return self::fetch('', [], [], $date);
    }

    private static function fetch(string $join, array $conditions, array $params, ?string $date): array
    {
        if ($date !== null) {
            $conditions[] = 'c.s_date <= :date_start';
            $conditions[] = 'c.e_date >= :date_end';
            $params[':date_start'] = $date;
            $params[':date_end'] = $date;
        }

        $sql = self::BASE_QUERY . $join
            . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . ' ORDER BY t.day, t.s_clock, c.class_id';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
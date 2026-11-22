<?php

declare(strict_types=1);

namespace Helpers;

use Core\Database;
use Core\HttpException;

final class ClassConflicts
{
    private const DAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

        public static function assertNone(array $input, int $exceptClassId): void
    {
        $errors = self::teacher($input, $exceptClassId)
            + self::location($input, $exceptClassId)
            + self::students($input, $exceptClassId);

        if ($errors !== []) {
            throw new HttpException('تداخل زمانی وجود دارد', 409, $errors);
        }
    }

    private static function teacher(array $input, int $exceptClassId): array
    {
        $errors = [];

        $statement = Database::connection()->prepare(
            "SELECT c.title, c.class_num,
                    TIME_FORMAT(t.s_clock, '%H:%i') AS s_clock,
                    TIME_FORMAT(t.e_clock, '%H:%i') AS e_clock
             FROM classes c
             JOIN class_time t ON t.class_id = c.class_id
             WHERE c.user_id = ?
               AND c.class_id <> ?
               AND c.s_date <= ? AND c.e_date >= ?
               AND t.day = ? AND t.s_clock < ? AND t.e_clock > ?
             LIMIT 1"
        );

        foreach ($input['times'] as $index => $time) {
            $statement->execute([
                $input['teacher_id'],
                $exceptClassId,
                $input['e_date'],
                $input['s_date'],
                $time['day'],
                $time['e_clock'],
                $time['s_clock'],
            ]);
            $conflict = $statement->fetch();
            $statement->closeCursor();

            if ($conflict !== false) {
                $errors['times.' . $index] = 'مدرس در روز ' . self::dayName($time['day'])
                    . ' (' . $conflict['s_clock'] . ' تا ' . $conflict['e_clock'] . ')'
                    . ' کلاس «' . $conflict['title'] . '» (' . $conflict['class_num'] . ') دارد';
            }
        }

        return $errors;
    }

    private static function location(array $input, int $exceptClassId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT c.title, c.class_num,
                    TIME_FORMAT(t.s_clock, '%H:%i') AS s_clock,
                    TIME_FORMAT(t.e_clock, '%H:%i') AS e_clock
             FROM classes c
             JOIN class_time t ON t.class_id = c.class_id
             WHERE c.l_id = ?
               AND c.class_id <> ?
               AND c.s_date <= ? AND c.e_date >= ?
               AND t.day = ? AND t.s_clock < ? AND t.e_clock > ?
             LIMIT 1"
        );

        foreach ($input['times'] as $time) {
            $statement->execute([
                $input['location_id'],
                $exceptClassId,
                $input['e_date'],
                $input['s_date'],
                $time['day'],
                $time['e_clock'],
                $time['s_clock'],
            ]);
            $conflict = $statement->fetch();
            $statement->closeCursor();

            if ($conflict !== false) {
                return [
                    'location_id' => 'مکان در روز ' . self::dayName($time['day'])
                        . ' (' . $conflict['s_clock'] . ' تا ' . $conflict['e_clock'] . ')'
                        . ' برای کلاس «' . $conflict['title'] . '» (' . $conflict['class_num'] . ') اشغال است',
                ];
            }
        }

        return [];
    }

        private static function students(array $input, int $exceptClassId): array
    {
        if ($input['student_ids'] === []) {
            return [];
        }

        $positions = array_flip($input['student_ids']);
        $placeholders = implode(',', array_fill(0, count($input['student_ids']), '?'));
        $errors = [];

        $statement = Database::connection()->prepare(
            "SELECT cs.user_id, u.fullname, c.title, c.class_num,
                    TIME_FORMAT(t.s_clock, '%H:%i') AS s_clock,
                    TIME_FORMAT(t.e_clock, '%H:%i') AS e_clock
             FROM class_student cs
             JOIN users u ON u.user_id = cs.user_id
             JOIN classes c ON c.class_id = cs.class_id
             JOIN class_time t ON t.class_id = c.class_id
             WHERE cs.user_id IN (" . $placeholders . ")
               AND c.class_id <> ?
               AND c.s_date <= ? AND c.e_date >= ?
               AND t.day = ? AND t.s_clock < ? AND t.e_clock > ?"
        );

        foreach ($input['times'] as $time) {
            $statement->execute([
                ...$input['student_ids'],
                $exceptClassId,
                $input['e_date'],
                $input['s_date'],
                $time['day'],
                $time['e_clock'],
                $time['s_clock'],
            ]);

            foreach ($statement->fetchAll() as $conflict) {
                $key = 'student_ids.' . $positions[(int) $conflict['user_id']];

                if (!isset($errors[$key])) {
                    $errors[$key] = 'زبان‌آموز «' . $conflict['fullname'] . '» در روز ' . self::dayName($time['day'])
                        . ' (' . $conflict['s_clock'] . ' تا ' . $conflict['e_clock'] . ')'
                        . ' در کلاس «' . $conflict['title'] . '» (' . $conflict['class_num'] . ') حضور دارد';
                }
            }
        }

        return $errors;
    }

    private static function dayName(int $day): string
    {
        return self::DAY_NAMES[$day] ?? (string) $day;
    }
}
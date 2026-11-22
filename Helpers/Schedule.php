<?php

declare(strict_types=1);

namespace Helpers;
use Core\Request;

final class Schedule
{
    public static function groupByClass(array $rows): array
    {
        $classes = [];

        foreach ($rows as $row) {
            $classId = (int) $row['class_id'];

            if (!isset($classes[$classId])) {
                $class = [
                    'class_id' => $classId,
                    'title' => $row['title'],
                    'class_num' => $row['class_num'],
                    'sessions' => (int) $row['sessions'],
                    's_date' => $row['s_date'],
                    'e_date' => $row['e_date'],
                ];

                if (array_key_exists('teacher_name', $row)) {
                    $class['teacher_name'] = $row['teacher_name'];
                }

                $class['times'] = [];
                $classes[$classId] = $class;
            }

            if ($row['day'] !== null) {
                $classes[$classId]['times'][] = [
                    'day' => (int) $row['day'],
                    's_clock' => $row['s_clock'],
                    'e_clock' => $row['e_clock'],
                ];
            }
        }

        return array_values($classes);
    }

        public static function groupByDay(array $rows): array
    {
        $days = [];

        for ($day = 0; $day <= 6; $day++) {
            $days[$day] = ['day' => $day, 'items' => []];
        }

        foreach ($rows as $row) {
            $days[(int) $row['day']]['items'][] = [
                'class_id' => (int) $row['class_id'],
                'title' => $row['title'],
                'class_num' => $row['class_num'],
                'teacher_id' => (int) $row['teacher_id'],
                'teacher_name' => $row['teacher_name'],
                'location' => $row['location'],
                's_clock' => $row['s_clock'],
                'e_clock' => $row['e_clock'],
                's_date' => $row['s_date'],
                'e_date' => $row['e_date'],
                'student_count' => (int) $row['student_count'],
            ];
        }

        return array_values($days);
    }

    public static function dateFilter(Request $request): ?string
    {
        if ($request->query('all') === '1') {
            return null;
        }

        $date = $request->query('date');

        if ($date === null) {
            return date('Y-m-d');
        }

        $validated = Validator::validate(['date' => $date], ['date' => ['required', 'date']]);

        return (string) $validated['date'];
    }
}
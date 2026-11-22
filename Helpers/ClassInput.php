<?php

declare(strict_types=1);

namespace Helpers;

use Core\HttpException;
use Models\ClassLocation;
use Models\User;

final class ClassInput
{
    private const MAX_STUDENTS = 200;

    private const MAX_TIMES = 14;

    public static function parse(array $body): array
    {
        $errors = [];
        $data = [];

        try {
            $data = Validator::validate($body, [
                'class_num' => ['required', 'max:20'],
                'title' => ['required', 'max:150'],
                'teacher_id' => ['required', 'int_between:1,4294967295'],
                'location_id' => ['required', 'int_between:1,4294967295'],
                'sessions' => ['required', 'int_between:1,500'],
                's_date' => ['required', 'date'],
                'e_date' => ['required', 'date'],
            ]);
        } catch (HttpException $exception) {
            $errors = $exception->getErrors();
        }

        if (!isset($errors['s_date']) && !isset($errors['e_date'])) {
            $start = trim((string) ($body['s_date'] ?? ''));
            $end = trim((string) ($body['e_date'] ?? ''));

            if ($start > $end) {
                $errors['e_date'] = 'تاریخ پایان نباید قبل از تاریخ شروع باشد';
            }
        }

        $studentIds = self::studentIds($body['student_ids'] ?? [], $errors);
        $times = self::times($body['times'] ?? null, $errors);

        if ($errors !== []) {
            throw new HttpException('اطلاعات ارسالی نامعتبر است', 422, $errors);
        }

        $teacherId = (int) $data['teacher_id'];
        $locationId = (int) $data['location_id'];

        if (User::find($teacherId, 'teacher') === null) {
            $errors['teacher_id'] = 'مدرس انتخاب‌شده وجود ندارد';
        }

        if (ClassLocation::find($locationId) === null) {
            $errors['location_id'] = 'مکان انتخاب‌شده وجود ندارد';
        }

        $found = User::existingIds($studentIds, 'student');

        foreach ($studentIds as $index => $studentId) {
            if (!in_array($studentId, $found, true)) {
                $errors['student_ids.' . $index] = 'زبان‌آموزی با این شناسه وجود ندارد';
            }
        }

        if ($errors !== []) {
            throw new HttpException('اطلاعات ارسالی نامعتبر است', 422, $errors);
        }

        return [
            'class_num' => $data['class_num'],
            'title' => $data['title'],
            'teacher_id' => $teacherId,
            'location_id' => $locationId,
            'sessions' => (int) $data['sessions'],
            's_date' => $data['s_date'],
            'e_date' => $data['e_date'],
            'student_ids' => $studentIds,
            'times' => $times,
        ];
    }

    private static function studentIds(mixed $value, array &$errors): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            $errors['student_ids'] = 'student_ids باید یک لیست باشد';

            return [];
        }

        if (count($value) > self::MAX_STUDENTS) {
            $errors['student_ids'] = 'حداکثر ' . self::MAX_STUDENTS . ' زبان‌آموز برای یک کلاس مجاز است';

            return [];
        }

        $ids = [];
        $seen = [];

        foreach ($value as $index => $item) {
            try {
                $validated = Validator::validate(['id' => $item], [
                    'id' => ['required', 'int_between:1,4294967295'],
                ]);
            } catch (HttpException $exception) {
                $errors['student_ids.' . $index] = $exception->getErrors()['id'] ?? 'شناسه نامعتبر است';

                continue;
            }

            $id = (int) $validated['id'];

            if (isset($seen[$id])) {
                $errors['student_ids.' . $index] = 'این زبان‌آموز در لیست تکراری است';

                continue;
            }

            $seen[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    private static function times(mixed $value, array &$errors): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            $errors['times'] = 'حداقل یک زمان برگزاری لازم است (لیست times)';

            return [];
        }

        if (count($value) > self::MAX_TIMES) {
            $errors['times'] = 'حداکثر ' . self::MAX_TIMES . ' زمان برگزاری مجاز است';

            return [];
        }

        $times = [];

        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                $errors['times.' . $index] = 'هر زمان باید شامل day، s_clock و e_clock باشد';

                continue;
            }

            try {
                $validated = Validator::validate($item, [
                    'day' => ['required', 'int_between:0,6'],
                    's_clock' => ['required', 'time'],
                    'e_clock' => ['required', 'time'],
                ]);
            } catch (HttpException $exception) {
                foreach ($exception->getErrors() as $field => $message) {
                    $errors['times.' . $index . '.' . $field] = $message;
                }

                continue;
            }

            if ($validated['s_clock'] >= $validated['e_clock']) {
                $errors['times.' . $index . '.e_clock'] = 'ساعت پایان باید بعد از ساعت شروع باشد';

                continue;
            }

            $times[] = [
                'day' => (int) $validated['day'],
                's_clock' => $validated['s_clock'],
                'e_clock' => $validated['e_clock'],
            ];
        }

        if (count($times) === count($value)) {
            foreach ($times as $i => $first) {
                for ($j = $i + 1; $j < count($times); $j++) {
                    $second = $times[$j];

                    if (
                        $first['day'] === $second['day']
                        && $first['s_clock'] < $second['e_clock']
                        && $second['s_clock'] < $first['e_clock']
                    ) {
                        $errors['times.' . $j] = 'این زمان با زمان شماره‌ی ' . ($i + 1) . ' همین کلاس تداخل دارد';
                    }
                }
            }
        }

        return $times;
    }
}
<?php

declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\Response;
use Helpers\Schedule;
use Models\Timetable;

final class ScheduleController
{
    public static function student(Request $request): void
    {
        $date = Schedule::dateFilter($request);
        $rows = Timetable::forStudent((int) $request->user['user_id'], $date);

        Response::success([
            'date' => $date,
            'days' => Schedule::groupByDay($rows),
        ], 'برنامه‌ی هفتگی من');
    }

        public static function teacher(Request $request): void
    {
        $date = Schedule::dateFilter($request);
        $rows = Timetable::forTeacher((int) $request->user['user_id'], $date);

        Response::success([
            'date' => $date,
            'days' => Schedule::groupByDay($rows),
        ], 'برنامه‌ی هفتگی من');
    }

        public static function admin(Request $request): void
    {
        $date = Schedule::dateFilter($request);

        Response::success([
            'date' => $date,
            'days' => Schedule::groupByDay(Timetable::forAll($date)),
        ], 'برنامه‌ی هفتگی آموزشگاه');
    }
}
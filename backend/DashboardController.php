<?php

declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\Response;
use Helpers\Schedule;
use Models\Dashboard;
use Models\Timetable;

final class DashboardController
{
    public static function show(Request $request): void
    {
        $date = Schedule::dateFilter($request);

        Response::success([
            'date' => $date,
            'counts' => Dashboard::counts($date),
            'schedule' => Schedule::groupByDay(Timetable::forAll($date)),
        ], 'داشبورد');
    }
}
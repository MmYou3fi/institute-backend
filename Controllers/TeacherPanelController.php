<?php

declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\Response;
use Helpers\Pagination;
use Models\Classroom;

final class TeacherPanelController
{
    public static function students(Request $request): void
    {
        $page = Pagination::fromRequest($request);

        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 100, 'UTF-8') : '';

        $result = Classroom::studentsOfTeacher(
            (int) $request->user['user_id'],
            $search,
            $page['per_page'],
            $page['offset']
        );

        Response::success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page['page'],
            'per_page' => $page['per_page'],
        ], 'زبان‌آموزان من');
    }
}
<?php

declare(strict_types=1);

namespace Controllers;

use Core\HttpException;
use Core\Request;
use Core\Response;
use Helpers\Account;
use Helpers\Pagination;
use Helpers\Schedule;
use Helpers\Validator;
use Models\User;
use Models\Timetable;
use Helpers\Rules;

final class StudentController
{
    private const ROLE = 'student';

    private const SEARCH_COLUMNS = ['fullname', 'national_code', 'phone'];

    public static function index(Request $request): void
    {
        $page = Pagination::fromRequest($request);

        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 100, 'UTF-8') : '';

        $result = User::paginate(self::ROLE, $search, self::SEARCH_COLUMNS, $page['per_page'], $page['offset']);

        Response::success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page['page'],
            'per_page' => $page['per_page'],
        ], 'لیست زبان‌آموزها');
    }

        public static function store(Request $request): void
    {
        $data = Validator::validate($request->body(), Rules::STUDENT_STORE);

        $nationalCode = (string) $data['national_code'];

        Account::assertAvailable($nationalCode);

        $id = Account::register(self::ROLE, [
            'national_code' => $nationalCode,
            'fullname' => $data['fullname'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
        ]);

        Response::success(User::find($id, self::ROLE), 'زبان‌آموز با موفقیت ثبت شد', 201);
    }

        public static function update(Request $request, string $id): void
    {
        $studentId = Validator::id($id);
        self::findOrFail($studentId);

        $data = Validator::validate($request->body(), [
            'fullname' => ['required', 'max:100'],
            'phone' => ['required', 'mobile'],
            'address' => ['max:255'],
        ]);

        User::update($studentId, self::ROLE, [
            'fullname' => $data['fullname'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
        ]);

        Response::success(self::findOrFail($studentId), 'اطلاعات زبان‌آموز ویرایش شد');
    }

    public static function destroy(Request $request, string $id): void
    {
        $studentId = Validator::id($id);
        self::findOrFail($studentId);

        User::delete($studentId, self::ROLE);

        Response::success(null, 'زبان‌آموز حذف شد');
    }

    private static function findOrFail(int $id): array
    {
        return User::find($id, self::ROLE) ?? throw new HttpException('زبان‌آموز یافت نشد', 404);
    }

        public static function resetPassword(Request $request, string $id): void
    {
        $studentId = Validator::id($id);
        $student = self::findOrFail($studentId);

        Account::resetPassword($student, self::ROLE);

        Response::success(null, 'رمز زبان‌آموز به کدملی او بازنشانی شد');
    }

        public static function schedule(Request $request, string $id): void
    {
        $studentId = Validator::id($id);
        $student = self::findOrFail($studentId);
        $date = Schedule::dateFilter($request);

        Response::success([
            'student' => $student,
            'date' => $date,
            'days' => Schedule::groupByDay(Timetable::forStudent($studentId, $date)),
        ], 'برنامه‌ی زبان‌آموز');
    }
}
<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\HttpException;
use Core\Request;
use Core\Response;
use Helpers\ClassConflicts;
use Helpers\ClassInput;
use Helpers\Pagination;
use Helpers\Validator;
use Models\Classroom;
use Models\ClassLocation;
use Models\User;
use PDOException;
use Throwable;

final class ClassController
{
    public static function store(Request $request): void
    {
        $input = ClassInput::parse($request->body());

        if (Classroom::numberExists($input['class_num'], 0)) {
            throw self::duplicateNumber();
        }

        $classId = 0;

        self::transaction(function () use ($input, &$classId): void {
            ClassLocation::lock($input['location_id']);
            User::lockByIds([$input['teacher_id'], ...$input['student_ids']]);

            ClassConflicts::assertNone($input, 0);
            
            $classId = Classroom::insert($input);
            Classroom::replaceStudents($classId, $input['student_ids']);
            Classroom::replaceTimes($classId, $input['times']);
        });

        Response::success(Classroom::details($classId), 'کلاس با موفقیت ثبت شد', 201);
    }

    private static function transaction(callable $work): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $work();
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw self::duplicateNumber();
            }

            throw $exception;
        }
    }

    private static function duplicateNumber(): HttpException
    {
        return new HttpException('شماره‌ی کلاس تکراری است', 409, [
            'class_num' => 'این شماره‌ی کلاس قبلاً ثبت شده است',
        ]);
    }

        public static function update(Request $request, string $id): void
    {
        $classId = Validator::id($id);
        self::findOrFail($classId);

        $input = ClassInput::parse($request->body());

        if (Classroom::numberExists($input['class_num'], $classId)) {
            throw self::duplicateNumber();
        }

        self::transaction(function () use ($input, $classId): void {
            if (!Classroom::lock($classId)) {
                throw new HttpException('کلاس یافت نشد', 404);
            }

            ClassLocation::lock($input['location_id']);
            User::lockByIds([$input['teacher_id'], ...$input['student_ids']]);

            ClassConflicts::assertNone($input, $classId);

            Classroom::update($classId, $input);
            Classroom::replaceStudents($classId, $input['student_ids']);
            Classroom::replaceTimes($classId, $input['times']);
        });

        Response::success(Classroom::details($classId), 'کلاس با موفقیت ویرایش شد');
    }

    private static function findOrFail(int $id): array
    {
        return Classroom::find($id) ?? throw new HttpException('کلاس یافت نشد', 404);
    }

        public static function index(Request $request): void
    {
        $page = Pagination::fromRequest($request);

        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 100, 'UTF-8') : '';

        $result = Classroom::paginate($search, $page['per_page'], $page['offset']);

        Response::success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page['page'],
            'per_page' => $page['per_page'],
        ], 'لیست کلاس‌ها');
    }

    public static function show(Request $request, string $id): void
    {
        $classId = Validator::id($id);
        $details = Classroom::details($classId) ?? throw new HttpException('کلاس یافت نشد', 404);

        Response::success($details, 'جزئیات کلاس');
    }

        public static function destroy(Request $request, string $id): void
    {
        $classId = Validator::id($id);
        self::findOrFail($classId);

        Classroom::delete($classId);

        Response::success(null, 'کلاس حذف شد');
    }
}
<?php

declare(strict_types=1);

namespace Controllers;

use Core\HttpException;
use Core\Request;
use Core\Response;
use Helpers\Validator;
use Models\ClassLocation;
use PDOException;

final class LocationController
{
    private const RULES = [
        'location' => ['required', 'max:100'],
    ];

    public static function index(): void
    {
        $items = ClassLocation::all();

        Response::success(['items' => $items, 'total' => count($items)], 'لیست مکان‌ها');
    }

    public static function store(Request $request): void
    {
        $data = Validator::validate($request->body(), self::RULES);
        $location = (string) $data['location'];

        if (ClassLocation::nameExists($location, 0)) {
            throw self::duplicate();
        }

        try {
            $id = ClassLocation::create($location);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw self::duplicate();
            }

            throw $exception;
        }

        Response::success(ClassLocation::find($id), 'مکان با موفقیت ثبت شد', 201);
    }

    public static function update(Request $request, string $id): void
    {
        $locationId = Validator::id($id);
        self::findOrFail($locationId);

        $data = Validator::validate($request->body(), self::RULES);
        $location = (string) $data['location'];

        if (ClassLocation::nameExists($location, $locationId)) {
            throw self::duplicate();
        }

        try {
            ClassLocation::update($locationId, $location);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw self::duplicate();
            }

            throw $exception;
        }

        Response::success(self::findOrFail($locationId), 'مکان ویرایش شد');
    }

    public static function destroy(Request $request, string $id): void
    {
        $locationId = Validator::id($id);
        self::findOrFail($locationId);

        if (ClassLocation::classCount($locationId) > 0) {
            throw self::inUse();
        }

        try {
            ClassLocation::delete($locationId);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1451) {
                throw self::inUse();
            }

            throw $exception;
        }

        Response::success(null, 'مکان حذف شد');
    }

    private static function findOrFail(int $id): array
    {
        return ClassLocation::find($id) ?? throw new HttpException('مکان یافت نشد', 404);
    }

    private static function duplicate(): HttpException
    {
        return new HttpException('این مکان قبلاً ثبت شده است', 409, [
            'location' => 'این نام قبلاً ثبت شده است',
        ]);
    }

    private static function inUse(): HttpException
    {
        return new HttpException('این مکان برای کلاس‌ها استفاده شده است', 409);
    }
}
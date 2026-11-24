<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\HttpException;
use Core\Request;
use Core\Response;
use Helpers\Upload;
use Helpers\Validator;
use Throwable;

final class ProfessorController
{
    private const RULES = [
        'name' => ['required', 'max:100'],
        'skill' => ['max:100'],
        'about' => ['max:5000'],
    ];

    public static function index(): void
    {
        $items = Database::connection()
            ->query('SELECT professor_id, name, skill, about, img_url FROM professors ORDER BY professor_id')
            ->fetchAll();

        Response::success(['items' => $items, 'total' => count($items)], 'لیست اساتید');
    }

    public static function store(Request $request): void
    {
        $data = Validator::validate($request->body(), self::RULES);

        $file = $request->file('image');
        $path = Upload::hasFile($file) ? Upload::image($file, 'professors') : null;
        $pdo = Database::connection();

        try {
            $statement = $pdo->prepare(
                'INSERT INTO professors (name, skill, about, img_url)
                 VALUES (:name, :skill, :about, :img_url)'
            );
            $statement->execute([
                'name' => $data['name'],
                'skill' => $data['skill'] ?? null,
                'about' => $data['about'] ?? null,
                'img_url' => $path,
            ]);
        } catch (Throwable $exception) {
            Upload::delete($path);

            throw $exception;
        }

        Response::success(self::find((int) $pdo->lastInsertId()), 'استاد با موفقیت اضافه شد', 201);
    }

    public static function update(Request $request, string $id): void
    {
        $professorId = Validator::id($id);
        $professor = self::find($professorId);

        $data = Validator::validate($request->body(), self::RULES);

        $file = $request->file('image');
        $newPath = Upload::hasFile($file) ? Upload::image($file, 'professors') : null;

        try {
            $statement = Database::connection()->prepare(
                'UPDATE professors
                 SET name = :name, skill = :skill, about = :about, img_url = :img_url
                 WHERE professor_id = :professor_id'
            );
            $statement->execute([
                'name' => $data['name'],
                'skill' => $data['skill'] ?? null,
                'about' => $data['about'] ?? null,
                'img_url' => $newPath ?? $professor['img_url'],
                'professor_id' => $professorId,
            ]);
        } catch (Throwable $exception) {
            Upload::delete($newPath);

            throw $exception;
        }

        if ($newPath !== null) {
            Upload::delete($professor['img_url']);
        }

        Response::success(self::find($professorId), 'استاد با موفقیت ویرایش شد');
    }

    public static function destroy(Request $request, string $id): void
    {
        $professorId = Validator::id($id);
        $professor = self::find($professorId);

        $statement = Database::connection()->prepare('DELETE FROM professors WHERE professor_id = :professor_id');
        $statement->execute(['professor_id' => $professorId]);

        Upload::delete($professor['img_url']);

        Response::success(null, 'استاد حذف شد');
    }

    private static function find(int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT professor_id, name, skill, about, img_url FROM professors WHERE professor_id = :professor_id'
        );
        $statement->execute(['professor_id' => $id]);
        $professor = $statement->fetch();

        if ($professor === false) {
            throw new HttpException('استاد یافت نشد', 404);
        }

        return $professor;
    }
}
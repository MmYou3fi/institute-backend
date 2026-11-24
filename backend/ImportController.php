<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\HttpException;
use Core\Request;
use Core\Response;
use Helpers\Account;
use Helpers\ExcelReader;
use Helpers\ExcelTemplate;
use Helpers\ImportDuplicates;
use Helpers\ImportRowValidator;
use Helpers\ImportSpec;
use Throwable;

final class ImportController
{
    private const XLSX_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const MODES = ['strict', 'partial'];

    private const HASH_COST = 10;

    public static function template(Request $request, string $type): void
    {
        $spec = ImportSpec::forType($type);

        Response::download(ExcelTemplate::build($spec), $spec['file'], self::XLSX_TYPE);
    }

    public static function import(Request $request, string $type): void
    {
        $spec = ImportSpec::forType($type);
        $mode = self::mode($request);

        set_time_limit(120);

        $rows = ExcelReader::read($request->file('file'), $spec);
        $checked = ImportRowValidator::validate($spec, $rows);

        $valid = $checked['valid'];
        $errors = $checked['errors'];

        foreach (ImportDuplicates::find($valid) as $rowNumber => $rowErrors) {
            unset($valid[$rowNumber]);
            $errors[$rowNumber] = $rowErrors;
        }

        ksort($errors);

        if ($valid === [] || ($errors !== [] && $mode === 'strict')) {
            throw new HttpException(
                $valid === []
                    ? 'هیچ ردیف معتبری در فایل وجود ندارد'
                    : 'فایل دارای ردیف نامعتبر است؛ هیچ ردیفی ثبت نشد',
                422,
                $errors
            );
        }

        $created = self::insertAll($spec['role'], $valid);

        Response::success([
            'total_rows' => count($rows),
            'created' => $created,
            'failed' => count($errors),
            'errors' => (object) $errors,
        ], $created . ' ' . $spec['label'] . ' ثبت شد', 201);
    }

    private static function mode(Request $request): string
    {
        $mode = $request->body()['mode'] ?? 'strict';

        if (!is_string($mode) || !in_array($mode, self::MODES, true)) {
            throw new HttpException('اطلاعات ارسالی نامعتبر است', 422, [
                'mode' => 'مقدار mode باید strict یا partial باشد',
            ]);
        }

        return $mode;
    }

    private static function insertAll(string $role, array $rows): int
    {
        $pdo = Database::connection();
        $created = 0;

        $pdo->beginTransaction();

        try {
            foreach ($rows as $data) {
                Account::register($role, $data, self::HASH_COST);
                $created++;
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        return $created;
    }
}
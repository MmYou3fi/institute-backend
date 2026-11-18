<?php

declare(strict_types=1);

namespace Helpers;

use Core\HttpException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Stringable;

final class ExcelReader
{
    public const MAX_ROWS = 500;

    private const MAX_SIZE = 2 * 1024 * 1024;

    public static function read(?array $file, array $spec): array
    {
        $path = self::validateUpload($file);

        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        if (!$reader->canRead($path)) {
            throw self::invalid('فایل اکسل (xlsx) معتبر نیست');
        }

        try {
            $info = $reader->listWorksheetInfo($path);

            if ((int) ($info[0]['totalRows'] ?? 0) > self::MAX_ROWS + 1) {
                throw self::invalid('تعداد ردیف‌های فایل بیشتر از ' . self::MAX_ROWS . ' است');
            }

            $reader->setReadFilter(new class (self::MAX_ROWS + 2) implements IReadFilter {
                public function __construct(private readonly int $lastRow)
                {
                }

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->lastRow;
                }
            });

            $spreadsheet = $reader->load($path);
        } catch (SpreadsheetException) {
            throw self::invalid('خواندن فایل اکسل ممکن نشد؛ فایل خراب یا ناسازگار است');
        }

        $data = $spreadsheet->getSheet(0)->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();

        self::assertHeader($data[0] ?? [], $spec['columns']);

        $rows = [];

        foreach ($data as $index => $cells) {
            if ($index === 0) {
                continue;
            }

            $values = [];

            foreach ($spec['columns'] as $position => $column) {
                $values[$column['key']] = self::cellToString($cells[$position] ?? null);
            }

            if (implode('', $values) === '') {
                continue;
            }

            $rows[$index + 1] = $values;
        }

        if ($rows === []) {
            throw self::invalid('فایل هیچ ردیف داده‌ای ندارد');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw self::invalid('تعداد ردیف‌های فایل بیشتر از ' . self::MAX_ROWS . ' است');
        }

        return $rows;
    }

    private static function validateUpload(?array $file): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($file === null || $error === UPLOAD_ERR_NO_FILE) {
            throw self::invalid('فایل اکسل ارسال نشده است (نام فیلد باید file باشد)');
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw self::invalid(self::sizeMessage());
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw self::invalid('آپلود فایل با خطا مواجه شد');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw self::invalid('فایل ارسال‌شده معتبر نیست');
        }

        if ((int) filesize($tmpName) > self::MAX_SIZE) {
            throw self::invalid(self::sizeMessage());
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if ($extension !== 'xlsx') {
            throw self::invalid('فقط فایل اکسل با پسوند xlsx مجاز است');
        }

        $handle = fopen($tmpName, 'rb');
        $signature = $handle === false ? '' : (string) fread($handle, 4);

        if ($handle !== false) {
            fclose($handle);
        }

        if ($signature !== "PK\x03\x04") {
            throw self::invalid('فایل اکسل (xlsx) معتبر نیست');
        }

        return $tmpName;
    }

    private static function assertHeader(array $header, array $columns): void
    {
        foreach ($columns as $position => $column) {
            $actual = self::compact(Normalizer::text(self::cellToString($header[$position] ?? null)));
            $expected = self::compact(Normalizer::text($column['title']));

            if ($actual !== $expected) {
                throw self::invalid('عنوان ستون‌های فایل با قالب نمونه یکسان نیست. فایل نمونه را دانلود کنید و از آن استفاده کنید');
            }
        }
    }

    private static function compact(string $value): string
    {
        return str_replace([' ', "\u{200C}"], '', $value);
    }

    private static function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return floor($value) === $value && abs($value) < 1.0E+15
                ? sprintf('%.0f', $value)
                : (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_string($value) || $value instanceof Stringable) {
            return trim((string) $value);
        }

        return '';
    }

    private static function sizeMessage(): string
    {
        return 'حجم فایل نباید بیشتر از ۲ مگابایت باشد';
    }

    private static function invalid(string $message): HttpException
    {
        return new HttpException('اطلاعات ارسالی نامعتبر است', 422, ['file' => $message]);
    }
}
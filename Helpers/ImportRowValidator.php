<?php

declare(strict_types=1);

namespace Helpers;

use Core\HttpException;

final class ImportRowValidator
{
    public static function validate(array $spec, array $rows): array
    {
        $valid = [];
        $errors = [];

        foreach ($rows as $rowNumber => $cells) {
            $data = [];
            $rowErrors = [];

            foreach ($spec['columns'] as $column) {
                $raw = $cells[$column['key']] ?? '';

                if (str_starts_with($raw, '=')) {
                    $rowErrors[$column['key']] = 'سلول نباید فرمول باشد';

                    continue;
                }

                $data[$column['key']] = self::normalize($column['normalize'], $raw);
            }

            $validated = [];

            try {
                $validated = Validator::validate($data, $spec['rules']);
            } catch (HttpException $exception) {
                $rowErrors += $exception->getErrors();
            }

            if ($rowErrors !== []) {
                $errors[$rowNumber] = $rowErrors;

                continue;
            }

            $valid[$rowNumber] = $validated;
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    private static function normalize(string $type, string $value): string
    {
        return match ($type) {
            'national_code' => Normalizer::nationalCode($value),
            'mobile' => Normalizer::mobile($value),
            default => Normalizer::text($value),
        };
    }
}
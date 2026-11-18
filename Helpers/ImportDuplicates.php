<?php

declare(strict_types=1);

namespace Helpers;

use Core\Database;

final class ImportDuplicates
{
    public static function find(array $rows): array
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $rowNumber => $data) {
            $code = (string) $data['national_code'];

            if (isset($seen[$code])) {
                $errors[$rowNumber]['national_code'] = 'کدملی تکراری است (در ردیف ' . $seen[$code] . ' هم آمده است)';

                continue;
            }

            $seen[$code] = $rowNumber;
        }

        if ($seen === []) {
            return $errors;
        }

        $codes = array_map('strval', array_keys($seen));
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $statement = Database::connection()->prepare(
            'SELECT national_code, username FROM users
             WHERE national_code IN (' . $placeholders . ') OR username IN (' . $placeholders . ')'
        );
        $statement->execute([...$codes, ...$codes]);

        $existing = [];

        foreach ($statement->fetchAll() as $user) {
            foreach (['national_code', 'username'] as $column) {
                if ($user[$column] !== null) {
                    $existing[(string) $user[$column]] = true;
                }
            }
        }

        foreach ($seen as $code => $rowNumber) {
            if (isset($existing[(string) $code])) {
                $errors[$rowNumber]['national_code'] = 'این کدملی قبلاً در سیستم ثبت شده است';
            }
        }

        return $errors;
    }
}
<?php

declare(strict_types=1);

namespace Helpers;

final class Normalizer
{
    public static function digits(string $value): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );
    }

    public static function text(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200F}", "\u{200E}"], ['ی', 'ک', '', ''], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function nationalCode(string $value): string
    {
        $code = preg_replace('/[\s\-]/u', '', self::digits(self::text($value))) ?? '';

        if (preg_match('/^\d{1,9}$/', $code) === 1) {
            return str_pad($code, 10, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    public static function mobile(string $value): string
    {
        $phone = preg_replace('/[\s\-()]/u', '', self::digits($value)) ?? '';

        if (preg_match('/^\+?\d+$/', $phone) !== 1) {
            return $phone;
        }

        if (str_starts_with($phone, '+98')) {
            return '0' . substr($phone, 3);
        }

        if (str_starts_with($phone, '0098')) {
            return '0' . substr($phone, 4);
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '98')) {
            return '0' . substr($phone, 2);
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            return '0' . $phone;
        }

        return $phone;
    }
}
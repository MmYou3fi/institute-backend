<?php

declare(strict_types=1);

namespace Helpers;

use Core\HttpException;

final class ImportSpec
{
    public static function forType(string $type): array
    {
        return match ($type) {
            'students' => [
                'role' => 'student',
                'label' => 'زبان‌آموز',
                'file' => 'students-template.xlsx',
                'sheet' => 'زبان‌آموزان',
                'rules' => Rules::STUDENT_STORE,
                'columns' => [
                    ['key' => 'fullname', 'title' => 'نام و نام خانوادگی', 'normalize' => 'text', 'example' => 'رضا کریمی', 'width' => 28],
                    ['key' => 'national_code', 'title' => 'کدملی', 'normalize' => 'national_code', 'example' => '0010350829', 'width' => 16],
                    ['key' => 'phone', 'title' => 'شماره تماس', 'normalize' => 'mobile', 'example' => '09121234567', 'width' => 16],
                    ['key' => 'address', 'title' => 'آدرس (اختیاری)', 'normalize' => 'text', 'example' => 'تهران', 'width' => 40],
                ],
                'guide' => [
                    'این فایل را بر اساس قالب نمونه پر کنید و ردیف نمونه‌ی ردیف ۲ را قبل از ارسال پاک کنید.',
                    'سطر اول (عنوان ستون‌ها) را تغییر ندهید و ترتیب ستون‌ها را عوض نکنید.',
                    'کدملی ده رقمی است. ستون‌های کدملی و شماره تماس به‌صورت متن (Text) تنظیم شده‌اند تا صفر اول حذف نشود.',
                    'شماره تماس با ۰۹ شروع می‌شود و ۱۱ رقم دارد.',
                    'آدرس اختیاری است و بقیه‌ی ستون‌ها اجباری‌اند.',
                    'کدملی هر ردیف نباید در همین فایل یا در سیستم تکراری باشد.',
                    'حداکثر ۵۰۰ ردیف و حداکثر ۲ مگابایت. فقط برگه‌ی اول خوانده می‌شود.',
                ],
            ],
            'teachers' => [
                'role' => 'teacher',
                'label' => 'مدرس',
                'file' => 'teachers-template.xlsx',
                'sheet' => 'مدرس‌ها',
                'rules' => Rules::TEACHER_STORE,
                'columns' => [
                    ['key' => 'fullname', 'title' => 'نام و نام خانوادگی', 'normalize' => 'text', 'example' => 'سارا احمدی', 'width' => 28],
                    ['key' => 'national_code', 'title' => 'کدملی', 'normalize' => 'national_code', 'example' => '0499370899', 'width' => 16],
                    ['key' => 'skill', 'title' => 'تخصص', 'normalize' => 'text', 'example' => 'ریاضی', 'width' => 28],
                ],
                'guide' => [
                    'این فایل را بر اساس قالب نمونه پر کنید و ردیف نمونه‌ی ردیف ۲ را قبل از ارسال پاک کنید.',
                    'سطر اول (عنوان ستون‌ها) را تغییر ندهید و ترتیب ستون‌ها را عوض نکنید.',
                    'کدملی ده رقمی است. ستون کدملی به‌صورت متن (Text) تنظیم شده تا صفر اول حذف نشود.',
                    'نام، کدملی و تخصص هر سه اجباری‌اند.',
                    'کدملی هر ردیف نباید در همین فایل یا در سیستم تکراری باشد.',
                    'حداکثر ۵۰۰ ردیف و حداکثر ۲ مگابایت. فقط برگه‌ی اول خوانده می‌شود.',
                ],
            ],
            default => throw new HttpException('نوع ایمپورت نامعتبر است', 404),
        };
    }
}
<?php

declare(strict_types=1);

namespace Helpers;

final class Rules
{
    public const STUDENT_STORE = [
        'fullname' => ['required', 'max:100'],
        'national_code' => ['required', 'national_code'],
        'phone' => ['required', 'mobile'],
        'address' => ['max:255'],
    ];

    public const TEACHER_STORE = [
        'fullname' => ['required', 'max:100'],
        'national_code' => ['required', 'national_code'],
        'skill' => ['required', 'max:100'],
    ];
}
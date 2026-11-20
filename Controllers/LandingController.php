<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Response;

final class LandingController
{
    public static function show(): void
    {
        $pdo = Database::connection();

        $info = $pdo
            ->query('SELECT about, phone, email, address FROM info ORDER BY info_id LIMIT 1')
            ->fetch();

        $slides = $pdo
            ->query('SELECT slide_id, title, description, img_url FROM slide_shows ORDER BY slide_id')
            ->fetchAll();

        $contents = $pdo
            ->query(
                'SELECT content_id, title, description, img_url, created_at
                 FROM new_content
                    ORDER BY created_at DESC, content_id DESC
                 LIMIT 20'
            );

                    $professors = $pdo
            ->query('SELECT professor_id, name, skill, about, img_url FROM professors ORDER BY professor_id')
            ->fetchAll();

                Response::success([
            'info' => $info === false ? null : $info,
            'slides' => $slides,
            'contents' => $contents,
            'professors' => $professors,
        ], 'اطلاعات صفحه‌ی اصلی');
    }
}
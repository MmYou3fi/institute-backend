<?php

declare(strict_types=1);

namespace Models;

use Core\Database;

final class ClassLocation
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT l_id AS location_id, location FROM class_location ORDER BY location')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT l_id AS location_id, location FROM class_location WHERE l_id = :l_id'
        );
        $statement->execute(['l_id' => $id]);
        $location = $statement->fetch();

        return $location === false ? null : $location;
    }

    public static function nameExists(string $location, int $exceptId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM class_location WHERE location = :location AND l_id <> :except_id'
        );
        $statement->execute(['location' => $location, 'except_id' => $exceptId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function create(string $location): int
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare('INSERT INTO class_location (location) VALUES (:location)');
        $statement->execute(['location' => $location]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $location): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE class_location SET location = :location WHERE l_id = :l_id'
        );
        $statement->execute(['location' => $location, 'l_id' => $id]);
    }

    public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM class_location WHERE l_id = :l_id');
        $statement->execute(['l_id' => $id]);
    }

    public static function classCount(int $id): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM classes WHERE l_id = :l_id');
        $statement->execute(['l_id' => $id]);

        return (int) $statement->fetchColumn();
    }
}
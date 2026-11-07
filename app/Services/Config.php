<?php
declare(strict_types=1);
namespace App\Services;

final class Config
{
    private static array $values;

    public static function set(array $values): void
    {
        self::$values = $values;
    }

    public static function get(string $key): mixed
    {
        return self::$values[$key] ?? null;
    }
}

<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Settings
{
    private static ?array $settings = null;

    public static function all(): array
    {
        if (self::$settings === null) {
            self::$settings = array_column(DB::all('SELECT setting_key,setting_value FROM settings'), 'setting_value', 'setting_key');
        }
        return self::$settings;
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    public static function save(string $key, string $value): void
    {
        DB::execute('INSERT INTO settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$key,$value]);
        self::$settings = null;
    }
}

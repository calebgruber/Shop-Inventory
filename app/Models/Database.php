<?php
declare(strict_types=1);
namespace App\Models;

use PDO;

final class Database
{
    public static function connection(): PDO
    {
        return \Illuminate\Support\Facades\DB::connection()->getPdo();
    }

    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function one(string $sql, array $params = []): ?array
    {
        return self::all($sql, $params)[0] ?? null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::execute($sql, $params);
        return (int) self::connection()->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        try {
            $result = \Illuminate\Support\Facades\DB::transaction($callback);
            \App\Services\Documents::commitPending();
            return $result;
        } catch (\Throwable $error) {
            \App\Services\Documents::rollbackPending();
            throw $error;
        }
    }
}

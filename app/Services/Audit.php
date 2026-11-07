<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;

final class Audit
{
    public static function record(string $action, string $type, int $id, array $details = []): void
    {
        DB::execute(
            'INSERT INTO audit_logs (user_id,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)',
            [$_SESSION['user_id'] ?? null, $action, $type, $id, json_encode($details, JSON_THROW_ON_ERROR),
                $_SERVER['REMOTE_ADDR'] ?? 'cli']
        );
    }
}

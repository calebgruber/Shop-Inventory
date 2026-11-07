<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
use App\Models\Database as DB;

try {
    $db = DB::connection();
    $locked = DB::one("SELECT GET_LOCK('theatre_shop_migrations', 10) AS acquired");
    if ((int) $locked['acquired'] !== 1) {
        throw new RuntimeException('Another migration process is running.');
    }
    $db->exec("CREATE TABLE IF NOT EXISTS migrations (
        name VARCHAR(200) PRIMARY KEY, checksum CHAR(64) NOT NULL,
        status VARCHAR(20) NOT NULL, completed_statements INT NOT NULL DEFAULT 0,
        applied_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach (glob(__DIR__ . '/database/migrations/*.php') as $file) {
        $name = basename($file);
        $checksum = hash_file('sha256', $file);
        $existing = DB::one('SELECT * FROM migrations WHERE name=?', [$name]);
        if ($existing && !hash_equals($existing['checksum'], $checksum)) {
            throw new RuntimeException("$name has changed. Never edit an applied migration; add a new one.");
        }
        if (in_array('--status', $argv, true)) {
            echo "$name: " . ($existing['status'] ?? 'pending') . "\n";
            continue;
        }
        if (($existing['status'] ?? '') === 'applied') {
            continue;
        }
        if (!$existing) {
            DB::execute("INSERT INTO migrations(name,checksum,status) VALUES (?,?,'applying')", [$name,$checksum]);
        }
        $statements = (static function (string $migration): array {
            return require $migration;
        })($file);
        // MySQL DDL commits implicitly. Migrations use replay-safe statements and checkpoint each one.
        foreach ($statements as $index => $statement) {
            if ($index < ($existing['completed_statements'] ?? 0)) {
                continue;
            }
            if (is_array($statement)) {
                $exists = DB::one($statement['exists_sql'],$statement['exists_params']);
                if (!(int)$exists['total']) {
                    $db->exec($statement['sql']);
                }
            } else {
                $db->exec($statement);
            }
            DB::execute('UPDATE migrations SET completed_statements=? WHERE name=?', [$index + 1, $name]);
        }
        DB::execute("UPDATE migrations SET status='applied',applied_at=UTC_TIMESTAMP() WHERE name=?", [$name]);
        echo "Applied $name\n";
    }
    DB::one("SELECT RELEASE_LOCK('theatre_shop_migrations')");
} catch (Throwable $error) {
    fwrite(STDERR, "Migration failed: {$error->getMessage()}\n");
    exit(1);
}

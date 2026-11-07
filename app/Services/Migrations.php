<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;
use PDO;

final class Migrations
{
    public static function apply(): array
    {
        $db = DB::connection();
        $locked = DB::one("SELECT GET_LOCK('theatre_shop_migrations', 10) AS acquired");
        if ((int)($locked['acquired'] ?? 0) !== 1) {
            throw new \RuntimeException('Another migration process is running.');
        }
        $applied = [];
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS migrations (
                name VARCHAR(200) PRIMARY KEY, checksum CHAR(64) NOT NULL,
                status VARCHAR(20) NOT NULL, completed_statements INT NOT NULL DEFAULT 0,
                applied_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            foreach (glob(ROOT . '/database/migrations/*.php') as $file) {
                $name = basename($file);
                $checksum = hash_file('sha256', $file);
                $existing = DB::one('SELECT * FROM migrations WHERE name=?',[$name]);
                if ($existing && !hash_equals($existing['checksum'],$checksum)) {
                    throw new \RuntimeException("$name has changed. Add a new migration instead.");
                }
                if (($existing['status'] ?? '') === 'applied') {
                    continue;
                }
                if (!$existing) {
                    DB::execute("INSERT INTO migrations(name,checksum,status) VALUES (?,?,'applying')",[$name,$checksum]);
                }
                $statements = (static fn(string $migration): array => require $migration)($file);
                foreach ($statements as $index => $statement) {
                    if ($index < (int)($existing['completed_statements'] ?? 0)) {
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
                    DB::execute('UPDATE migrations SET completed_statements=? WHERE name=?',[$index + 1,$name]);
                }
                DB::execute("UPDATE migrations SET status='applied',applied_at=UTC_TIMESTAMP() WHERE name=?",[$name]);
                $applied[] = $name;
            }
            return $applied;
        } finally {
            DB::one("SELECT RELEASE_LOCK('theatre_shop_migrations')");
        }
    }

    public static function status(): array
    {
        $rows = [];
        $hasTrackingTable = in_array('migrations',self::existingTables(),true);
        foreach (glob(ROOT . '/database/migrations/*.php') as $file) {
            $name = basename($file);
            $row = $hasTrackingTable ? DB::one('SELECT status,checksum FROM migrations WHERE name=?',[$name]) : null;
            $rows[] = ['name'=>$name,'status'=>$row['status'] ?? 'pending',
                'checksum_matches'=>!$row || hash_equals($row['checksum'],hash_file('sha256',$file))];
        }
        return $rows;
    }

    public static function tableNames(): array
    {
        $tables = [];
        foreach (glob(ROOT . '/database/migrations/*.php') as $file) {
            $source = file_get_contents($file);
            preg_match_all('/CREATE TABLE IF NOT EXISTS\s+([a-z_][a-z0-9_]*)/i',$source,$matches);
            array_push($tables,...$matches[1]);
        }
        return array_values(array_unique([...$tables,'migrations']));
    }

    public static function existingTables(): array
    {
        $rows = DB::connection()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('strval',$rows);
    }
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';

try {
    if (in_array('--status',$argv,true)) {
        foreach (App\Services\Migrations::status() as $row) {
            echo $row['name'] . ': ' . $row['status'] . ($row['checksum_matches']?'':' (checksum mismatch)') . "\n";
        }
    } else {
        foreach (App\Services\Migrations::apply() as $name) {
            echo "Applied $name\n";
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Migration failed: {$error->getMessage()}\n");
    exit(1);
}

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';

try {
    if (($argv[1] ?? '') === 'create-admin') {
        $username = App\Services\Validation::text($argv[2] ?? '', 'Username', 80);
        $email = App\Services\Validation::text($argv[3] ?? '', 'Email', 254);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email is required.');
        }
        if (!function_exists('posix_isatty') || !posix_isatty(STDIN)) {
            throw new RuntimeException('Use an interactive terminal to enter the password securely.');
        }
        fwrite(STDOUT, 'Password (12+ characters): ');
        system('stty -echo');
        try {
            $password = rtrim(fgets(STDIN), "\r\n");
        } finally {
            system('stty echo');
            fwrite(STDOUT, "\n");
        }
        $hash = App\Services\Auth::password($password);
        App\Models\Database::transaction(function () use ($username,$email,$hash): void {
            App\Services\Auth::uniqueIdentifiers($username,$email);
            $id = App\Models\Database::insert('INSERT INTO users(username,email,name,password_hash,role,avatar_seed) VALUES (?,?,?,?,?,?)',
                [$username,$email,$username,$hash,'Super Admin',bin2hex(random_bytes(16))]);
            App\Services\Audit::record('user.created', 'user', $id, ['role'=>'Super Admin']);
        });
        echo "Administrator created. No default credentials are installed.\n";
    } elseif (($argv[1] ?? '') === 'cleanup') {
        App\Models\Database::execute('DELETE FROM login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
        App\Models\Database::execute('DELETE FROM password_resets WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)');
        echo "Expired authentication records removed.\n";
    } else {
        echo "Commands:\n  php console.php create-admin USERNAME EMAIL\n  php console.php cleanup\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

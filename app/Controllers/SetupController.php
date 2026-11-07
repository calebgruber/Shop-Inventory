<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\Database as DB;
use App\Services\Auth;
use App\Services\Config;
use App\Services\HttpException;
use App\Services\Migrations;
use App\Services\Settings;

final class SetupController
{
    public function show(): void
    {
        $status = self::status();
        if ($status['state'] === 'installed') {
            \redirect('/login');
        }
        $values = $status['config'] ?? [];
        \view('setup',[
            'title'=>'Set up your Theatre Shop',
            'values'=>[
                'url'=>$values['url'] ?? self::requestOrigin(),
                'database_host'=>$values['database']['host'] ?? 'localhost',
                'database_port'=>$values['database']['port'] ?? 3306,
                'database_name'=>$values['database']['name'] ?? '',
                'database_username'=>$values['database']['username'] ?? '',
                'mail_from'=>$values['mail_from'] ?? '',
                'app_name'=>$values['name'] ?? 'Theatre Shop',
                'admin_username'=>'','admin_email'=>'','admin_name'=>'',
            ],
            'error'=>$status['state']==='unavailable' ? $status['message'] : '',
            'ready'=>$status['state']!=='unavailable',
        ],false);
    }

    public function install(): void
    {
        $values = $_POST;
        unset($values['database_password'],$values['admin_password'],$values['admin_password_confirm']);
        try {
            if (self::status()['state'] === 'installed') {
                \redirect('/login');
            }
            if (!is_dir(ROOT . '/config') || !is_writable(ROOT . '/config')) {
                throw new HttpException(503,'The private config directory must be writable by PHP during installation.');
            }
            $candidate = self::configuration($_POST);
            Config::set($candidate);
            $db = DB::connection();
            $version = (string)DB::one('SELECT VERSION() AS version')['version'];
            if (preg_match('/^(\d+)/',$version,$match) && (int)$match[1] < 8 &&
                !stripos($version,'mariadb')) {
                throw new HttpException(422,'Use MySQL 8 or newer, or a supported MariaDB installation.');
            }
            self::assertInstallableSchema();
            if (strlen((string)($_POST['admin_password'] ?? '')) < 12 ||
                strlen((string)($_POST['admin_password'] ?? '')) > 1024) {
                throw new HttpException(422,'The administrator password must be between 12 and 1024 bytes.');
            }
            if (!hash_equals((string)($_POST['admin_password'] ?? ''),(string)($_POST['admin_password_confirm'] ?? ''))) {
                throw new HttpException(422,'The administrator password confirmation does not match.');
            }
            $username = \App\Services\Validation::text($_POST['admin_username'] ?? '', 'Username',80);
            $email = \App\Services\Validation::text($_POST['admin_email'] ?? '', 'Email',254);
            $name = \App\Services\Validation::text($_POST['admin_name'] ?? '', 'Name',160);
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(422,'Enter a valid administrator email address.');
            }
            if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/D',$username)) {
                throw new HttpException(422,'Use 3-80 letters, numbers, periods, underscores, or hyphens for the username.');
            }
            if ((int)DB::one('SELECT GET_LOCK(?,0) AS acquired',['theatre_shop_install'])['acquired'] !== 1) {
                throw new HttpException(409,'Another installation is already in progress. Wait a moment and retry.');
            }
            try {
                self::assertInstallableSchema();
                $candidate['installed'] = false;
                self::writeConfiguration($candidate);
                Migrations::apply();
                DB::transaction(function () use ($username,$email,$name,$candidate): void {
                    if ((int)DB::one('SELECT COUNT(*) AS total FROM users')['total'] !== 0) {
                        throw new HttpException(409,'This database already contains user accounts. The installer cannot overwrite them.');
                    }
                    Auth::uniqueIdentifiers($username,$email);
                    $id = DB::insert(
                        'INSERT INTO users(username,email,name,password_hash,role,avatar_seed) VALUES (?,?,?,?,?,?)',
                        [$username,$email,$name,Auth::password((string)$_POST['admin_password']),'Super Admin',bin2hex(random_bytes(16))]
                    );
                    Settings::save('brand_name',$candidate['name']);
                    Settings::save('pull_mode','barcode');
                    Settings::save('return_mode','barcode');
                    Settings::save('theme_default','light');
                    \App\Services\Audit::record('installer.completed','user',$id,['role'=>'Super Admin']);
                });
                $candidate['installed'] = true;
                self::writeConfiguration($candidate);
            } finally {
                DB::one("SELECT RELEASE_LOCK('theatre_shop_install')");
            }
            $_SESSION = [];
            \App\Middleware\Security::rotate();
            \redirect('/login?installed=1');
        } catch (HttpException $error) {
            http_response_code($error->status);
            \view('setup',[
                'title'=>'Set up your Theatre Shop','values'=>self::safeFormValues($values),
                'error'=>$error->getMessage(),'ready'=>true,
            ],false);
        } catch (\Throwable $error) {
            $reference = bin2hex(random_bytes(6));
            error_log("Theatre Shop installation [$reference]: " . $error);
            http_response_code(500);
            \view('setup',[
                'title'=>'Set up your Theatre Shop','values'=>self::safeFormValues($values),
                'error'=>"Setup could not complete. Verify the database details and permissions, then retry. Reference: $reference.",
                'ready'=>true,
            ],false);
        }
    }

    private static function status(): array
    {
        $path = ROOT . '/config/local.php';
        if (!is_file($path)) {
            return ['state'=>'ready','config'=>null];
        }
        $config = require $path;
        if (($config['installed'] ?? false) === true) {
            return ['state'=>'installed','config'=>$config];
        }
        try {
            Config::set(array_replace_recursive(require ROOT . '/config/app.php',$config));
            $tables = Migrations::existingTables();
            if (array_diff($tables,Migrations::tableNames())) {
                return ['state'=>'unavailable','config'=>$config,
                    'message'=>'This database contains unrelated tables. Choose a new empty database to protect existing data.'];
            }
            if (in_array('users',$tables,true) && (int)DB::one('SELECT COUNT(*) AS total FROM users')['total'] > 0) {
                return ['state'=>'installed','config'=>$config];
            }
            return ['state'=>'ready','config'=>$config];
        } catch (\Throwable) {
            return ['state'=>'unavailable','config'=>$config,
                'message'=>'The saved database configuration cannot be verified. Restore database connectivity or finish setup from the hosting terminal.'];
        }
    }

    private static function assertInstallableSchema(): void
    {
        $tables = Migrations::existingTables();
        $unexpected = array_diff($tables,Migrations::tableNames());
        if ($unexpected) {
            throw new HttpException(409,'The database is not empty or contains tables that do not belong to this application. Use a new, empty database.');
        }
        if (in_array('users',$tables,true) && (int)DB::one('SELECT COUNT(*) AS total FROM users')['total'] > 0) {
            throw new HttpException(409,'This database already contains users. The installer is locked to protect existing accounts.');
        }
    }

    private static function configuration(array $input): array
    {
        if (($_SERVER['HTTPS'] ?? '') !== 'on') {
            throw new HttpException(400,'Open the setup page over HTTPS before installing.');
        }
        $url = trim((string)($input['url'] ?? ''));
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
            !in_array($parts['path'] ?? '',['','/'],true)) {
            throw new HttpException(422,'Enter the canonical HTTPS origin for this site, without a path.');
        }
        $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $urlAuthority = strtolower($parts['host']) . (isset($parts['port']) && $parts['port'] !== 443 ? ':' . $parts['port'] : '');
        $normalizedRequestHost = preg_replace('/:443$/','',$requestHost);
        if ($urlAuthority !== $normalizedRequestHost || !filter_var($parts['host'],FILTER_VALIDATE_IP) &&
            !preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD',$parts['host'])) {
            throw new HttpException(422,'The canonical HTTPS origin must match the domain currently open in your browser.');
        }
        $host = trim((string)($input['database_host'] ?? ''));
        if (!filter_var($host,FILTER_VALIDATE_IP) && !preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD',$host)) {
            throw new HttpException(422,'Enter a valid MySQL server hostname or IP address.');
        }
        self::validateDatabaseHost($host);
        $port = filter_var($input['database_port'] ?? 3306,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
        $database = trim((string)($input['database_name'] ?? ''));
        $dbUser = (string)($input['database_username'] ?? '');
        $dbPassword = (string)($input['database_password'] ?? '');
        $mail = trim((string)($input['mail_from'] ?? ''));
        $appName = \App\Services\Validation::text($input['app_name'] ?? 'Theatre Shop','Application name',160);
        if (!$port || !preg_match('/^[A-Za-z0-9_]{1,64}$/D',$database) || $dbUser==='' || strlen($dbUser)>128 ||
            str_contains($dbUser,"\0") || str_contains($dbPassword,"\0")) {
            throw new HttpException(422,'Enter a valid database name, username, host, and port.');
        }
        $existing = is_file(ROOT . '/config/local.php') ? require ROOT . '/config/local.php' : [];
        if (($existing['installed'] ?? false) !== true && isset($existing['database'])) {
            foreach (['host','port','name','username'] as $key) {
                if ((string)($existing['database'][$key] ?? '') !== (string)($key==='host'?$host:($key==='port'?$port:($key==='name'?$database:$dbUser)))) {
                    throw new HttpException(409,'Resume setup using the same database host, port, name, username, and site origin.');
                }
            }
            if (($existing['url'] ?? '') !== $url) {
                throw new HttpException(409,'Resume setup using the same canonical site origin.');
            }
            $savedPassword = (string)($existing['database']['password'] ?? '');
            if ($dbPassword !== '' && !hash_equals($savedPassword,$dbPassword)) {
                throw new HttpException(409,'Resume setup with the saved database password, or update the configuration from your hosting terminal.');
            }
            $dbPassword = $savedPassword;
        }
        if (!filter_var($mail,FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422,'Enter a valid email address for system emails.');
        }
        return [
            'name'=>$appName,'url'=>rtrim($url,'/'),'environment'=>'production','mail_from'=>$mail,
            'database'=>['host'=>$host,'port'=>(int)$port,'name'=>$database,'username'=>$dbUser,'password'=>$dbPassword],
        ];
    }

    private static function writeConfiguration(array $config): void
    {
        $temporary = tempnam(ROOT . '/config','install-');
        if ($temporary===false) {
            throw new \RuntimeException('Cannot create private configuration file.');
        }

        try {
            $source = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config,true) . ";\n";
            if (file_put_contents($temporary,$source,LOCK_EX)!==strlen($source) || !chmod($temporary,0600) ||
                !rename($temporary,ROOT . '/config/local.php')) {
                throw new \RuntimeException('Cannot safely save private configuration.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private static function validateDatabaseHost(string $host): void
    {
        $host = strtolower($host);
        if (in_array($host,['localhost','127.0.0.1','::1'],true)) {
            return;
        }
        $addresses = filter_var($host,FILTER_VALIDATE_IP)
            ? [$host]
            : array_values(array_filter(array_map(
                static fn(array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
                dns_get_record($host,DNS_A|DNS_AAAA) ?: []
            )));
        if (!$addresses) {
            throw new HttpException(422,'The database host could not be resolved. Check the hostname provided by your hosting panel.');
        }
        foreach ($addresses as $address) {
            if (!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
                throw new HttpException(422,'For security, remote database hosts must resolve only to public IP addresses. Use localhost for the cPanel database.');
            }
        }
    }

    private static function safeFormValues(array $input): array
    {
        return [
            'url'=>(string)($input['url'] ?? self::requestOrigin()),
            'database_host'=>(string)($input['database_host'] ?? 'localhost'),
            'database_port'=>(string)($input['database_port'] ?? '3306'),
            'database_name'=>(string)($input['database_name'] ?? ''),
            'database_username'=>(string)($input['database_username'] ?? ''),
            'mail_from'=>(string)($input['mail_from'] ?? ''),
            'app_name'=>(string)($input['app_name'] ?? 'Theatre Shop'),
            'admin_username'=>(string)($input['admin_username'] ?? ''),
            'admin_email'=>(string)($input['admin_email'] ?? ''),
            'admin_name'=>(string)($input['admin_name'] ?? ''),
        ];
    }

    private static function requestOrigin(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'shop.example.edu');
        return 'https://' . preg_replace('/[^A-Za-z0-9.:-]/','',$host);
    }
}

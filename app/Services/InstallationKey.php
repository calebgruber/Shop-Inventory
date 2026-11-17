<?php
declare(strict_types=1);
namespace App\Services;

final class InstallationKey
{
    public static function get(): string
    {
        $path = ROOT . '/storage/framework/installation.key';
        $file = fopen($path,'c+');
        if ($file===false) {
            throw new \RuntimeException('The private storage/framework directory must be writable by PHP.');
        }
        try {
            if (!chmod($path,0600) || !flock($file,LOCK_EX)) {
                throw new \RuntimeException('Cannot secure the application encryption key.');
            }
            $key = trim(stream_get_contents($file));
            if ($key==='') {
                $key = 'base64:' . base64_encode(random_bytes(32));
                if (fwrite($file,$key)!==strlen($key) || !fflush($file)) {
                    throw new \RuntimeException('Cannot persist the application encryption key.');
                }
            }
            if (!str_starts_with($key,'base64:') || strlen((string)base64_decode(substr($key,7),true))!==32) {
                throw new \RuntimeException('The saved application encryption key is invalid.');
            }
            return $key;
        } finally {
            flock($file,LOCK_UN);
            fclose($file);
        }
    }
}

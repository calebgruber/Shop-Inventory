<?php
declare(strict_types=1);
namespace App\Services;

final class Config
{
    public static function set(array $values): void
    {
        $database = config('shop.database');
        config(['shop'=>array_replace_recursive(config('shop',[]),$values)]);
        if (isset($values['database']) && $database!==config('shop.database')) {
            $db = config('shop.database');
            config(['database.connections.mysql'=>array_replace(config('database.connections.mysql'),[
                'host'=>$db['host'],'port'=>$db['port'],'database'=>$db['name'],
                'username'=>$db['username'],'password'=>$db['password'],
            ])]);
            \Illuminate\Support\Facades\DB::purge('mysql');
        }
    }

    public static function get(string $key): mixed
    {
        return config('shop.' . $key);
    }
}

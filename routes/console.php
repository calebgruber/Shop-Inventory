<?php
declare(strict_types=1);

use App\Services\Auth;
use App\Services\Validation;
use App\Models\Database as DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('shop:create-admin {username} {email}',function (): int {
    $username = Validation::text($this->argument('username'),'Username',80);
    $email = Validation::text($this->argument('email'),'Email',254);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email address.');
        return 1;
    }
    $hash = Auth::password((string)$this->secret('Password (12+ characters)'));
    DB::transaction(function () use ($username,$email,$hash): void {
        Auth::uniqueIdentifiers($username,$email);
        $id = DB::insert('INSERT INTO users(username,email,name,password_hash,role,avatar_seed) VALUES (?,?,?,?,?,?)',
            [$username,$email,$username,$hash,'Super Admin',bin2hex(random_bytes(16))]);
        \App\Services\Audit::record('user.created','user',$id,['role'=>'Super Admin']);
    });
    $this->info('Administrator created.');
    return 0;
})->purpose('Create a shop administrator using a hidden password prompt');

Artisan::command('shop:cleanup',function (): void {
    DB::execute('DELETE FROM login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
    DB::execute('DELETE FROM password_resets WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)');
    $this->info('Expired authentication records removed.');
})->purpose('Remove expired authentication records, preserving audit history');

Schedule::command('shop:cleanup')->daily()->withoutOverlapping();

<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $guarded = ['id','role','auth_version','active'];
    protected $hidden = ['password_hash','remember_token'];
    public $timestamps = false;

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    protected function casts(): array
    {
        return ['active'=>'boolean','auth_version'=>'integer'];
    }
}

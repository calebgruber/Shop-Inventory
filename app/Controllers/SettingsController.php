<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\Database as DB;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\Avatar;
use App\Services\Settings;
use App\Services\Uploads;
use App\Services\Validation;
use App\Services\HttpException;
use App\Middleware\Permissions;

final class SettingsController
{
    public const BRANDING = ['logo_light','logo_dark','background_light','background_dark','favicon'];

    public function index(): void
    {
        $settings = Settings::all();
        \view('settings/index',compact('settings')+['title'=>'Shop Settings','workspaceKey'=>'settings']);
    }

    public function save(): void
    {
        $data = \input();
        $values = [
            'brand_name'=>Validation::text($data['brand_name'] ?? '', 'Shop name',160),
            'theme_default'=>Validation::choice($data['theme_default'] ?? '',['light','dark','system'],'theme'),
            'pull_mode'=>Validation::choice($data['pull_mode'] ?? '',['barcode','manual'],'pull mode'),
            'return_mode'=>Validation::choice($data['return_mode'] ?? '',['barcode','manual'],'return mode'),
        ];
        $received = [];
        try {
            foreach (self::BRANDING as $key) {
                if (isset($_FILES[$key]) && $_FILES[$key]['error']!==UPLOAD_ERR_NO_FILE) {
                    $upload = Uploads::receive($_FILES[$key],true);
                    $values[$key] = $upload['name'];
                    $received[] = ROOT . '/storage/uploads/' . $upload['name'];
                }
            }
            DB::transaction(function () use ($values): void {
                foreach ($values as $key=>$value) {
                    Settings::save($key,$value);
                }
                Audit::record('settings.updated','settings',0,['keys'=>array_keys($values)]);
            });
        } catch (\Throwable $error) {
            foreach ($received as $path) {
                unlink($path);
            }
            throw $error;
        }
        \json_response(['redirect'=>'/settings','message'=>'Shop settings saved. Refresh the page to apply global branding.']);
    }

    public function branding(string $key): void
    {
        if (!in_array($key,self::BRANDING,true)) {
            throw new HttpException(404,'Branding image not found.');
        }
        $name = Settings::get($key);
        $path = ROOT . '/storage/uploads/' . basename($name);
        if ($name==='' || !is_file($path)) {
            throw new HttpException(404,'No branding image configured.');
        }
        header('Content-Type: ' . (new \finfo(FILEINFO_MIME_TYPE))->file($path));
        header('Content-Disposition: inline');
        readfile($path);
    }

    public function profile(): void
    {
        $user = Auth::user();
        $keys = DB::all('SELECT id,label,created_at FROM passkeys WHERE user_id=? AND revoked_at IS NULL',[$user['id']]);
        \view('settings/profile',compact('user','keys')+['title'=>'My Profile','workspaceKey'=>'profile']);
    }

    public function avatar(int $id): void
    {
        $user = DB::one('SELECT avatar_seed FROM users WHERE id=? AND active=1',[$id]) ?? throw new HttpException(404,'User not found.');
        header('Content-Type: image/svg+xml; charset=utf-8');
        echo Avatar::svg($user['avatar_seed']);
    }

    public function regenerate(): void
    {
        DB::transaction(function (): void {
            DB::execute('UPDATE users SET avatar_seed=? WHERE id=?',[bin2hex(random_bytes(16)),Auth::user()['id']]);
            Audit::record('avatar.regenerated','user',(int)Auth::user()['id']);
        });
        \json_response(['message'=>'New avatar generated.']);
    }

    public function revokeKey(int $id): void
    {
        if (time()-($_SESSION['authenticated_at'] ?? 0)>600) {
            throw new HttpException(403,'Sign in again to manage passkeys.');
        }
        DB::transaction(function () use ($id): void {
            if (!DB::execute('UPDATE passkeys SET revoked_at=UTC_TIMESTAMP() WHERE id=? AND user_id=? AND revoked_at IS NULL',[$id,Auth::user()['id']])) {
                throw new HttpException(404,'Passkey not found.');
            }
            Audit::record('passkey.revoked','passkey',$id);
        });
        \json_response(['redirect'=>'/profile']);
    }

    public function password(): void
    {
        Auth::throttle('profile-password:' . Auth::user()['id']);
        $data = \input();
        if (!password_verify(Validation::passwordInput($data['current_password'] ?? ''),Auth::user()['password_hash'])) {
            throw new HttpException(422,'Current password is incorrect.');
        }
        $hash = Auth::password(Validation::passwordInput($data['password'] ?? ''));
        DB::transaction(function () use ($hash): void {
            DB::execute('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[$hash,Auth::user()['id']]);
            Audit::record('password.changed','user',(int)Auth::user()['id']);
        });
        Auth::logout(false);
        \json_response(['redirect'=>'/login']);
    }

    public function userForm(?int $id = null): void
    {
        $record = $id?DB::one('SELECT id,username,email,name,role,active FROM users WHERE id=?',[$id]):null;
        if ($id && !$record) {
            throw new HttpException(404,'User not found.');
        }
        \view('settings/user',compact('record')+['title'=>$id?'User: ' . $record['name']:'Create User','workspaceKey'=>$id?'user:' . $id:'new-user']);
    }

    public function userSave(?int $id = null): void
    {
        $data = \input();
        $email = Validation::text($data['email'] ?? '', 'Email',254);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422,'A valid email address is required.');
        }
        $values = [Validation::text($data['username'] ?? '', 'Username',80),$email,Validation::text($data['name'] ?? '', 'Name',160),
            Validation::choice($data['role'] ?? '',array_keys(Permissions::ROLES),'role'),isset($data['active'])?1:0];
        DB::transaction(function () use ($id,$data,$values): void {
            Auth::uniqueIdentifiers($values[0],$values[1],$id);
            if ($id) {
                $admins = DB::all("SELECT id FROM users WHERE role='Super Admin' AND active=1 ORDER BY id FOR UPDATE");
                $before = DB::one('SELECT id,role,active FROM users WHERE id=? FOR UPDATE',[$id]);
                if (!$before) {
                    throw new HttpException(404,'User not found.');
                }
                if ($before['role']==='Super Admin' && $before['active'] && count($admins)===1 && ($values[3]!=='Super Admin' || !$values[4])) {
                    throw new HttpException(422,'The last active Super Admin cannot be removed or demoted.');
                }
                DB::execute('UPDATE users SET username=?,email=?,name=?,role=?,active=?,auth_version=auth_version+1 WHERE id=?',[...$values,$id]);
                Audit::record('user.updated','user',$id,['role'=>$values[3],'active'=>$values[4]]);
            } else {
                $hash = Auth::password(Validation::passwordInput($data['password'] ?? ''));
                $newId = DB::insert('INSERT INTO users(username,email,name,role,active,password_hash,avatar_seed) VALUES (?,?,?,?,?,?,?)',[...$values,$hash,bin2hex(random_bytes(16))]);
                Audit::record('user.created','user',$newId,['role'=>$values[3]]);
            }
        });
        \json_response(['redirect'=>$id===(int)Auth::user()['id']?'/login':'/users']);
    }
}

<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

final class Passkeys
{
    private static function library(): WebAuthn
    {
        return new WebAuthn(Config::get('name'), parse_url(Config::get('url'), PHP_URL_HOST), ['none','packed','fido-u2f','apple','android-key','tpm'], true);
    }

    public static function begin(bool $register): array
    {
        $library = self::library();
        if ($register) {
            $user = Auth::user();
            if (!$user || time() - ($_SESSION['authenticated_at'] ?? 0) > 600) {
                throw new HttpException(403, 'Sign in again before registering a passkey.');
            }
            $ids = array_column(DB::all('SELECT credential_id FROM passkeys WHERE user_id=? AND revoked_at IS NULL', [$user['id']]), 'credential_id');
            $options = $library->getCreateArgs((string) $user['id'], $user['username'], $user['name'], 120, true, 'required', null, $ids);
        } else {
            Auth::throttle('passkey');
            $options = $library->getGetArgs([], 120, true, true, true, true, true, 'required');
        }
        $_SESSION['webauthn'] = [
            'challenge' => $library->getChallenge()->getBinaryString(), 'register' => $register,
            'user_id' => $register ? $user['id'] : null, 'expires' => time() + 120,
        ];
        return json_decode(json_encode($options, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
    }

    public static function decode(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 65536 || !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
            throw new HttpException(422, 'Invalid passkey response.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new HttpException(422, 'Invalid passkey encoding.');
        }
        return $decoded;
    }

    public static function finish(array $data, bool $register): void
    {
        $state = $_SESSION['webauthn'] ?? null;
        unset($_SESSION['webauthn']);
        if (!$state || $state['expires'] < time() || $state['register'] !== $register ||
            ($register && $state['user_id'] !== (Auth::user()['id'] ?? null))) {
            throw new HttpException(422, 'The passkey request expired. Please try again.');
        }
        $client = self::decode($data['clientDataJSON'] ?? '');
        $clientData = json_decode($client, true, 32, JSON_THROW_ON_ERROR);
        $origin = rtrim(Config::get('url'), '/');
        if (($clientData['origin'] ?? '') !== $origin || ($clientData['crossOrigin'] ?? false)) {
            Audit::record('passkey.invalid_origin', 'user', (int) ($state['user_id'] ?? 0));
            throw new HttpException(422, 'Passkey origin validation failed.');
        }
        $library = self::library();
        try {
            if ($register) {
                $result = $library->processCreate($client, self::decode($data['attestationObject'] ?? ''), $state['challenge'], true, true, false);
                DB::transaction(function () use ($result, $data, $state): void {
                    $id = DB::insert('INSERT INTO passkeys(user_id,credential_id,credential_hash,public_key,sign_count,label) VALUES (?,?,?,?,?,?)',
                        [$state['user_id'],$result->credentialId,hash('sha256',$result->credentialId,true),$result->credentialPublicKey,$result->signatureCounter ?? 0,
                            Validation::text($data['label'] ?? 'My passkey','Passkey name',100)]);
                    Audit::record('passkey.registered', 'passkey', $id);
                });
            } else {
                $credential = self::decode($data['rawId'] ?? '');
                $user = DB::transaction(function () use ($library,$client,$data,$state,$credential): array {
                    $key = DB::one('SELECT * FROM passkeys WHERE credential_hash=? AND revoked_at IS NULL FOR UPDATE', [hash('sha256',$credential,true)]);
                    if (!$key || !hash_equals($key['credential_id'],$credential)) {
                        throw new HttpException(422, 'Passkey authentication failed.');
                    }
                    $user = DB::one('SELECT * FROM users WHERE id=? AND active=1 FOR UPDATE', [$key['user_id']]);
                    if (!$user || ($user['locked_until'] && strtotime($user['locked_until'] . ' UTC') > time())) {
                        throw new HttpException(422, 'Passkey authentication failed.');
                    }
                    if (empty($data['userHandle']) || self::decode($data['userHandle']) !== (string) $user['id']) {
                        throw new HttpException(422, 'Passkey account validation failed.');
                    }
                    $library->processGet($client,self::decode($data['authenticatorData'] ?? ''),self::decode($data['signature'] ?? ''),
                        $key['public_key'],$state['challenge'],(int) $key['sign_count'],true,true);
                    DB::execute('UPDATE passkeys SET sign_count=? WHERE id=?', [$library->getSignatureCounter() ?? 0,$key['id']]);
                    Audit::record('passkey.authenticated','passkey',(int) $key['id']);
                    return $user;
                });
                Auth::establish($user);
            }
        } catch (WebAuthnException $error) {
            Audit::record('passkey.failed', 'user', (int) ($state['user_id'] ?? 0), ['reason'=>$error->getCode()]);
            throw new HttpException(422, 'Passkey verification failed. Please try again.');
        }
    }
}

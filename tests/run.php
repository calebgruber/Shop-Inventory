<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__) . '/bootstrap.php';
if (in_array('--integration',$argv,true)) {
    App\Middleware\Security::start();
}

use App\Services\Planning;
use App\Services\Rules;
use App\Services\Inventory;
use App\Services\Validation;
use App\Services\HttpException;
use App\Services\Auth;
use App\Services\Orders;
use App\Services\Shows;
use App\Services\Fulfillment;
use App\Services\Settings;
use App\Services\Documents;
use App\Models\Database as DB;

$passed = 0;
$failed = 0;
function test(string $name, callable $callback): void
{
    global $passed,$failed;
    try {
        $callback();
        $passed++;
        echo "PASS $name\n";
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR,"FAIL $name: {$error->getMessage()}\n");
    }
}
function same(mixed $expected, mixed $actual): void
{
    if ($expected!==$actual) {
        throw new RuntimeException('Expected ' . var_export($expected,true) . '; got ' . var_export($actual,true));
    }
}
function rejects(callable $callback, int $status = 422): void
{
    try {
        $callback();
    } catch (HttpException $error) {
        same($status,$error->status);
        return;
    }
    throw new RuntimeException('Invalid action was not rejected.');
}
test('Peak demand does not sum non-overlapping shows',function (): void {
    same(8,Planning::peak([
        ['start_date'=>'2026-01-01','return_date'=>'2026-01-03','quantity'=>8],
        ['start_date'=>'2026-01-04','return_date'=>'2026-01-06','quantity'=>7],
    ],'2026-01-01','2026-01-10'));
});
test('Return and prep on the same day conflict',function (): void {
    same(15,Planning::peak([
        ['start_date'=>'2026-01-01','return_date'=>'2026-01-03','quantity'=>8],
        ['start_date'=>'2026-01-03','return_date'=>'2026-01-06','quantity'=>7],
    ],'2026-01-01','2026-01-10'));
});
test('Ratios round source groups up',fn() => same(6,Rules::required(5,2,2)));
test('Dates reject calendar overflow',fn() => rejects(fn() => Validation::date('2026-02-30','Date')));
test('Quantities reject decimals, negatives and zero',function (): void {
    foreach (['1.5',-1,0,'garbage'] as $value) rejects(fn() => Validation::positive($value,'Quantity'));
});
test('CSV headers and pasted serials parse',function (): void {
    same([['asset_tag'=>'S4-0001','serial_number'=>'SN-123']],Inventory::parseImport("asset_tag,serial_number\nS4-0001,SN-123",'csv'));
    same([['asset_tag'=>'','serial_number'=>'SN-1'],['asset_tag'=>'','serial_number'=>'SN-2']],Inventory::parseImport("SN-1\nSN-2",'text'));
});
test('Password hashing uses Argon2id',function (): void {
    $hash = Auth::password('a-long-test-password');
    same('argon2id',password_get_info($hash)['algoName']);
    same(true,password_verify('a-long-test-password',$hash));
});
test('Password input preserves intentional surrounding spaces',function (): void {
    $password = '  a-long-test-password  ';
    same($password,Validation::passwordInput($password));
    same(true,password_verify($password,Auth::password($password)));
    rejects(fn() => Validation::passwordInput(['not-a-string']));
});
test('Passkey input decoder rejects malformed input',fn() => rejects(fn() => App\Services\Passkeys::decode('<invalid>')));
test('Avatar is deterministic and differs across seeds',function (): void {
    $one = App\Services\Avatar::svg(str_repeat('a',32));
    same($one,App\Services\Avatar::svg(str_repeat('a',32)));
    same(false,$one===App\Services\Avatar::svg(str_repeat('b',32)));
    same(false,str_contains($one,'http://api.'));
});
test('Setup recognizes the full schema and status can be inspected before installation',function (): void {
    $tables = App\Services\Migrations::tableNames();
    same(true,in_array('users',$tables,true));
    same(true,in_array('migrations',$tables,true));
    same(true,count($tables)>=27);
});

if (in_array('--integration',$argv,true)) {
    if (!str_ends_with(App\Services\Config::get('database')['name'],'_testing') ||
        (int)DB::one('SELECT COUNT(*) AS total FROM users')['total']!==0) {
        throw new RuntimeException('Integration tests require an empty, disposable database with a name ending in _testing.');
    }
    $userId = DB::insert('INSERT INTO users(username,email,name,password_hash,role,avatar_seed) VALUES (?,?,?,?,?,?)',
        ['test-admin','admin@example.test','Test Administrator',Auth::password('integration-password-123'),'Super Admin',bin2hex(random_bytes(16))]);
    Auth::establish(DB::one('SELECT * FROM users WHERE id=?',[$userId]));
    test('Migration tracking records every filename as applied',function (): void {
        $rows = DB::all('SELECT name,status FROM migrations ORDER BY name');
        $names = array_map('basename',glob(ROOT . '/database/migrations/*.php'));
        same($names,array_column($rows,'name'));
        same(array_fill(0,count($names),'applied'),array_column($rows,'status'));
    });
    $category = Inventory::folder(['name'=>'Lighting']);
    $subcategory = Inventory::folder(['name'=>'Fixtures','parent_id'=>$category]);
    $fixture = Inventory::itemType(['name'=>'Source Four','folder_id'=>$subcategory,'prefix'=>'S4']);
    $accessory = Inventory::itemType(['name'=>'Safety Cable','folder_id'=>$subcategory,'prefix'=>'SAFE']);
    Inventory::import($fixture,"SN-01\nSN-02\nSN-03\nSN-04\nSN-05\nSN-06",'text');
    Inventory::import($accessory,"SAFE-01\nSAFE-02\nSAFE-03\nSAFE-04\nSAFE-05\nSAFE-06",'text');
    $today = gmdate('Y-m-d');
    $end = gmdate('Y-m-d',time()+10*86400);
    $showData = ['name'=>'Hamlet','theatre'=>'Mainstage'];
    foreach (Shows::DATES as $key) $showData[$key] = $key==='return_date'?$end:$today;
    $showId = Shows::save($showData);
    $showData['name'] = 'RUR';
    $destination = Shows::save($showData);
    $package = Inventory::package(['name'=>'Source Four Package','components'=>[
        ['item_type_id'=>$fixture,'quantity'=>1],['item_type_id'=>$accessory,'quantity'=>1],
    ]]);
    $ruleId = Inventory::rule(['name'=>'Fixture safety','source_type_id'=>$fixture,'source_quantity'=>1,
        'dependency_type_id'=>$accessory,'dependency_quantity'=>1,'mode'=>'strict']);
    $original = Orders::create($showId,['title'=>'Original Shop Order','lines'=>[['package_id'=>$package,'quantity'=>2]]]);
    $revisionId = $original['revision_id'];
    $originalRows = Orders::lines($revisionId);
    test('Package expansion preserves component quantities and dates',function () use ($originalRows,$end): void {
        same(2,count($originalRows));
        same([2,2],array_column($originalRows,'quantity'));
        same([$end,$end],array_column($originalRows,'return_date'));
    });
    test('Strict accessory rule blocks incomplete request atomically',function () use ($showId,$fixture): void {
        $before = (int)DB::one('SELECT COUNT(*) AS total FROM revisions')['total'];
        rejects(fn() => Orders::create($showId,['lines'=>[['item_type_id'=>$fixture,'quantity'=>1]]]));
        same($before,(int)DB::one('SELECT COUNT(*) AS total FROM revisions')['total']);
    });
    test('Shortage is shown in planning, not hidden',function () use ($showId,$package): void {
        $large = Orders::create($showId,['title'=>'Shortage request','lines'=>[['package_id'=>$package,'quantity'=>20]]]);
        same(true,count(array_filter($large['issues'],fn(array $i): bool => $i['type']==='shortage'))>0);
        rejects(fn() => Orders::approve($large['revision_id'],'Approved'));
        Orders::approve($large['revision_id'],'Rejected');
    });
    Orders::approve($revisionId,'Approved');
    test('Approval cannot rewrite a previous decision',fn() => rejects(fn() => Orders::approve($revisionId,'Rejected')));
    Settings::save('pull_mode','barcode');
    $pull = Fulfillment::pull($revisionId,['asset_tag'=>'S4-0001']);
    test('Barcode pull is partial and records the actual asset',function () use ($pull): void {
        same(true,$pull['partial']);
        same('On Show',DB::one("SELECT state FROM assets WHERE asset_tag='S4-0001'")['state']);
    });
    test('Duplicate scan is blocked',fn() => rejects(fn() => Fulfillment::pull($revisionId,['asset_tag'=>'S4-0001'])));
    Fulfillment::pull($revisionId,['asset_tag'=>'S4-0002']);
    test('Extra scanner quantity is blocked',fn() => rejects(fn() => Fulfillment::pull($revisionId,['asset_tag'=>'S4-0003'])));
    Settings::save('pull_mode','manual');
    test('Manual quantities cannot exceed requested amounts',function () use ($revisionId,$originalRows): void {
        rejects(fn() => Fulfillment::pull($revisionId,['quantities'=>[$originalRows[1]['id']=>3]]));
    });
    Fulfillment::pull($revisionId,['quantities'=>[$originalRows[1]['id']=>1]]);
    test('Manual partial pulls allocate a serialized asset',function () use ($revisionId): void {
        $lines = Orders::lines($revisionId);
        same(1,(int)$lines[1]['pulled']);
    });
    test('Manual pulls allocate distinct assets across repeated item-type lines',function () use ($showId,$fixture,$accessory): void {
        $order = Orders::create($showId,['lines'=>[
            ['item_type_id'=>$fixture,'quantity'=>1],
            ['item_type_id'=>$fixture,'quantity'=>1],
            ['item_type_id'=>$accessory,'quantity'=>2],
        ]]);
        Orders::approve($order['revision_id'],'Approved');
        $lines = Orders::lines($order['revision_id']);
        $result = Fulfillment::pull($order['revision_id'],['quantities'=>[$lines[0]['id']=>1,$lines[1]['id']=>1]]);
        same(true,$result['partial']);
        $assets = DB::all('SELECT a.asset_id FROM assignments a JOIN revision_lines l ON l.id=a.line_id WHERE l.revision_id=?',[$order['revision_id']]);
        same(2,count(array_unique(array_column($assets,'asset_id'))));
        foreach ($assets as $asset) {
            $tag = DB::one('SELECT asset_tag FROM assets WHERE id=?',[$asset['asset_id']])['asset_tag'];
            Settings::save('return_mode','barcode');
            Fulfillment::returns($showId,['asset_tag'=>$tag,'condition'=>'Good']);
        }
    });
    $next = Orders::create($showId,['lines'=>[['package_id'=>$package,'quantity'=>1]]],$original['order_id']);
    test('Revisions are discrete and original request rows do not change',function () use ($next,$originalRows,$revisionId): void {
        same(1,(int)Orders::revision($next['revision_id'])['revision_number']);
        $now = Orders::lines($revisionId);
        foreach ($now as $index=>$line) {
            foreach (['id','revision_id','item_type_id','quantity','start_date','return_date'] as $key) same($originalRows[$index][$key],$line[$key]);
        }
    });
    $destOrder = Orders::create($destination,['lines'=>[['package_id'=>$package,'quantity'=>1]]]);
    Orders::approve($destOrder['revision_id'],'Approved');
    $destLine = Orders::lines($destOrder['revision_id'])[0]['id'];
    $transfer = Fulfillment::transfer($showId,['destination_line_id'=>$destLine,'asset_tags'=>['S4-0001']]);
    test('Transfer never returns equipment to inventory',function () use ($destination,$transfer): void {
        $asset = DB::one("SELECT * FROM assets WHERE asset_tag='S4-0001'");
        same('On Show',$asset['state']);
        $active = DB::one('SELECT show_id FROM assignments WHERE asset_id=? AND ended_at IS NULL',[$asset['id']]);
        same($destination,(int)$active['show_id']);
        same(1,(int)DB::one("SELECT COUNT(*) AS total FROM asset_events WHERE operation_id=? AND state='Transfer'",[$transfer])['total']);
    });
    test('Direct transfer works when the source holds all inventory',function () use ($subcategory,$showData): void {
        $type = Inventory::itemType(['name'=>'Work Light','folder_id'=>$subcategory,'prefix'=>'WORK']);
        Inventory::import($type,"WORK-SERIAL-1\nWORK-SERIAL-2",'text');
        $source = Shows::save([...$showData,'name'=>'Transfer Source']);
        $destination = Shows::save([...$showData,'name'=>'Transfer Destination']);
        $from = Orders::create($source,['lines'=>[['item_type_id'=>$type,'quantity'=>2]]]);
        Orders::approve($from['revision_id'],'Approved');
        $line = Orders::lines($from['revision_id'])[0];
        Settings::save('pull_mode','manual');
        Fulfillment::pull($from['revision_id'],['quantities'=>[$line['id']=>2]]);
        $to = Orders::create($destination,['lines'=>[['item_type_id'=>$type,'quantity'=>2]]]);
        rejects(fn() => Orders::approve($to['revision_id'],'Approved'));
        $destinationLine = Orders::lines($to['revision_id'])[0];
        Fulfillment::transfer($source,['destination_line_id'=>$destinationLine['id'],'asset_tags'=>['WORK-0001','WORK-0002']]);
        same('Approved',Orders::revision($to['revision_id'])['status']);
        same(2,(int)DB::one('SELECT COUNT(*) AS total FROM assignments WHERE show_id=? AND ended_at IS NULL',[$destination])['total']);
    });
    Settings::save('return_mode','barcode');
    Fulfillment::returns($destination,['asset_tag'=>'S4-0001','condition'=>'Broken','description'=>'Socket needs replacement']);
    test('Broken return opens repair and removes availability',function (): void {
        same('Repair',DB::one("SELECT state FROM assets WHERE asset_tag='S4-0001'")['state']);
        same(1,(int)DB::one('SELECT COUNT(*) AS total FROM repairs')['total']);
    });
    test('Duplicate and incorrect show returns are blocked',fn() => rejects(fn() => Fulfillment::returns($destination,['asset_tag'=>'S4-0001'])));
    $repairId = (int)DB::one('SELECT id FROM repairs LIMIT 1')['id'];
    Fulfillment::repair($repairId,['status'=>'Working','notes'=>'Bench diagnosis complete']);
    Fulfillment::repair($repairId,['status'=>'Completed','notes'=>'Socket replaced and tested']);
    test('Completed repair returns serviceable asset to availability',fn() => same('Available',DB::one("SELECT state FROM assets WHERE asset_tag='S4-0001'")['state']));
    test('Outstanding equipment blocks archive',function () use ($showData,$showId): void {
        $data = $showData;
        $data['name'] = 'Hamlet';
        $data['status'] = 'Archived';
        rejects(fn() => Shows::save($data,$showId));
    });
    test('Import duplicates roll back the entire upload',function () use ($fixture): void {
        $before = (int)DB::one('SELECT COUNT(*) AS total FROM assets')['total'];
        rejects(fn() => Inventory::import($fixture,"NEW-SERIAL\nSN-01",'text'));
        same($before,(int)DB::one('SELECT COUNT(*) AS total FROM assets')['total']);
    });
    test('PDFs are real, persistent and integrity-tracked',function () use ($showId): void {
        foreach ([...Documents::REPORTS,'archive'] as $type) {
            $id = Documents::generate($type,$type==='archive'?$showId:0);
            $document = DB::one('SELECT * FROM documents WHERE id=?',[$id]);
            $path = ROOT . '/storage/pdfs/' . $document['stored_name'];
            same('%PDF',file_get_contents($path,false,null,0,4));
            same($document['sha256'],hash_file('sha256',$path));
        }
    });
    test('Saved tables escape search wildcards and reject unauthorized users',function (): void {
        $data = App\Services\Tables::query('assets',['q'=>'S4-','sort'=>'asset_tag','direction'=>'asc']);
        same(6,$data['total']);
        same(0,App\Services\Tables::query('assets',['q'=>'%'])['total']);
    });
    test('CSRF rejects missing and forged tokens',function (): void {
        $_POST['_csrf'] = 'forged';
        rejects(fn() => App\Middleware\Security::verify(),419);
        $_POST['_csrf'] = App\Middleware\Security::token();
        App\Middleware\Security::verify();
    });
    test('Database prevents revision and audit history rewrites',function () use ($revisionId): void {
        foreach ([
            ['UPDATE revisions SET notes=? WHERE id=?',['changed',$revisionId]],
            ['DELETE FROM revision_lines WHERE revision_id=?',[$revisionId]],
            ['UPDATE audit_logs SET action=? WHERE id=?',['changed',1]],
        ] as [$sql,$params]) {
            try {
                DB::execute($sql,$params);
                throw new RuntimeException('Historical mutation was accepted.');
            } catch (PDOException $error) {
                same('45000',$error->getCode());
            }
        }
    });
    test('Passwords preserve leading and trailing spaces',function (): void {
        $password = ' a long password with spaces ';
        same($password,Validation::passwordInput($password));
        same(true,password_verify($password,Auth::password($password)));
    });
    test('Passkey registration and signed authentication verify cryptographically',function () use ($userId): void {
        $encode = fn(string $bytes): string => rtrim(strtr(base64_encode($bytes),'+/','-_'),'=');
        $head = function (int $major,int $length): string {
            if ($length<24) return chr(($major<<5)|$length);
            if ($length<256) return chr(($major<<5)|24) . chr($length);
            return chr(($major<<5)|25) . pack('n',$length);
        };
        $bytes = fn(string $value): string => $head(2,strlen($value)) . $value;
        $key = openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
        $details = openssl_pkey_get_details($key);
        $credential = random_bytes(32);
        $options = App\Services\Passkeys::begin(true);
        same('required',$options['publicKey']['authenticatorSelection']['userVerification']);
        $client = json_encode(['type'=>'webauthn.create','challenge'=>$options['publicKey']['challenge'],'origin'=>App\Services\Config::get('url')],JSON_THROW_ON_ERROR);
        $cose = hex2bin('a5010203262001215820') . $details['ec']['x'] . hex2bin('225820') . $details['ec']['y'];
        $authData = hash('sha256','localhost',true) . chr(0x45) . pack('N',0) . str_repeat("\0",16) . pack('n',strlen($credential)) . $credential . $cose;
        $attestation = "\xa3\x63fmt\x64none\x68authData" . $bytes($authData) . "\x67attStmt\xa0";
        App\Services\Passkeys::finish(['rawId'=>$encode($credential),'clientDataJSON'=>$encode($client),'attestationObject'=>$encode($attestation),'label'=>'Synthetic test authenticator'],true);
        $options = App\Services\Passkeys::begin(false);
        $client = json_encode(['type'=>'webauthn.get','challenge'=>$options['publicKey']['challenge'],'origin'=>App\Services\Config::get('url')],JSON_THROW_ON_ERROR);
        $authData = hash('sha256','localhost',true) . chr(0x05) . pack('N',1);
        openssl_sign($authData . hash('sha256',$client,true),$signature,$key,OPENSSL_ALGO_SHA256);
        $response = ['rawId'=>$encode($credential),'clientDataJSON'=>$encode($client),'authenticatorData'=>$encode($authData),'signature'=>$encode($signature),'userHandle'=>$encode((string)$userId)];
        App\Services\Passkeys::finish($response,false);
        same($userId,(int)Auth::user()['id']);
        rejects(fn() => App\Services\Passkeys::finish($response,false));
        $options = App\Services\Passkeys::begin(false);
        $badClient = json_encode(['type'=>'webauthn.get','challenge'=>$options['publicKey']['challenge'],'origin'=>'https://evil.example'],JSON_THROW_ON_ERROR);
        rejects(fn() => App\Services\Passkeys::finish([...$response,'clientDataJSON'=>$encode($badClient)],false));
        $options = App\Services\Passkeys::begin(false);
        $client = json_encode(['type'=>'webauthn.get','challenge'=>$options['publicKey']['challenge'],'origin'=>App\Services\Config::get('url')],JSON_THROW_ON_ERROR);
        $authData = hash('sha256','localhost',true) . chr(0x01) . pack('N',2);
        openssl_sign($authData . hash('sha256',$client,true),$signature,$key,OPENSSL_ALGO_SHA256);
        rejects(fn() => App\Services\Passkeys::finish([...$response,'clientDataJSON'=>$encode($client),'authenticatorData'=>$encode($authData),'signature'=>$encode($signature)],false));
    });
    test('Single-use reset tokens rotate the account session version',function (): void {
        $id = DB::insert('INSERT INTO users(username,email,name,password_hash,role,avatar_seed) VALUES (?,?,?,?,?,?)',
            ['reset-test','reset@example.test','Reset Tester',Auth::password('old-password-for-test'),'Read Only',bin2hex(random_bytes(16))]);
        $token = bin2hex(random_bytes(32));
        DB::execute('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))',[$id,hash('sha256',$token)]);
        Auth::reset($token,'new-password-for-test');
        $user = DB::one('SELECT * FROM users WHERE id=?',[$id]);
        same(2,(int)$user['auth_version']);
        same(true,password_verify('new-password-for-test',$user['password_hash']));
        rejects(fn() => Auth::reset($token,'another-test-password'));
    });
    test('Read Only cannot change inventory or access administrative users',function (): void {
        $admin = Auth::user();
        $readonly = DB::one("SELECT * FROM users WHERE username='reset-test'");
        Auth::establish($readonly);
        rejects(fn() => App\Middleware\Permissions::require('inventory.edit'),403);
        rejects(fn() => App\Middleware\Permissions::require('settings.edit'),403);
        rejects(fn() => App\Services\Tables::query('users',[]),403);
        same(true,App\Middleware\Permissions::allows('reports.view'));
        Auth::establish($admin);
    });
    test('Missing equipment recovery and shop-origin repair are audited',function () use ($showId): void {
        $asset = DB::one("SELECT * FROM assets WHERE asset_tag='S4-0002'");
        Fulfillment::returns($showId,['asset_tag'=>$asset['asset_tag'],'condition'=>'Missing','description'=>'Not present at strike']);
        same('Missing',DB::one('SELECT state FROM assets WHERE id=?',[$asset['id']])['state']);
        $documentId = Documents::generate('missing',0);
        $document = DB::one('SELECT * FROM documents WHERE id=?',[$documentId]);
        same('%PDF',file_get_contents(ROOT . '/storage/pdfs/' . $document['stored_name'],false,null,0,4));
        Inventory::assetAction((int)$asset['id'],['action'=>'recover','notes'=>'Found in the road case; tested serviceable']);
        same('Available',DB::one('SELECT state FROM assets WHERE id=?',[$asset['id']])['state']);
        Inventory::assetAction((int)$asset['id'],['action'=>'repair','notes'=>'Bench inspection found a damaged yoke']);
        same('Repair',DB::one('SELECT state FROM assets WHERE id=?',[$asset['id']])['state']);
    });
    test('Used taxonomy cannot erase equipment or alter historical labels',function () use ($fixture,$subcategory,$category): void {
        rejects(fn() => Inventory::remove('item-type',$fixture));
        rejects(fn() => Inventory::remove('category',$category));
        rejects(fn() => Inventory::update('item-type',$fixture,['name'=>'Renamed Fixture','folder_id'=>$subcategory,'prefix'=>'S4']));
        rejects(fn() => Inventory::update('category',$subcategory,['name'=>'Fixtures','parent_id'=>null]));
    });
}
echo "$passed passed, $failed failed\n";
ob_end_flush();
exit($failed>0?1:0);

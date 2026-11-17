<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

$passed = 0;
function verify(bool $condition,string $message): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passed++;
    echo "PASS $message\n";
}

verify(str_starts_with(app()->version(),'12.'),'Laravel boots as the application framework');
verify(class_exists(Laravel\Fortify\Fortify::class),'Fortify is installed locally');
verify(class_exists(Livewire\Livewire::class),'Livewire is installed locally');
verify(config('session.driver')==='file' && config('cache.default')==='file' && config('queue.default')==='sync',
    'Shared hosting defaults do not require workers or Redis');
verify(config('session.http_only') && config('session.same_site')==='strict','Sessions retain strict cookie protections');
verify(config('hashing.driver')==='argon2id','Laravel hashing uses Argon2id');
verify(app('router')->getRoutes()->match(Illuminate\Http\Request::create('/shows/123'))->gatherMiddleware()
    === ['web','shop.permission:shows.view'],'Show routes use Laravel web and permission middleware');
verify(app('router')->getRoutes()->match(Illuminate\Http\Request::create('/livewire/update','POST'))->getName()!==null,
    'Livewire update endpoint is registered');
verify(hash_file('sha256',ROOT . '/database/legacy/001_initial.php')===
    'ec57363a9a6e4adfe715ac87a5235bb94aefef3061e67840cdd9cbb9e3548cc7',
    'Original schema bytes are preserved for existing installations');

if (in_array('--integration',$argv,true)) {
    if (!str_ends_with((string)App\Services\Config::get('database')['name'],'_testing')) {
        throw new RuntimeException('HTTP integration checks require the disposable _testing database.');
    }
    $base = rtrim((string)App\Services\Config::get('url'),'/');
    if (!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1'],true)) {
        throw new RuntimeException('HTTP integration checks only run against localhost.');
    }
    $cookies = [];
    $request = function (string $path,?array $body = null,array $headers = []) use ($base,&$cookies): array {
        $headers = array_merge(['Accept: application/json'], $headers);
        if ($cookies) {
            $headers[] = 'Cookie: ' . implode('; ',array_map(
                static fn($key,$value) => $key . '=' . $value,array_keys($cookies),array_values($cookies)
            ));
        }
        $options = ['http'=>[
            'method'=>$body===null?'GET':'POST','ignore_errors'=>true,'follow_location'=>0,
            'header'=>implode("\r\n",array_merge($headers,$body===null?[]:['Content-Type: application/json'])),
            'content'=>$body===null?'':json_encode($body,JSON_THROW_ON_ERROR),'timeout'=>30,
        ]];
        $content = file_get_contents($base . $path,false,stream_context_create($options));
        $responseHeaders = $http_response_header ?? [];
        preg_match('/\s(\d{3})\s/',$responseHeaders[0] ?? '',$match);
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$header,$cookie)) {
                $cookies[$cookie[1]] = $cookie[2];
            }
        }
        return ['status'=>(int)($match[1] ?? 0),'body'=>$content,'headers'=>$responseHeaders];
    };
    $login = $request('/login',null,['Accept: text/html']);
    verify($login['status']===200,'Login page is rendered by Laravel');
    preg_match('/name="csrf-token" content="([^"]+)"/',$login['body'],$token);
    $csrf = $token[1] ?? '';
    verify($csrf!=='','Login page exposes a session CSRF token');
    verify($request('/login',['username'=>'test-admin','password'=>'integration-password-123'])['status']===419,
        'Laravel rejects login submissions without CSRF');
    $auth = $request('/login',['_csrf'=>$csrf,'username'=>'test-admin','password'=>'integration-password-123']);
    verify($auth['status']===200 && (json_decode($auth['body'],true)['redirect'] ?? '')==='/dashboard',
        'Fortify authenticates the existing Argon2id shop account');
    $dashboard = $request('/dashboard',null,['Accept: text/html']);
    verify($dashboard['status']===200 && str_contains($dashboard['body'],'wire:snapshot'),
        'Authenticated dashboard mounts a real Livewire component');
    verify(str_contains($dashboard['body'],'/livewire/livewire.min.js'),'Livewire scripts are served locally');
    preg_match('/name="csrf-token" content="([^"]+)"/',$dashboard['body'],$token);
    $csrf = $token[1] ?? '';
    preg_match('/wire:snapshot="([^"]+)"/',$dashboard['body'],$snapshot);
    $snapshot = html_entity_decode($snapshot[1] ?? '',ENT_QUOTES|ENT_HTML5,'UTF-8');
    verify($snapshot!=='','Livewire state has a signed component snapshot');
    $pulse = $request('/livewire/update',[
        '_token'=>$csrf,'components'=>[['snapshot'=>$snapshot,'updates'=>[],
            'calls'=>[['path'=>'','method'=>'refresh','params'=>[]]]]],
    ]);
    verify($pulse['status']===200 && isset(json_decode($pulse['body'],true)['components'][0]['snapshot']),
        'Livewire polling executes through Laravel sessions and CSRF');
    $snapshot = json_decode($pulse['body'],true)['components'][0]['snapshot'];
    App\Models\Database::execute(
        'INSERT INTO audit_logs(action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?)',
        ['test.live_update','user',1,'{}','cli']
    );
    $change = $request('/livewire/update',[
        '_token'=>$csrf,'components'=>[['snapshot'=>$snapshot,'updates'=>[],
            'calls'=>[['path'=>'','method'=>'refresh','params'=>[]]]]],
    ]);
    $changed = json_decode($change['body'],true);
    verify($change['status']===200 && str_contains(json_encode($changed),'shop-updated'),
        'Livewire publishes a refresh event when another action changes shop data');
    $snapshot = $changed['components'][0]['snapshot'];
    $table = $request('/api/table?resource=assets&q=S4-');
    verify($table['status']===200 && (json_decode($table['body'],true)['total'] ?? 0)===6,
        'Inventory AJAX queries preserve existing filters');
    $workspace = $request('/shows/1',null,['X-Workspace: 1']);
    $data = json_decode($workspace['body'],true);
    verify($workspace['status']===200 && isset($data['html'],$data['csrf'],$data['key']),
        'Workspace navigation preserves its JSON contract');
    $document = App\Models\Database::one('SELECT id FROM documents ORDER BY id LIMIT 1');
    $pdf = $request('/documents/' . $document['id']);
    verify($pdf['status']===200 && str_starts_with($pdf['body'],'%PDF'),'Historical PDFs use Laravel binary responses');
    $logout = $request('/logout',['_csrf'=>$csrf]);
    verify($logout['status']===200,'Logout invalidates the Laravel session');
    verify($request('/api/table?resource=assets')['status']===401,'Logged-out sessions cannot read inventory APIs');
    $replay = $request('/livewire/update',[
        '_token'=>$csrf,'components'=>[['snapshot'=>$snapshot,'updates'=>[],
            'calls'=>[['path'=>'','method'=>'refresh','params'=>[]]]]],
    ]);
    verify(in_array($replay['status'],[401,419],true),'Old Livewire snapshots cannot bypass logout');
    $login = $request('/login',null,['Accept: text/html']);
    preg_match('/name="csrf-token" content="([^"]+)"/',$login['body'],$token);
    $readOnly = $request('/login',[
        '_csrf'=>$token[1],'username'=>'reset-test','password'=>'new-password-for-test',
    ]);
    verify($readOnly['status']===200,'Read Only accounts use the same Fortify login pipeline');
    verify($request('/settings')['status']===403 && $request('/api/table?resource=users')['status']===403,
        'Laravel Gates protect administrative pages and table APIs');
    App\Models\Database::execute("UPDATE users SET auth_version=auth_version+1 WHERE username='reset-test'");
    verify($request('/api/table?resource=assets')['status']===401,
        'Revoked account sessions are rejected by Laravel middleware');
}
echo "$passed framework checks passed.\n";

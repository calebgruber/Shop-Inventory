<?php
declare(strict_types=1);

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;

function csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App\Middleware\Security::token()) . '">';
}

function shop_redirect(string $path): never
{
    throw new HttpResponseException(redirect($path,303));
}

function shop_view(string $template,array $data = [],bool $layout = true,int $status = 200): never
{
    $content = view(str_replace('/','.',$template),$data)->render();
    if ($layout && request()->header('X-Workspace')==='1') {
        json_response([
            'html'=>$content,'title'=>$data['title'] ?? 'Theatre Shop',
            'label'=>$data['workspaceLabel'] ?? $data['title'] ?? 'Theatre Shop',
            'key'=>$data['workspaceKey'] ?? request()->path(),
            'csrf'=>App\Middleware\Security::token(),
        ]);
    }
    if ($layout) {
        $content = view('layout',$data + ['content'=>$content])->render();
    }
    throw new HttpResponseException(response($content,$status));
}

function json_response(array $data,int $status = 200): never
{
    throw new HttpResponseException(response()->json($data,$status));
}

function input(): array
{
    if (!str_starts_with(request()->header('Content-Type',''),'multipart/form-data') && strlen(request()->getContent())>1048576) {
        throw new App\Services\HttpException(413,'Request is too large.');
    }
    if (request()->isJson()) {
        $data = json_decode(request()->getContent(),true,32,JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new App\Services\HttpException(422,'Expected an object.');
        }
        return $data;
    }
    return request()->request->all();
}

function shop_route(string $method,string $path,callable|array $action,?string $permission = null): void
{
    $route = Route::match([$method],$path,$action);
    preg_match_all('/\{([a-z_]+)\}/',$path,$matches);
    foreach ($matches[1] as $parameter) {
        $route->whereNumber($parameter);
    }
    if ($permission!==null) {
        $route->middleware('shop.permission:' . $permission);
    }
}

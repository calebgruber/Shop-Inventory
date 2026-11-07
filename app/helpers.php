<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App\Middleware\Security::token()) . '">';
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function view(string $template, array $data = [], bool $layout = true): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/app/Views/' . $template . '.php';
    $content = ob_get_clean();
    if ($layout && ($_SERVER['HTTP_X_WORKSPACE'] ?? '') === '1') {
        json_response([
            'html' => $content, 'title' => $data['title'] ?? 'Theatre Shop',
            'label' => $data['workspaceLabel'] ?? $data['title'] ?? 'Theatre Shop',
            'key' => $data['workspaceKey'] ?? (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/dashboard'),
            'csrf' => App\Middleware\Security::token(),
        ]);
    }
    if ($layout) {
        require ROOT . '/app/Views/layout.php';
    } else {
        echo $content;
    }
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

function input(): array
{
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $body = file_get_contents('php://input');
        if (strlen($body) > 1048576) {
            throw new App\Services\HttpException(413, 'Request is too large.');
        }
        $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new App\Services\HttpException(422, 'Expected an object.');
        }
        return $data;
    }
    return $_POST;
}

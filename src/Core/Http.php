<?php
declare(strict_types=1);
namespace App\Core;

class Request {
    public function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
    public function uri(): string { return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; }
    public function input(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            return json_input();
        }
        return array_merge(json_input(), $_POST);
    }
    public function files(): array { return $_FILES; }
    public function query(string $key, mixed $default=null): mixed { return $_GET[$key] ?? $default; }
    public function header(string $name): ?string {
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        foreach ($headers as $k => $v) if (strcasecmp($k, $name) === 0) return $v;
        return null;
    }
    public function bearerToken(): ?string {
        $header = $this->header('Authorization');
        return ($header && preg_match('/Bearer\s+(.*)$/i', $header, $m)) ? trim($m[1]) : null;
    }
}

class Response {
    public static function json(mixed $data=null, int $status=200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($status === 204) {
            exit;
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    public static function ok(mixed $data=null, int $status=200): void { self::json($data, $status); }
    public static function noContent(): void { self::json(null, 204); }
    public static function error(string $message, int $status=400, array $errors=[]): void {
        self::json(['message'=>$message, 'errors'=>$errors], $status);
    }
}

class Router {
    private array $routes = [];
    public function add(string $method, string $path, array $handler, array $middlewares=[]): void { $this->routes[] = compact('method','path','handler','middlewares'); }
    public function dispatch(Request $request): void {
        $uri = rtrim($request->uri(), '/') ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) continue;
            $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', rtrim($route['path'], '/') ?: '/') . '$#';
            if (!preg_match($pattern, $uri, $m)) continue;
            $params = array_filter($m, fn($k)=>!is_int($k), ARRAY_FILTER_USE_KEY);
            foreach ($route['middlewares'] as $middleware) (new $middleware())->handle($request, $params);
            [$class, $method] = $route['handler'];
            (new $class())->$method($request, $params);
            return;
        }
        Response::error('Rota não encontrada', 404);
    }
}

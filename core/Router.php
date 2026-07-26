<?php

declare(strict_types=1);

namespace Core;

class Router
{
  private const DEFAULT_MIDDLEWARE_MAP = [
    'auth'    => \App\Middleware\AuthMiddleware::class,
    'teacher' => \App\Middleware\TeacherMiddleware::class,
    'admin'   => \App\Middleware\AdminMiddleware::class,
    'student' => \App\Middleware\StudentMiddleware::class,
  ];

  private array $routes = [];
  private array $middlewareMap;

  public function __construct(array $middlewareMap = [])
  {
    $this->middlewareMap = $middlewareMap + self::DEFAULT_MIDDLEWARE_MAP;
  }

  public function get(string $path, string $action, array $middleware = []): void
  {
    $this->add('GET', $path, $action, $middleware);
  }

  public function post(string $path, string $action, array $middleware = []): void
  {
    $this->add('POST', $path, $action, $middleware);
  }

  private function add(string $method, string $path, string $action, array $middleware = []): void
  {
    $this->routes[] = [
      'method'     => $method,
      'path'       => $path,
      'action'     => $action,
      'pattern'    => $this->toPattern($path),
      'middleware' => $middleware,
    ];
  }

  /**
   * Falha fechado: nome de middleware sem entrada no mapa aborta a
   * requisição em vez de deixá-la passar sem checagem.
   */
  private function runMiddleware(array $names): void
  {
    foreach ($names as $name) {
      $class = $this->middlewareMap[$name] ?? null;
      if ($class === null) {
        throw new \RuntimeException("Middleware desconhecido: {$name}");
      }
      $class::handle();
    }
  }

  public function dispatch(): void
  {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri    = app_request_path();

    foreach ($this->routes as $route) {
      if ($route['method'] !== $method) {
        continue;
      }

      if (!preg_match($route['pattern'], $uri, $matches)) {
        continue;
      }

      $this->runMiddleware($route['middleware']);

      $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

      [$class, $method] = explode('@', $route['action']);
      $fqcn = 'App\\Controllers\\' . $class;

      if (!class_exists($fqcn)) {
        $this->abort(500, "Controller não encontrado: {$fqcn}");
        return;
      }

      $controller = new $fqcn();

      if (!method_exists($controller, $method)) {
        $this->abort(500, "Método não encontrado: {$class}@{$method}");
        return;
      }

      $controller->$method(...array_values($params));
      return;
    }

    $this->abort(404, 'Página não encontrada.');
  }

  private function toPattern(string $path): string
  {
    $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
    return '#^' . $pattern . '$#';
  }

  private function abort(int $code, string $message): void
  {
    http_response_code($code);

    // Erros 5xx não devem revelar estrutura interna (classe/método); só loga.
    if ($code >= 500) {
      error_log("Router {$code}: {$message}");
      $message = 'Erro interno. Tente novamente mais tarde.';
    }

    echo "<h1>{$code}</h1><p>" . htmlspecialchars($message) . "</p>";
  }
}

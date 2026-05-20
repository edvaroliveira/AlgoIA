<?php

declare(strict_types=1);

namespace Core;

class Router
{
  private array $routes = [];

  public function get(string $path, string $action): void
  {
    $this->add('GET', $path, $action);
  }

  public function post(string $path, string $action): void
  {
    $this->add('POST', $path, $action);
  }

  private function add(string $method, string $path, string $action): void
  {
    $this->routes[] = [
      'method'  => $method,
      'path'    => $path,
      'action'  => $action,
      'pattern' => $this->toPattern($path),
    ];
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
    echo "<h1>{$code}</h1><p>" . htmlspecialchars($message) . "</p>";
  }
}

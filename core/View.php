<?php

declare(strict_types=1);

namespace Core;

class View
{
  /**
   * Renders $template inside $layout.
   * The layout receives $content (the rendered template) plus all $data keys.
   */
  public static function render(
    string $template,
    array  $data   = [],
    string $layout = 'layouts/main'
  ): void {
    // Render inner view
    $content = self::capture($template, $data);

    if ($layout) {
      self::capture($layout, array_merge($data, ['content' => $content]), true);
    } else {
      echo $content;
    }
  }

  /** Render a partial (no layout). */
  public static function partial(string $template, array $data = []): void
  {
    self::capture($template, $data, true);
  }

  /** Escape for HTML output. */
  public static function e(mixed $value): string
  {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  /** HTTP redirect + exit. */
  public static function redirect(string $url): never
  {
    header('Location: ' . $url);
    exit;
  }

  private static function capture(string $template, array $data, bool $echo = false): string
  {
    $file = ROOT_PATH . '/views/' . $template . '.php';

    if (!file_exists($file)) {
      throw new \RuntimeException("View not found: {$template}");
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $out = (string) ob_get_clean();

    if ($echo) {
      echo $out;
    }

    return $out;
  }
}

<?php

declare(strict_types=1);

namespace Core;

class View
{
  public static string $nonce = '';

  /** Título definido pela view ($pageTitle), repassado ao layout. */
  private static ?string $pageTitle = null;

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
    self::$pageTitle = null;
    $content = self::capture($template, $data);

    if ($layout) {
      $layoutData = ['content' => $content];
      if (self::$pageTitle !== null) {
        $layoutData['pageTitle'] = self::$pageTitle;
      }
      self::capture($layout, array_merge($data, $layoutData), true);
    } else {
      echo $content;
    }
  }

  /** Render a partial (no layout). */
  public static function partial(string $template, array $data = []): void
  {
    self::capture($template, $data, true);
  }

  /** Texto do <title>: "Página — AlgoIA" (ou só "AlgoIA"). */
  public static function documentTitle(?string $pageTitle): string
  {
    $pageTitle = trim((string) $pageTitle);
    return $pageTitle === '' || $pageTitle === 'AlgoIA' ? 'AlgoIA' : $pageTitle . ' — AlgoIA';
  }

  /** Escape for HTML output. */
  public static function e(mixed $value): string
  {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  /** HTTP redirect + exit. */
  public static function redirect(string $url): never
  {
    header('Location: ' . app_url($url));
    exit;
  }

  private static function capture(string $template, array $data, bool $echo = false): string
  {
    $base = ROOT_PATH . '/views/';
    $file = $base . $template . '.php';

    // Allowlist: o caminho resolvido precisa ficar dentro de views/ (evita LFI/path traversal).
    $real     = realpath($file);
    $realBase = realpath($base);
    if (
      $real === false
      || $realBase === false
      || !str_starts_with($real, $realBase . DIRECTORY_SEPARATOR)
    ) {
      throw new \RuntimeException("View not found: {$template}");
    }
    $file = $real;

    if (!isset($data['csp_nonce'])) {
      $data['csp_nonce'] = self::$nonce;
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $out = (string) ob_get_clean();
    if (!$echo && isset($pageTitle) && is_string($pageTitle)) {
      self::$pageTitle = $pageTitle;
    }

    if ($echo) {
      echo $out;
    }

    return $out;
  }
}

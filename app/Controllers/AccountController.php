<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AccountController
{
  /** Tamanho final do avatar (quadrado), em px. */
  private const SIZE = 256;

  /** Limite de upload aceito (2 MB). */
  private const MAX_BYTES = 2 * 1024 * 1024;

  /** Subpasta (relativa a public/assets/uploads) onde os avatares ficam. */
  private const SUBDIR = 'avatars';

  /** Limite operacional de uploads por janela (anti-abuso de CPU/disco). */
  private const UPLOAD_MAX = 12;
  private const UPLOAD_WINDOW_SECONDS = 600;

  private const ALLOWED_MIME = [
    'image/jpeg' => true,
    'image/png'  => true,
    'image/webp' => true,
  ];

  public function show(): void
  {

    $users = new User();
    $user  = $users->find((int) Auth::id()) ?: [];

    View::render('account/index', [
      'user'      => $user,
      'roleLabel' => Auth::isAdmin() ? 'Administrador' : (Auth::isTeacher() ? 'Docente' : 'Aluno'),
    ]);
  }

  public function uploadAvatar(): void
  {
    Request::validateCsrf();

    global $session;
    $userId = (int) Auth::id();

    if ($this->isUploadThrottled()) {
      $session->flash('error', 'Muitas trocas de foto em pouco tempo. Aguarde alguns minutos e tente novamente.');
      View::redirect('/conta');
    }

    $error = $this->validateUpload($_FILES['avatar'] ?? null);
    if ($error !== null) {
      $session->flash('error', $error);
      View::redirect('/conta');
    }

    try {
      $relativePath = $this->processAndStore($_FILES['avatar']['tmp_name']);
    } catch (\Throwable $e) {
      error_log('avatar upload failed: ' . $e->getMessage());
      $session->flash('error', 'Não foi possível processar a imagem enviada. Tente outra foto.');
      View::redirect('/conta');
    }

    $users = new User();
    $previous = $users->find($userId)['avatar_path'] ?? null;

    $users->updateAvatar($userId, $relativePath);
    Auth::setAvatar($relativePath);
    $this->deleteStoredFile($previous);

    AuditService::record('account.avatar.update', 'user', $userId, [
      'has_previous' => $previous !== null,
    ]);

    $session->flash('success', 'Foto de perfil atualizada.');
    View::redirect('/conta');
  }

  public function deleteAvatar(): void
  {
    Request::validateCsrf();

    global $session;
    $userId = (int) Auth::id();

    $users = new User();
    $previous = $users->find($userId)['avatar_path'] ?? null;

    $users->updateAvatar($userId, null);
    Auth::setAvatar(null);
    $this->deleteStoredFile($previous);

    if ($previous !== null) {
      AuditService::record('account.avatar.remove', 'user', $userId, []);
      $session->flash('success', 'Foto de perfil removida.');
    }

    View::redirect('/conta');
  }

  /** Retorna mensagem de erro ou null se o upload for válido. */
  private function validateUpload(?array $file): ?string
  {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      return 'Selecione uma imagem para enviar.';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      return 'Falha no envio do arquivo. Tente novamente.';
    }

    if (!is_uploaded_file($file['tmp_name'])) {
      return 'Upload inválido.';
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_BYTES) {
      return 'A imagem deve ter no máximo 2 MB.';
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);
    if (!isset(self::ALLOWED_MIME[$mime])) {
      return 'Formato não suportado. Envie JPG, PNG ou WebP.';
    }

    $dimensions = @getimagesize($file['tmp_name']);
    if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
      return 'Arquivo de imagem inválido.';
    }
    if ($dimensions[0] > 8000 || $dimensions[1] > 8000) {
      return 'Imagem muito grande em dimensões. Use até 8000px por lado.';
    }

    return null;
  }

  /**
   * Reencoda a imagem: recorte central quadrado → SIZE×SIZE → JPEG.
   * Descarta metadados (EXIF) e qualquer payload embutido. Retorna o caminho relativo.
   */
  private function processAndStore(string $tmpPath): string
  {
    $data = file_get_contents($tmpPath);
    if ($data === false) {
      throw new \RuntimeException('Falha ao ler o arquivo temporário.');
    }

    $src = @imagecreatefromstring($data);
    if ($src === false) {
      throw new \RuntimeException('GD não conseguiu decodificar a imagem.');
    }

    try {
      $width  = imagesx($src);
      $height = imagesy($src);
      $side   = min($width, $height);
      $srcX   = (int) (($width - $side) / 2);
      $srcY   = (int) (($height - $side) / 2);

      $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
      imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);

      $dir = $this->storageDir();
      if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new \RuntimeException('Não foi possível criar o diretório de avatares.');
      }

      $filename = bin2hex(random_bytes(16)) . '.jpg';
      $absolute = $dir . '/' . $filename;

      if (!imagejpeg($dst, $absolute, 85)) {
        throw new \RuntimeException('Falha ao gravar o JPEG do avatar.');
      }
      @chmod($absolute, 0644);

      return self::SUBDIR . '/' . $filename;
    } finally {
      imagedestroy($src);
      if (isset($dst) && $dst instanceof \GdImage) {
        imagedestroy($dst);
      }
    }
  }

  /** Throttle por sessão: no máximo UPLOAD_MAX uploads na janela. */
  private function isUploadThrottled(): bool
  {
    global $session;
    $now  = time();
    $hits = array_values(array_filter(
      (array) $session->get('avatar_upload_hits', []),
      static fn($t): bool => ($now - (int) $t) < self::UPLOAD_WINDOW_SECONDS
    ));

    if (count($hits) >= self::UPLOAD_MAX) {
      $session->set('avatar_upload_hits', $hits);
      return true;
    }

    $hits[] = $now;
    $session->set('avatar_upload_hits', $hits);
    return false;
  }

  private function storageDir(): string
  {
    return ROOT_PATH . '/public/assets/uploads/' . self::SUBDIR;
  }

  /** Remove com segurança um arquivo de avatar gerado por nós. */
  private function deleteStoredFile(?string $relativePath): void
  {
    if ($relativePath === null || $relativePath === '') {
      return;
    }

    // Aceita apenas o padrão que nós geramos: avatars/<hex>.jpg
    if (!preg_match('#^' . preg_quote(self::SUBDIR, '#') . '/[a-f0-9]{32}\.jpg$#', $relativePath)) {
      return;
    }

    $absolute = ROOT_PATH . '/public/assets/uploads/' . $relativePath;
    if (is_file($absolute)) {
      @unlink($absolute);
    }
  }
}

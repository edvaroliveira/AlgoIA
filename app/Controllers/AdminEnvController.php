<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\EnvFile;
use App\Services\EnvSettings;
use Core\Auth;
use Core\Request;
use Core\View;

/**
 * Configurações do ambiente (só Admin): edita o .env pela interface.
 * Salvar exige a senha do Admin; cada grupo é testado antes de gravar;
 * segredos nunca voltam para a tela; auditoria registra só os nomes das chaves.
 */
class AdminEnvController extends AdminBaseController
{
  /** GET /admin/configuracoes */
  public function index(): void
  {
    $file = new EnvFile(EnvSettings::filePath());
    $values = $file->read();

    $overridden = [];
    foreach (EnvSettings::GROUPS as $group) {
      foreach (array_keys($group['fields']) as $key) {
        $live = getenv($key);
        if ($live !== false && array_key_exists($key, $values) && $live !== $values[$key]) {
          $overridden[] = $key;
        }
      }
    }

    View::render('admin/settings', [
      'groups'     => EnvSettings::GROUPS,
      'values'     => $values,
      'writable'   => $file->isWritable(),
      'overridden' => $overridden,
    ]);
  }

  /** POST /admin/configuracoes/{group} */
  public function update(string $group): void
  {
    Request::validateCsrf();
    global $session;
    $back = '/admin/configuracoes#grupo-' . preg_replace('/[^a-z]/', '', $group);

    if (!array_key_exists($group, EnvSettings::GROUPS)) {
      $session->flash('error', 'Grupo de configuração desconhecido.');
      View::redirect('/admin/configuracoes');
    }

    $admin = $this->users->find((int) Auth::id());
    if (!$admin || !password_verify((string) Request::post('admin_password', ''), (string) $admin['password_hash'])) {
      AuditService::record('admin.env.update_denied', 'settings', null, ['group' => $group]);
      $session->flash('error', 'Senha do Admin incorreta. Nada foi alterado.');
      View::redirect($back);
    }

    $file = new EnvFile(EnvSettings::filePath());
    if (!$file->isWritable()) {
      $session->flash('error', 'O arquivo .env não pode ser gravado pelo servidor (permissões). Nada foi alterado.');
      View::redirect($back);
    }

    $settings = new EnvSettings($file);
    try {
      $values = $settings->validate($group, $_POST);
      $settings->test($group, $values);
    } catch (\InvalidArgumentException | \RuntimeException $e) {
      $session->flash('error', $e->getMessage());
      View::redirect($back);
    }

    $current = $file->read();
    $changed = array_keys(array_filter($values, static fn(string $v, string $k): bool => ($current[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
    if ($changed === []) {
      $session->flash('success', 'Nenhuma alteração.');
      View::redirect($back);
    }

    try {
      $file->update(array_intersect_key($values, array_flip($changed)));
    } catch (\Throwable $e) {
      error_log('env update failed: ' . $e->getMessage());
      $session->flash('error', 'Não foi possível gravar o .env: ' . $e->getMessage());
      View::redirect($back);
    }

    AuditService::record('admin.env.update', 'settings', null, ['group' => $group, 'keys' => $changed]);
    $session->flash('success', EnvSettings::GROUPS[$group]['title'] . ': configuração salva. Vale a partir da próxima página e da próxima execução do cron.');
    View::redirect($back);
  }
}

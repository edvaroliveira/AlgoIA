<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Services\AuditService;
use Core\Request;
use Core\View;

/** Categorias e subcategorias (só Admin): criar, renomear, desativar. Sem exclusão. */
class AdminCategoryController extends AdminBaseController
{
  /** GET /admin/categorias */
  public function index(): void
  {
    View::render('admin/categories', ['tree' => (new Category())->tree()]);
  }

  /** POST /admin/categorias */
  public function store(): void
  {
    Request::validateCsrf();
    $this->run(static function (Category $c): array {
      $id = $c->createCategory(Request::str('name'));
      return ['admin.category.create', $id, 'Categoria criada.'];
    });
  }

  /** POST /admin/categorias/{id}/subcategorias */
  public function storeSub(string $id): void
  {
    Request::validateCsrf();
    $this->run(static function (Category $c) use ($id): array {
      $subId = $c->createSub((int) $id, Request::str('name'));
      return ['admin.category.sub_create', $subId, 'Subcategoria criada.'];
    });
  }

  /** POST /admin/categorias/{id}/renomear e /admin/subcategorias/{id}/renomear */
  public function renameCategory(string $id): void
  {
    $this->renameKind('category', (int) $id);
  }

  public function renameSub(string $id): void
  {
    $this->renameKind('sub', (int) $id);
  }

  /** POST /admin/categorias/{id}/status e /admin/subcategorias/{id}/status */
  public function statusCategory(string $id): void
  {
    $this->statusKind('category', (int) $id);
  }

  public function statusSub(string $id): void
  {
    $this->statusKind('sub', (int) $id);
  }

  private function renameKind(string $kind, int $id): void
  {
    Request::validateCsrf();
    $this->run(static function (Category $c) use ($kind, $id): array {
      $c->rename($kind, $id, Request::str('name'));
      return [$kind === 'category' ? 'admin.category.rename' : 'admin.category.sub_rename', $id, 'Nome atualizado.'];
    });
  }

  private function statusKind(string $kind, int $id): void
  {
    Request::validateCsrf();
    $active = (string) Request::post('active', '1') === '1';
    $this->run(static function (Category $c) use ($kind, $id, $active): array {
      if (!$c->setActive($kind, $id, $active)) {
        throw new \InvalidArgumentException('Item não encontrado.');
      }
      $action = ($kind === 'category' ? 'admin.category.' : 'admin.category.sub_') . ($active ? 'activate' : 'deactivate');
      return [$action, $id, $active ? 'Reativado.' : 'Desativado: some das escolhas, mas as classificações existentes continuam.'];
    });
  }

  /** @param callable(Category): array{0: string, 1: int, 2: string} $action */
  private function run(callable $action): never
  {
    global $session;
    try {
      [$audit, $entityId, $message] = $action(new Category());
      AuditService::record($audit, 'category', $entityId, []);
      $session->flash('success', $message);
    } catch (\InvalidArgumentException $e) {
      $session->flash('error', $e->getMessage());
    }
    View::redirect('/admin/categorias');
  }
}

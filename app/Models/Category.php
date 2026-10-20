<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Categoria e subcategoria dos Exercícios e do Banco de exercícios.
 * Subcategoria = linha de bank_themes (antes chamada "Tema"). O Admin mantém a
 * lista; Docente e Admin só escolhem entre as ativas. Classificação = uma
 * categoria + uma ou mais subcategorias dela.
 */
class Category extends Model
{
  public const NAME_MAX = 80;
  public const SUB_NAME_MAX = 60;

  /** Alvos de classificação: tabela do item, tabela de ligação e coluna de ligação. */
  private const TARGETS = [
    'exercise' => ['exercises', 'exercise_themes', 'exercise_id'],
    'bank'     => ['bank_exercises', 'bank_exercise_themes', 'bank_exercise_id'],
  ];

  protected string $table = 'categories';

  /**
   * Árvore categoria → subcategorias.
   *
   * @return array<int, array{id: int, name: string, active: bool, subs: array<int, array{id: int, name: string, active: bool}>}>
   */
  public function tree(bool $onlyActive = false): array
  {
    $categories = $this->db->fetchAll(
      "SELECT id, name, active FROM categories" . ($onlyActive ? " WHERE active = 1" : "") . " ORDER BY name"
    );
    $subs = $this->db->fetchAll(
      "SELECT id, category_id, name, active FROM bank_themes WHERE category_id IS NOT NULL"
        . ($onlyActive ? " AND active = 1" : "") . " ORDER BY name"
    );
    $byCategory = [];
    foreach ($subs as $sub) {
      $byCategory[(int) $sub['category_id']][] = ['id' => (int) $sub['id'], 'name' => (string) $sub['name'], 'active' => (bool) $sub['active']];
    }

    return array_map(static fn(array $c): array => [
      'id'     => (int) $c['id'],
      'name'   => (string) $c['name'],
      'active' => (bool) $c['active'],
      'subs'   => $byCategory[(int) $c['id']] ?? [],
    ], $categories);
  }

  /** @throws \InvalidArgumentException */
  public function createCategory(string $name): int
  {
    $name = $this->cleanName($name, self::NAME_MAX);
    if ($this->db->fetchOne("SELECT id FROM categories WHERE name = ?", [$name])) {
      throw new \InvalidArgumentException('Já existe uma categoria com esse nome.');
    }
    return $this->db->insert("INSERT INTO categories (name) VALUES (?)", [$name]);
  }

  /** @throws \InvalidArgumentException */
  public function createSub(int $categoryId, string $name): int
  {
    $name = $this->cleanName($name, self::SUB_NAME_MAX);
    if (!$this->db->fetchOne("SELECT id FROM categories WHERE id = ?", [$categoryId])) {
      throw new \InvalidArgumentException('Categoria não encontrada.');
    }
    if ($this->db->fetchOne("SELECT id FROM bank_themes WHERE category_id = ? AND name = ?", [$categoryId, $name])) {
      throw new \InvalidArgumentException('Já existe uma subcategoria com esse nome nesta categoria.');
    }
    return $this->db->insert("INSERT INTO bank_themes (category_id, name) VALUES (?, ?)", [$categoryId, $name]);
  }

  /** @throws \InvalidArgumentException */
  public function rename(string $kind, int $id, string $name): void
  {
    if ($kind === 'category') {
      $name = $this->cleanName($name, self::NAME_MAX);
      if ($this->db->fetchOne("SELECT id FROM categories WHERE name = ? AND id <> ?", [$name, $id])) {
        throw new \InvalidArgumentException('Já existe uma categoria com esse nome.');
      }
      $this->db->execute("UPDATE categories SET name = ? WHERE id = ?", [$name, $id]);
      return;
    }
    $name = $this->cleanName($name, self::SUB_NAME_MAX);
    $sub = $this->db->fetchOne("SELECT category_id FROM bank_themes WHERE id = ?", [$id]);
    if (!$sub) {
      throw new \InvalidArgumentException('Subcategoria não encontrada.');
    }
    if ($this->db->fetchOne("SELECT id FROM bank_themes WHERE category_id = ? AND name = ? AND id <> ?", [(int) $sub['category_id'], $name, $id])) {
      throw new \InvalidArgumentException('Já existe uma subcategoria com esse nome nesta categoria.');
    }
    $this->db->execute("UPDATE bank_themes SET name = ? WHERE id = ?", [$name, $id]);
  }

  public function setActive(string $kind, int $id, bool $active): bool
  {
    $table = $kind === 'category' ? 'categories' : 'bank_themes';
    return $this->db->execute("UPDATE {$table} SET active = ? WHERE id = ?", [$active ? 1 : 0, $id]) > 0
      || (bool) $this->db->fetchOne("SELECT id FROM {$table} WHERE id = ?", [$id]);
  }

  /**
   * Valida a escolha (categoria ativa + subcategorias ativas dela) e devolve [categoria, subcategorias].
   *
   * @throws \InvalidArgumentException mensagem para a tela
   * @return array{0: int, 1: int[]}
   */
  public function validateSelection(mixed $categoryId, mixed $themeIds): array
  {
    $categoryId = (int) $categoryId;
    $themeIds = array_values(array_unique(array_filter(array_map('intval', is_array($themeIds) ? $themeIds : []), static fn(int $id): bool => $id > 0)));

    if ($categoryId <= 0 || !$this->db->fetchOne("SELECT id FROM categories WHERE id = ? AND active = 1", [$categoryId])) {
      throw new \InvalidArgumentException('Escolha uma categoria.');
    }
    if ($themeIds === []) {
      throw new \InvalidArgumentException('Escolha pelo menos uma subcategoria.');
    }
    $placeholders = implode(',', array_fill(0, count($themeIds), '?'));
    $valid = (int) ($this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM bank_themes WHERE id IN ({$placeholders}) AND category_id = ? AND active = 1",
      array_merge($themeIds, [$categoryId])
    )['c'] ?? 0);
    if ($valid !== count($themeIds)) {
      throw new \InvalidArgumentException('As subcategorias precisam ser ativas e pertencer à categoria escolhida.');
    }
    return [$categoryId, $themeIds];
  }

  /** @return array{category_id: ?int, category_name: ?string, themes: array<int, array{id: int, name: string}>} */
  public function classificationOf(string $target, int $id): array
  {
    [$table, $link, $column] = self::TARGETS[$target];
    $row = $this->db->fetchOne(
      "SELECT t.category_id, c.name AS category_name FROM {$table} t LEFT JOIN categories c ON c.id = t.category_id WHERE t.id = ?",
      [$id]
    ) ?: [];
    $themes = $this->db->fetchAll(
      "SELECT bt.id, bt.name FROM {$link} l JOIN bank_themes bt ON bt.id = l.theme_id WHERE l.{$column} = ? ORDER BY bt.name",
      [$id]
    );
    return [
      'category_id'   => isset($row['category_id']) ? (int) $row['category_id'] : null,
      'category_name' => $row['category_name'] ?? null,
      'themes'        => array_map(static fn(array $t): array => ['id' => (int) $t['id'], 'name' => (string) $t['name']], $themes),
    ];
  }

  /** Substitui a classificação (já validada). */
  public function classify(string $target, int $id, int $categoryId, array $themeIds): void
  {
    [$table, $link, $column] = self::TARGETS[$target];
    $this->db->execute("UPDATE {$table} SET category_id = ? WHERE id = ?", [$categoryId, $id]);
    $this->db->execute("DELETE FROM {$link} WHERE {$column} = ?", [$id]);
    foreach ($themeIds as $themeId) {
      $this->db->execute("INSERT IGNORE INTO {$link} ({$column}, theme_id) VALUES (?, ?)", [$id, (int) $themeId]);
    }
  }

  /** Acrescenta subcategorias sem tirar as existentes. */
  public function addThemes(string $target, int $id, array $themeIds): void
  {
    [, $link, $column] = self::TARGETS[$target];
    foreach ($themeIds as $themeId) {
      $this->db->execute("INSERT IGNORE INTO {$link} ({$column}, theme_id) VALUES (?, ?)", [$id, (int) $themeId]);
    }
  }

  public function isClassified(string $target, int $id): bool
  {
    $c = $this->classificationOf($target, $id);
    return $c['category_id'] !== null && $c['themes'] !== [];
  }

  /**
   * Rótulos "Categoria → sub1, sub2" de vários Exercícios (listas).
   *
   * @param int[] $ids
   * @return array<int, string>
   */
  public function labelsFor(string $target, array $ids): array
  {
    $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
    if ($ids === []) {
      return [];
    }
    [$table, $link, $column] = self::TARGETS[$target];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = $this->db->fetchAll(
      "SELECT t.id, c.name AS category,
              (SELECT GROUP_CONCAT(bt.name ORDER BY bt.name SEPARATOR ', ')
                 FROM {$link} l JOIN bank_themes bt ON bt.id = l.theme_id WHERE l.{$column} = t.id) AS subs
             FROM {$table} t
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.id IN ({$placeholders})",
      $ids
    );
    $labels = [];
    foreach ($rows as $row) {
      $labels[(int) $row['id']] = $row['category'] !== null
        ? $row['category'] . ($row['subs'] ? ' → ' . $row['subs'] : '')
        : '';
    }
    return $labels;
  }

  /** @throws \InvalidArgumentException */
  private function cleanName(string $name, int $max): string
  {
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '' || mb_strlen($name) > $max) {
      throw new \InvalidArgumentException("Informe um nome de até {$max} caracteres.");
    }
    return $name;
  }
}

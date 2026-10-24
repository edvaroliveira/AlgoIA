<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Banco de exercícios: acervo institucional curado pelo Admin.
 *
 * Cada item é um snapshot (título, descrição e Questões) de um Exercício
 * Concluído ou Publicado, independente da origem. Estados: suggested (Sugestão
 * de um Docente), active (no banco), rejected (recusado, com motivo), archived.
 */
class BankExercise extends Model
{
  public const STATUS_SUGGESTED = 'suggested';
  public const STATUS_ACTIVE    = 'active';
  public const STATUS_REJECTED  = 'rejected';
  public const STATUS_ARCHIVED  = 'archived';

  public const PER_PAGE = 10;

  protected string $table = 'bank_exercises';

  /**
   * Resultado da última incorporação (aprovação ou inclusão direta): quantas
   * Questões entraram e quais ficaram de fora por similaridade com o banco.
   *
   * @var array{total: int, kept: int, block: float, excluded: list<array<string, mixed>>}
   */
  private array $lastIncorporation = ['total' => 0, 'kept' => 0, 'block' => 0.0, 'excluded' => []];

  /** @return array{total: int, kept: int, block: float, excluded: list<array<string, mixed>>} */
  public function lastIncorporation(): array
  {
    return $this->lastIncorporation;
  }

  /**
   * Cria o item a partir de um Exercício: Sugestão do Docente (suggested) ou
   * inclusão direta do Admin (active). Retorna o id do item.
   *
   * @throws \RuntimeException se o Exercício não puder entrar no banco.
   */
  public function createFromExercise(int $exerciseId, string $status, ?int $reviewedBy = null): int
  {
    $this->db->beginTransaction();

    try {
      $exercise = $this->db->fetchOne(
        "SELECT id, teacher_id, title, description, status, admin_review_status, category_id
               FROM exercises WHERE id = ? FOR UPDATE",
        [$exerciseId]
      );
      if (!$exercise || !in_array($exercise['status'], [Exercise::STATUS_READY, Exercise::STATUS_ACTIVE], true)) {
        throw new \RuntimeException('Só Exercícios Concluídos ou Publicados podem entrar no banco.');
      }
      if (($exercise['admin_review_status'] ?? 'approved') !== 'approved') {
        throw new \RuntimeException('Exercícios Sinalizados ou Bloqueados pela Moderação não podem entrar no banco.');
      }

      $open = $this->db->fetchOne(
        "SELECT id FROM bank_exercises WHERE source_exercise_id = ? AND status IN (?, ?) LIMIT 1",
        [$exerciseId, self::STATUS_SUGGESTED, self::STATUS_ACTIVE]
      );
      if ($open) {
        throw new \RuntimeException('Este Exercício já está no banco ou aguardando a curadoria.');
      }

      $questions = $this->db->fetchAll(
        "SELECT id, text, expected_answer_hint, max_score, python_solution, python_solution_status, python_solution_source FROM questions
               WHERE exercise_id = ? AND admin_review_status <> 'blocked'
               ORDER BY order_index, id",
        [$exerciseId]
      );
      if ($questions === []) {
        throw new \RuntimeException('O Exercício não tem Questões que possam entrar no banco.');
      }

      $isDirect = $status === self::STATUS_ACTIVE;
      // Inclusão direta já é incorporação: Questões acima do Limite de similaridade ficam de fora.
      // A Sugestão guarda todas; o Admin decide na aprovação.
      $total = count($questions);
      $excluded = [];
      $block = 0.0;
      if ($isDirect) {
        [$questions, $excluded, $block] = $this->withoutSimilar($questions, null);
      }
      $bankId = $this->db->insert(
        "INSERT INTO bank_exercises (title, description, source_exercise_id, source_teacher_id, status, reviewed_by, reviewed_at)
               VALUES (?, ?, ?, ?, ?, ?, " . ($isDirect ? 'NOW()' : 'NULL') . ")",
        [$exercise['title'], $exercise['description'], $exerciseId, (int) $exercise['teacher_id'], $status, $isDirect ? $reviewedBy : null]
      );

      $order = 1;
      foreach ($questions as $question) {
        $this->db->insert(
          "INSERT INTO bank_questions (bank_exercise_id, text, expected_answer_hint, max_score, order_index,
                                       python_solution, python_solution_status, python_solution_source, python_solution_updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
          [$bankId, $question['text'], $question['expected_answer_hint'], $question['max_score'], $order++, ...self::approvedPython($question)]
        );
      }

      // A classificação do Exercício vai junto (o Admin pode ajustar na curadoria).
      if ($exercise['category_id'] !== null) {
        $this->db->execute("UPDATE bank_exercises SET category_id = ? WHERE id = ?", [(int) $exercise['category_id'], $bankId]);
        $this->db->execute(
          "INSERT IGNORE INTO bank_exercise_themes (bank_exercise_id, theme_id)
                 SELECT ?, theme_id FROM exercise_themes WHERE exercise_id = ?",
          [$bankId, $exerciseId]
        );
      }

      $this->db->commit();
      $this->lastIncorporation = ['total' => $total, 'kept' => count($questions), 'block' => $block, 'excluded' => $excluded];
      return $bankId;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }
      throw $e;
    }
  }

  /**
   * Aprova uma Sugestão: vai para o banco com a classificação escolhida (já validada).
   * Questões acima do Limite de similaridade com o banco são retiradas do item;
   * as demais entram, renumeradas. Tudo na mesma transação.
   *
   * @return bool false se a Sugestão não existe ou já foi revisada.
   * @throws \RuntimeException se todas as Questões estiverem acima do limite.
   */
  public function approve(int $id, int $adminId, int $categoryId, array $themeIds): bool
  {
    $this->db->beginTransaction();

    try {
      $item = $this->db->fetchOne("SELECT id FROM bank_exercises WHERE id = ? AND status = ? FOR UPDATE", [$id, self::STATUS_SUGGESTED]);
      if (!$item) {
        $this->db->rollback();
        return false;
      }

      $questions = $this->db->fetchAll(
        "SELECT id, text FROM bank_questions WHERE bank_exercise_id = ? ORDER BY order_index, id",
        [$id]
      );
      [$kept, $excluded, $block] = $this->withoutSimilar($questions, $id);

      foreach ($excluded as $question) {
        $this->db->execute("DELETE FROM bank_questions WHERE id = ?", [(int) $question['id']]);
      }
      $order = 1;
      foreach ($kept as $question) {
        $this->db->execute("UPDATE bank_questions SET order_index = ? WHERE id = ?", [$order++, (int) $question['id']]);
      }

      $this->db->execute(
        "UPDATE bank_exercises SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = NULL WHERE id = ?",
        [self::STATUS_ACTIVE, $adminId, $id]
      );
      (new Category($this->db))->classify('bank', $id, $categoryId, $themeIds);

      $this->db->commit();
      $this->lastIncorporation = ['total' => count($questions), 'kept' => count($kept), 'block' => $block, 'excluded' => $excluded];
      return true;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }
      throw $e;
    }
  }

  /**
   * Separa as Questões que podem entrar das que estão acima do Limite de similaridade.
   *
   * @param list<array<string, mixed>> $questions
   * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: float}
   * @throws \RuntimeException se nenhuma Questão puder entrar.
   */
  private function withoutSimilar(array $questions, ?int $excludeBankExerciseId): array
  {
    $report = (new \App\Services\BankSimilarityService($this->db))->report($questions, $excludeBankExerciseId);
    $kept = [];
    $excluded = [];
    foreach ($questions as $i => $question) {
      $row = $report['questions'][$i];
      if (!$row['blocked']) {
        $kept[] = $question;
        continue;
      }
      $match = $row['matches'][0];
      $excluded[] = [
        'id'                => $question['id'] ?? null,
        'position'          => $i + 1,
        'pct'               => $row['pct'],
        'bank_question_id'  => (int) $match['bank_question_id'],
        'bank_exercise_id'  => (int) $match['bank_exercise_id'],
      ];
    }

    if ($kept === []) {
      $limit = rtrim(rtrim(number_format($report['block'], 1, ',', ''), '0'), ',');
      throw new \RuntimeException("Todas as Questões já existem no banco (similaridade ≥ {$limit}%).");
    }

    return [$kept, $excluded, $report['block']];
  }

  /** Recusa uma Sugestão, registrando o motivo para o Docente. */
  public function reject(int $id, int $adminId, string $note): bool
  {
    return $this->db->execute(
      "UPDATE bank_exercises SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
             WHERE id = ? AND status = ?",
      [self::STATUS_REJECTED, $adminId, $note, $id, self::STATUS_SUGGESTED]
    ) > 0;
  }

  /** Tira um item ativo do banco (deixa de ser listado e importado). */
  public function archive(int $id, int $adminId): bool
  {
    return $this->db->execute(
      "UPDATE bank_exercises SET status = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ? AND status = ?",
      [self::STATUS_ARCHIVED, $adminId, $id, self::STATUS_ACTIVE]
    ) > 0;
  }

  /**
   * Classifica o item por NOMES (uso do seed): cria a categoria e as subcategorias que faltarem.
   *
   * @return int[] ids das subcategorias
   */
  public function setThemes(int $id, array $themeNames, string $categoryName = 'Algoritmos'): array
  {
    $names = self::normalizeThemeNames($themeNames);
    $this->db->execute("INSERT IGNORE INTO categories (name) VALUES (?)", [$categoryName]);
    $categoryId = (int) ($this->db->fetchOne("SELECT id FROM categories WHERE name = ?", [$categoryName])['id'] ?? 0);
    $this->db->execute("UPDATE bank_exercises SET category_id = ? WHERE id = ?", [$categoryId, $id]);

    $ids = [];
    $this->db->execute("DELETE FROM bank_exercise_themes WHERE bank_exercise_id = ?", [$id]);
    foreach ($names as $name) {
      $this->db->execute("INSERT IGNORE INTO bank_themes (category_id, name) VALUES (?, ?)", [$categoryId, $name]);
      $theme = $this->db->fetchOne("SELECT id FROM bank_themes WHERE category_id = ? AND name = ?", [$categoryId, $name]);
      if ($theme) {
        $this->db->execute(
          "INSERT IGNORE INTO bank_exercise_themes (bank_exercise_id, theme_id) VALUES (?, ?)",
          [$id, (int) $theme['id']]
        );
        $ids[] = (int) $theme['id'];
      }
    }
    return $ids;
  }

  /** "Vetores, Laços" → ['Vetores', 'Laços'] (sem repetição, até 60 caracteres cada). */
  public static function normalizeThemeNames(array|string $themes): array
  {
    $parts = is_array($themes) ? $themes : explode(',', $themes);
    $names = [];
    foreach ($parts as $part) {
      $name = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $part) ?? ''), 0, 60);
      if ($name !== '' && !in_array(mb_strtolower($name), array_map('mb_strtolower', $names), true)) {
        $names[] = $name;
      }
    }
    return $names;
  }

  public function find(int $id): array|false
  {
    return $this->db->fetchOne("SELECT * FROM bank_exercises WHERE id = ?", [$id]);
  }

  /** Itens ativos do banco, com filtro por texto (título, descrição, enunciado) e Tema. */
  public function listActive(string $search, string $theme, int $limit, int $offset, int $category = 0): array
  {
    [$where, $params] = $this->activeFilter($search, $theme, $category);
    $safeLimit = max(1, $limit);
    $safeOffset = max(0, $offset);

    $items = $this->db->fetchAll(
      "SELECT be.id, be.title, be.description, be.category_id,
              (SELECT c.name FROM categories c WHERE c.id = be.category_id) AS category_name,
              (SELECT COUNT(*) FROM bank_questions bq WHERE bq.bank_exercise_id = be.id) AS question_count,
              (SELECT COALESCE(SUM(bq.max_score), 0) FROM bank_questions bq WHERE bq.bank_exercise_id = be.id) AS total_score
             FROM bank_exercises be
             {$where}
             ORDER BY be.title, be.id
             LIMIT {$safeLimit} OFFSET {$safeOffset}",
      $params
    );

    return $this->attachDetails($items, 'bank_questions', 'bank_exercise_id');
  }

  public function countActive(string $search, string $theme, int $category = 0): int
  {
    [$where, $params] = $this->activeFilter($search, $theme, $category);
    $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM bank_exercises be {$where}", $params);
    return (int) ($row['c'] ?? 0);
  }

  /** Subcategorias com pelo menos um item ativo, com a categoria, para os filtros. */
  public function activeThemes(): array
  {
    return $this->db->fetchAll(
      "SELECT bt.name, COUNT(*) AS item_count, c.id AS category_id, COALESCE(c.name, 'Sem categoria') AS category_name
             FROM bank_themes bt
             LEFT JOIN categories c ON c.id = bt.category_id
             JOIN bank_exercise_themes bet ON bet.theme_id = bt.id
             JOIN bank_exercises be ON be.id = bet.bank_exercise_id AND be.status = ?
             GROUP BY bt.id, bt.name, c.id, c.name
             ORDER BY category_name, bt.name",
      [self::STATUS_ACTIVE]
    );
  }

  /** Exercícios do próprio Docente que podem servir de origem (aba "Meus exercícios"). */
  public function listOwnSources(int $teacherId, string $search, int $limit, int $offset): array
  {
    [$where, $params] = $this->ownFilter($teacherId, $search);
    $safeLimit = max(1, $limit);
    $safeOffset = max(0, $offset);

    $items = $this->db->fetchAll(
      "SELECT e.id, e.title, e.description,
              (SELECT COUNT(*) FROM questions q WHERE q.exercise_id = e.id AND q.admin_review_status <> 'blocked') AS question_count,
              (SELECT COALESCE(SUM(q.max_score), 0) FROM questions q WHERE q.exercise_id = e.id AND q.admin_review_status <> 'blocked') AS total_score
             FROM exercises e
             {$where}
             ORDER BY e.updated_at DESC, e.id DESC
             LIMIT {$safeLimit} OFFSET {$safeOffset}",
      $params
    );

    return $this->attachDetails($items, 'questions', 'exercise_id');
  }

  public function countOwnSources(int $teacherId, string $search): int
  {
    [$where, $params] = $this->ownFilter($teacherId, $search);
    $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM exercises e {$where}", $params);
    return (int) ($row['c'] ?? 0);
  }

  /** Situação do Exercício no banco, para o Docente (null = nunca sugerido). */
  public function latestForExercise(int $exerciseId): array|false
  {
    return $this->db->fetchOne(
      "SELECT id, status, review_note, reviewed_at FROM bank_exercises
             WHERE source_exercise_id = ? ORDER BY id DESC LIMIT 1",
      [$exerciseId]
    );
  }

  /** Itens por estado, para a curadoria do Admin. */
  public function listByStatus(string $status, int $limit, int $offset): array
  {
    $safeLimit = max(1, $limit);
    $safeOffset = max(0, $offset);

    $items = $this->db->fetchAll(
      "SELECT be.id, be.title, be.description, be.status, be.review_note, be.created_at, be.reviewed_at,
              be.source_exercise_id, teacher.name AS teacher_name, be.category_id,
              (SELECT c.name FROM categories c WHERE c.id = be.category_id) AS category_name,
              (SELECT COUNT(*) FROM bank_questions bq WHERE bq.bank_exercise_id = be.id) AS question_count
             FROM bank_exercises be
             LEFT JOIN users teacher ON teacher.id = be.source_teacher_id
             WHERE be.status = ?
             ORDER BY be.created_at DESC, be.id DESC
             LIMIT {$safeLimit} OFFSET {$safeOffset}",
      [$status]
    );

    return $this->attachDetails($items, 'bank_questions', 'bank_exercise_id');
  }

  public function countByStatus(string $status): int
  {
    $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM bank_exercises WHERE status = ?", [$status]);
    return (int) ($row['c'] ?? 0);
  }

  private function activeFilter(string $search, string $theme, int $category = 0): array
  {
    $where = ['be.status = ?'];
    $params = [self::STATUS_ACTIVE];

    if ($search !== '') {
      $like = '%' . $search . '%';
      $where[] = "(be.title LIKE ? OR be.description LIKE ?
                   OR EXISTS (SELECT 1 FROM bank_questions sq WHERE sq.bank_exercise_id = be.id AND sq.text LIKE ?))";
      array_push($params, $like, $like, $like);
    }
    if ($theme !== '') {
      $where[] = "EXISTS (SELECT 1 FROM bank_exercise_themes ft JOIN bank_themes fth ON fth.id = ft.theme_id
                          WHERE ft.bank_exercise_id = be.id AND fth.name = ?)";
      $params[] = $theme;
    }
    if ($category > 0) {
      $where[] = 'be.category_id = ?';
      $params[] = $category;
    }

    return ['WHERE ' . implode(' AND ', $where), $params];
  }

  private function ownFilter(int $teacherId, string $search): array
  {
    $where = ["e.teacher_id = ?", "e.status IN (?, ?)", "e.admin_review_status <> 'blocked'"];
    $params = [$teacherId, Exercise::STATUS_READY, Exercise::STATUS_ACTIVE];

    if ($search !== '') {
      $like = '%' . $search . '%';
      $where[] = "(e.title LIKE ? OR e.description LIKE ?
                   OR EXISTS (SELECT 1 FROM questions sq WHERE sq.exercise_id = e.id AND sq.text LIKE ?))";
      array_push($params, $like, $like, $like);
    }

    return ['WHERE ' . implode(' AND ', $where), $params];
  }

  /** Anexa as Questões (e, no banco, os Temas) a cada item, em uma consulta por tipo. */
  private function attachDetails(array $items, string $questionTable, string $parentColumn): array
  {
    if ($items === []) {
      return [];
    }

    $ids = array_map(static fn(array $item): int => (int) $item['id'], $items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $blockedFilter = $questionTable === 'questions' ? "AND admin_review_status <> 'blocked'" : '';

    $questions = $this->db->fetchAll(
      "SELECT id, {$parentColumn} AS parent_id, text, expected_answer_hint, max_score
             FROM {$questionTable}
             WHERE {$parentColumn} IN ({$placeholders}) {$blockedFilter}
             ORDER BY order_index, id",
      $ids
    );

    $themes = $questionTable === 'bank_questions' ? $this->db->fetchAll(
      "SELECT bet.bank_exercise_id AS parent_id, bt.name, bt.id AS theme_id
             FROM bank_exercise_themes bet JOIN bank_themes bt ON bt.id = bet.theme_id
             WHERE bet.bank_exercise_id IN ({$placeholders})
             ORDER BY bt.name",
      $ids
    ) : [];

    foreach ($items as &$item) {
      $id = (int) $item['id'];
      $item['questions'] = array_values(array_filter($questions, static fn(array $q): bool => (int) $q['parent_id'] === $id));
      $mine = array_filter($themes, static fn(array $t): bool => (int) $t['parent_id'] === $id);
      $item['themes'] = array_values(array_column($mine, 'name'));
      $item['theme_ids'] = array_values(array_map('intval', array_column($mine, 'theme_id')));
    }
    unset($item);

    return $items;
  }

  /**
   * Resposta em Python que viaja com a Questão: só a aprovada pelo Docente
   * (rascunho não vai para o Banco). Colunas python_solution, _status, _source e _updated_at.
   *
   * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
   */
  private static function approvedPython(array $question): array
  {
    if (($question['python_solution_status'] ?? null) !== 'approved' || trim((string) ($question['python_solution'] ?? '')) === '') {
      return [null, null, null, null];
    }

    return [(string) $question['python_solution'], 'approved', (string) ($question['python_solution_source'] ?? 'ai'), date('Y-m-d H:i:s')];
  }
}

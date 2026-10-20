<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Exercise;
use Core\Database;

/**
 * Importar: copiar Questões escolhidas de uma fonte para um Rascunho do Docente.
 *
 * Fontes: um item ativo do Banco de exercícios ('bank') ou um Exercício próprio
 * já Concluído ou Publicado ('own'). A cópia é independente: a origem nunca é
 * alterada, e Questões Bloqueadas pela Moderação nunca são copiadas.
 */
class BankImportService
{
  public const SOURCE_BANK = 'bank';
  public const SOURCE_OWN  = 'own';

  private Database $db;

  public function __construct(?Database $db = null)
  {
    $this->db = $db ?? Database::getInstance();
  }

  /** Cria um Rascunho novo do Docente com as Questões escolhidas. Retorna o id do Rascunho. */
  public function importToNewDraft(int $teacherId, string $source, int $sourceId, array $questionIds, string $title): int
  {
    $db = $this->db;
    $db->beginTransaction();

    try {
      $origin = $this->loadSource($teacherId, $source, $sourceId, $questionIds);
      $title = trim($title) !== '' ? trim($title) : $origin['title'];

      $draftId = (new Exercise($db))->createDraft($teacherId, mb_substr($title, 0, 200), $origin['description']);
      $this->copyQuestions($draftId, $origin['questions'], 1);
      $this->inheritClassification($draftId, $origin['classification']);

      $db->commit();
      return $draftId;
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }

  /** Acrescenta as Questões escolhidas, depois das existentes, a um Rascunho do Docente. Retorna quantas copiou. */
  public function importToDraft(int $teacherId, int $exerciseId, string $source, int $sourceId, array $questionIds): int
  {
    $db = $this->db;
    $db->beginTransaction();

    try {
      $draft = $db->fetchOne(
        "SELECT id FROM exercises WHERE id = ? AND teacher_id = ? AND status = ? FOR UPDATE",
        [$exerciseId, $teacherId, Exercise::STATUS_DRAFT]
      );
      if (!$draft) {
        throw new \RuntimeException('Só é possível importar para um Rascunho seu.');
      }

      $origin = $this->loadSource($teacherId, $source, $sourceId, $questionIds);
      $next = (int) ($db->fetchOne(
        "SELECT COALESCE(MAX(order_index), 0) AS last FROM questions WHERE exercise_id = ?",
        [$exerciseId]
      )['last'] ?? 0) + 1;
      $this->copyQuestions($exerciseId, $origin['questions'], $next);
      $this->inheritClassification($exerciseId, $origin['classification']);

      $db->commit();
      return count($origin['questions']);
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }

  /** @return array{title: string, description: ?string, questions: array<int, array>} */
  private function loadSource(int $teacherId, string $source, int $sourceId, array $questionIds): array
  {
    $questionIds = array_values(array_unique(array_filter(array_map('intval', $questionIds), static fn(int $id): bool => $id > 0)));
    if ($questionIds === []) {
      throw new \RuntimeException('Selecione pelo menos uma questão para importar.');
    }
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

    if ($source === self::SOURCE_OWN) {
      $exercise = $this->db->fetchOne(
        "SELECT title, description FROM exercises
               WHERE id = ? AND teacher_id = ? AND status IN (?, ?) AND admin_review_status <> 'blocked'",
        [$sourceId, $teacherId, Exercise::STATUS_READY, Exercise::STATUS_ACTIVE]
      );
      $questions = $exercise ? $this->db->fetchAll(
        "SELECT text, expected_answer_hint, max_score, bank_question_id FROM questions
               WHERE exercise_id = ? AND id IN ({$placeholders}) AND admin_review_status <> 'blocked'
               ORDER BY order_index, id",
        array_merge([$sourceId], $questionIds)
      ) : [];
    } elseif ($source === self::SOURCE_BANK) {
      $exercise = $this->db->fetchOne(
        "SELECT title, description FROM bank_exercises WHERE id = ? AND status = 'active'",
        [$sourceId]
      );
      $questions = $exercise ? $this->db->fetchAll(
        "SELECT text, expected_answer_hint, max_score, id AS bank_question_id FROM bank_questions
               WHERE bank_exercise_id = ? AND id IN ({$placeholders})
               ORDER BY order_index, id",
        array_merge([$sourceId], $questionIds)
      ) : [];
    } else {
      throw new \RuntimeException('Origem de importação inválida.');
    }

    if (!$exercise) {
      throw new \RuntimeException('Exercício de origem não encontrado ou indisponível para importação.');
    }
    if ($questions === []) {
      throw new \RuntimeException('Nenhuma das questões selecionadas pode ser importada.');
    }

    return [
      'title'          => (string) $exercise['title'],
      'description'    => $exercise['description'] !== null ? (string) $exercise['description'] : null,
      'questions'      => $questions,
      'classification' => (new Category($this->db))->classificationOf($source === self::SOURCE_BANK ? 'bank' : 'exercise', $sourceId),
    ];
  }

  /**
   * Classificação da origem vai para o Rascunho: sem categoria → herda tudo;
   * mesma categoria → soma as subcategorias; categoria diferente → mantém a dele.
   */
  private function inheritClassification(int $draftId, array $origin): void
  {
    if ($origin['category_id'] === null || $origin['themes'] === []) {
      return;
    }
    $categories = new Category($this->db);
    $current = $categories->classificationOf('exercise', $draftId);
    $themeIds = array_column($origin['themes'], 'id');

    if ($current['category_id'] === null) {
      $categories->classify('exercise', $draftId, (int) $origin['category_id'], $themeIds);
    } elseif ($current['category_id'] === $origin['category_id']) {
      $categories->addThemes('exercise', $draftId, $themeIds);
    }
  }

  private function copyQuestions(int $exerciseId, array $questions, int $firstOrder): void
  {
    $order = $firstOrder;
    foreach ($questions as $question) {
      $this->db->insert(
        "INSERT INTO questions (exercise_id, text, expected_answer_hint, max_score, order_index, bank_question_id)
               VALUES (?, ?, ?, ?, ?, ?)",
        [
          $exerciseId,
          $question['text'],
          $question['expected_answer_hint'],
          $question['max_score'],
          $order++,
          // Origem no banco (dá o Tema ao progresso); de um Exercício próprio, herda a dele.
          $question['bank_question_id'] !== null ? (int) $question['bank_question_id'] : null,
        ]
      );
    }
  }
}

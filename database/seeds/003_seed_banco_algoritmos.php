<?php

/**
 * Seed 003 — Banco de algoritmos: população mínima para testar o sistema.
 *
 * 6 tópicos × 10 Exercícios × 5 Questões (300 Questões), com 3 níveis de
 * rigidez de correção e Pontuação de 1 a 3 (ver data/banco_algoritmos.php).
 *
 * Cria, para cada Exercício:
 *  - um item ATIVO no Banco de exercícios, com o tópico como Tema;
 *  - com --teacher-id=N, também um Exercício CONCLUÍDO desse Docente
 *    (aba "Meus exercícios"), que vira a origem do item do banco.
 *
 * Idempotente: Exercícios e itens do banco com o mesmo título são pulados.
 *
 * Uso: php database/seeds/003_seed_banco_algoritmos.php [--teacher-id=N]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit('Somente pela linha de comando.');
}

define('ROOT_PATH', dirname(__DIR__, 2));

require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();
require ROOT_PATH . '/autoload.php';
require __DIR__ . '/data/banco_algoritmos.php';

$teacherId = 0;
foreach (array_slice($argv, 1) as $arg) {
  if (preg_match('/^--teacher-id=(\d+)$/', $arg, $m)) {
    $teacherId = (int) $m[1];
  } else {
    fwrite(STDERR, "Argumento desconhecido: {$arg}\nUso: php database/seeds/003_seed_banco_algoritmos.php [--teacher-id=N]\n");
    exit(1);
  }
}

$db = Core\Database::getInstance();

if ($teacherId > 0) {
  $teacher = $db->fetchOne("SELECT id, name FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'", [$teacherId]);
  if (!$teacher) {
    fwrite(STDERR, "Docente {$teacherId} não encontrado ou inativo. Nada foi criado.\n");
    exit(1);
  }
}

$bank = new App\Models\BankExercise($db);
$created = ['bank' => 0, 'own' => 0, 'questions' => 0];
$skipped = ['bank' => 0, 'own' => 0];

// Categoria "Algoritmos" e uma subcategoria por tópico (cria o que faltar).
$subcategoryId = static function (string $name) use ($db): array {
  $db->execute("INSERT IGNORE INTO categories (name) VALUES ('Algoritmos')");
  $categoryId = (int) $db->fetchOne("SELECT id FROM categories WHERE name = 'Algoritmos'")['id'];
  $db->execute("INSERT IGNORE INTO bank_themes (category_id, name) VALUES (?, ?)", [$categoryId, $name]);
  $themeId = (int) $db->fetchOne("SELECT id FROM bank_themes WHERE category_id = ? AND name = ?", [$categoryId, $name])['id'];
  return [$categoryId, $themeId];
};
$classifier = new App\Models\Category($db);

foreach (banco_algoritmos_dados() as $topic) {
  foreach ($topic['exercicios'] as $exercise) {
    $db->beginTransaction();

    try {
      $sourceId = null;

      if ($teacherId > 0) {
        $own = $db->fetchOne("SELECT id FROM exercises WHERE teacher_id = ? AND title = ?", [$teacherId, $exercise['titulo']]);
        if ($own) {
          $sourceId = (int) $own['id'];
          $skipped['own']++;
        } else {
          $sourceId = $db->insert(
            "INSERT INTO exercises (teacher_id, title, description, status) VALUES (?, ?, ?, 'ready')",
            [$teacherId, $exercise['titulo'], $exercise['descricao']]
          );
          foreach ($exercise['questoes'] as $i => $q) {
            $db->insert(
              "INSERT INTO questions (exercise_id, text, expected_answer_hint, max_score, order_index) VALUES (?, ?, ?, ?, ?)",
              [$sourceId, $q['enunciado'], $q['referencia'], $q['pontos'], $i + 1]
            );
          }
          [$categoryId, $themeId] = $subcategoryId($topic['tema']);
          $classifier->classify('exercise', $sourceId, $categoryId, [$themeId]);
          $created['own']++;
        }
      }

      $inBank = $db->fetchOne("SELECT id FROM bank_exercises WHERE title = ? AND status = 'active'", [$exercise['titulo']]);
      if ($inBank) {
        $skipped['bank']++;
      } else {
        $bankId = $db->insert(
          "INSERT INTO bank_exercises (title, description, source_exercise_id, source_teacher_id, status, reviewed_at)
                 VALUES (?, ?, ?, ?, 'active', NOW())",
          [$exercise['titulo'], $exercise['descricao'], $sourceId, $teacherId > 0 ? $teacherId : null]
        );
        foreach ($exercise['questoes'] as $i => $q) {
          $db->insert(
            "INSERT INTO bank_questions (bank_exercise_id, text, expected_answer_hint, max_score, order_index) VALUES (?, ?, ?, ?, ?)",
            [$bankId, $q['enunciado'], $q['referencia'], $q['pontos'], $i + 1]
          );
          $created['questions']++;
        }
        $bank->setThemes($bankId, [$topic['tema']]);
        $created['bank']++;
      }

      $db->commit();
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      fwrite(STDERR, "Falha em \"{$exercise['titulo']}\": {$e->getMessage()}\n");
      exit(1);
    }
  }
}

echo "Banco de exercícios: {$created['bank']} criados ({$created['questions']} questões), {$skipped['bank']} já existiam.\n";
if ($teacherId > 0) {
  echo "Exercícios do Docente {$teacherId}: {$created['own']} criados, {$skipped['own']} já existiam.\n";
}

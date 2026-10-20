-- Migration 033 — Categoria e subcategoria dos Exercícios e do banco.
-- categories: lista mantida pelo Admin (ex.: Algoritmos).
-- bank_themes passa a ser a SUBCATEGORIA (antes "Tema"): ganha category_id e active;
--   o nome deixa de ser único global e passa a ser único dentro da categoria.
-- bank_exercises e exercises ganham category_id; exercise_themes liga Exercício → subcategorias.
-- Os Temas existentes viram subcategorias de "Algoritmos". Exercícios com Questões
-- importadas do banco herdam a classificação do item de origem.
-- Idempotente: cada passo só roda se ainda faltar. Também está no 001 (linha de base).

CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(80)  NOT NULL,
    active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- bank_themes.category_id / active
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_themes' AND COLUMN_NAME = 'category_id');
SET @sql := IF(@has = 0,
  'ALTER TABLE bank_themes ADD COLUMN category_id INT UNSIGNED NULL AFTER id, ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER name',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Temas existentes → subcategorias de "Algoritmos" (só se houver Temas sem categoria)
SET @orphans := (SELECT COUNT(*) FROM bank_themes WHERE category_id IS NULL);
SET @sql := IF(@orphans > 0, 'INSERT IGNORE INTO categories (name) VALUES (''Algoritmos'')', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE bank_themes
   SET category_id = (SELECT id FROM categories WHERE name = 'Algoritmos')
 WHERE category_id IS NULL;

-- Nome único dentro da categoria (antes: único global)
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_themes' AND INDEX_NAME = 'uk_bank_themes_name');
SET @sql := IF(@has > 0, 'ALTER TABLE bank_themes DROP INDEX uk_bank_themes_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_themes' AND INDEX_NAME = 'uk_bank_themes_category_name');
SET @sql := IF(@has = 0, 'ALTER TABLE bank_themes ADD UNIQUE KEY uk_bank_themes_category_name (category_id, name)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_themes' AND CONSTRAINT_NAME = 'fk_bank_themes_category');
SET @sql := IF(@has = 0, 'ALTER TABLE bank_themes ADD CONSTRAINT fk_bank_themes_category FOREIGN KEY (category_id) REFERENCES categories(id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- bank_exercises.category_id
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_exercises' AND COLUMN_NAME = 'category_id');
SET @sql := IF(@has = 0, 'ALTER TABLE bank_exercises ADD COLUMN category_id INT UNSIGNED NULL AFTER description', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_exercises' AND CONSTRAINT_NAME = 'fk_bank_ex_category');
SET @sql := IF(@has = 0, 'ALTER TABLE bank_exercises ADD CONSTRAINT fk_bank_ex_category FOREIGN KEY (category_id) REFERENCES categories(id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE bank_exercises be
   SET be.category_id = (SELECT MIN(bt.category_id)
                           FROM bank_exercise_themes bet JOIN bank_themes bt ON bt.id = bet.theme_id
                          WHERE bet.bank_exercise_id = be.id)
 WHERE be.category_id IS NULL;

-- exercises.category_id
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercises' AND COLUMN_NAME = 'category_id');
SET @sql := IF(@has = 0, 'ALTER TABLE exercises ADD COLUMN category_id INT UNSIGNED NULL AFTER description', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercises' AND CONSTRAINT_NAME = 'fk_exercises_category');
SET @sql := IF(@has = 0, 'ALTER TABLE exercises ADD CONSTRAINT fk_exercises_category FOREIGN KEY (category_id) REFERENCES categories(id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS exercise_themes (
    exercise_id INT UNSIGNED NOT NULL,
    theme_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (exercise_id, theme_id),
    INDEX idx_exercise_themes_theme (theme_id),
    CONSTRAINT fk_exercise_themes_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_exercise_themes_theme FOREIGN KEY (theme_id) REFERENCES bank_themes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exercícios ainda sem categoria, com Questões vindas do banco: herdam do item de origem.
UPDATE exercises e
   SET e.category_id = (SELECT MIN(be.category_id)
                          FROM questions q
                          JOIN bank_questions bq ON bq.id = q.bank_question_id
                          JOIN bank_exercises be ON be.id = bq.bank_exercise_id
                         WHERE q.exercise_id = e.id)
 WHERE e.category_id IS NULL;
INSERT IGNORE INTO exercise_themes (exercise_id, theme_id)
SELECT DISTINCT q.exercise_id, bet.theme_id
  FROM questions q
  JOIN exercises e ON e.id = q.exercise_id
  JOIN bank_questions bq ON bq.id = q.bank_question_id
  JOIN bank_exercise_themes bet ON bet.bank_exercise_id = bq.bank_exercise_id
  JOIN bank_themes bt ON bt.id = bet.theme_id AND bt.category_id = e.category_id
 WHERE NOT EXISTS (SELECT 1 FROM exercise_themes x WHERE x.exercise_id = q.exercise_id);

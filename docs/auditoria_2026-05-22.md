# Auditoria do Sistema IAProg — 2026-05-22

## 🔴 Bugs / Falhas Reais

### 1. FK `exercises.turma_id` sem CASCADE bloqueia exclusão de turmas
**Arquivo:** `database/migrations/001_create_tables.sql:56`

`CONSTRAINT fk_ex_turma FOREIGN KEY (turma_id) REFERENCES turmas(id)` sem `ON DELETE`.
Excluir uma turma → MySQL RESTRICT impede a operação, mesmo que FKs de `exercise_turmas` e `student_turma` tenham CASCADE.

**Fix:** Adicionar `ON DELETE SET NULL` nessa constraint.

---

### 2. Dashboard carrega todos os usuários para contar por role
**Arquivo:** `app/Controllers/AdminController.php:43`

`$this->users->getAllForAdmin()` sem limit carrega a tabela inteira para depois contar admin/teacher/student em PHP.
O método `countForAdmin()` existe no model mas não é usado aqui.

**Fix:** Usar `countForAdmin()` com filtro de role, ou fazer `GROUP BY role` direto no SQL.

---

### 3. `hash_equals` usado em strings plaintext
**Arquivo:** `app/Controllers/AuthController.php:119`

```php
hash_equals($currentPassword, $password)
```

`hash_equals` é para comparar hashes (evita timing attack em digests). Com plaintext não faz sentido e pode mascarar bug futuro.

**Fix:** `$currentPassword === $password`

---

### 4. `sleep()` bloqueante em worker PHP durante retries OpenAI
**Arquivo:** `app/Services/OpenAIService.php` — método `callApi`

`sleep(2 ** $attempt)`: attempt=1→2s, attempt=2→4s. Bloqueia o processo PHP inteiro. Sob carga, até 6s por tentativa imobiliza o worker.

**Fix:** Fila assíncrona para correção, ou ao menos limitar retries/timeout total.

---

### 5. `Exercise::delete` apaga attempts redundantemente
**Arquivo:** `app/Models/Exercise.php:23`

```php
DELETE FROM attempts WHERE exercise_id = ?
```

Redundante — a FK `fk_att_exercise` já tem `ON DELETE CASCADE`. Inofensivo, mas indica código desatualizado pós-migration 004.

---

## 🟡 Inconsistências de Design

### 6. Duas rotas mapeiam para o mesmo método com nomes enganosos
**Arquivo:** `routes/web.php:94-95`

```php
$router->post('/teacher/students/{id}/detach', 'StudentController@destroy');
$router->post('/teacher/students/{id}/delete', 'StudentController@destroy');
```

`destroy` só desvincula o aluno das turmas do professor — nunca deleta o usuário. A rota `/delete` nunca deleta nada.

---

### 7. `@method` docblock em `Turma` para métodos implementados
**Arquivo:** `app/Models/Turma.php:6-10`

`@method reactivate`, `countPendingEnrollmentsForAdmin`, `getPendingTurmasForAdmin` — todos três estão implementados no corpo da classe. `@method` é para métodos mágicos não implementados. Confunde IDEs e estáticas.

---

### 8. Colunas legadas em `exercises` ainda sendo escritas
**Arquivo:** `app/Models/Exercise.php:535`

`exercises.turma_id`, `opens_at`, `closes_at`, `max_attempts` são marcadas como legado no schema, mas `Exercise::activate` ainda escreve `turma_id`. Queries de leitura já usam `exercise_turmas`. Estado duplo inconsistente.

---

### 9. `Request::email` sanitiza em vez de validar
**Arquivo:** `core/Request.php:36`

`FILTER_SANITIZE_EMAIL` modifica a string (remove caracteres inválidos). O resultado pode parecer válido mas não é o valor original. `AuthController::register` então valida com `FILTER_VALIDATE_EMAIL` na string já mutada.

---

### 10. `Attempt::getWithExercise` fallback expõe publicação errada
**Arquivo:** `app/Models/Attempt.php` — método `getWithExercise`

```sql
COALESCE(attempt_et.closes_at, MAX(CASE WHEN st.student_id IS NOT NULL THEN et.closes_at END))
```

Se a turma da tentativa não tem publicação, usa `MAX(closes_at)` de qualquer turma do aluno. Pode exibir prazo errado na tela de resultado.

---

### 11. Log de injeção descarta conteúdo
**Arquivo:** `app/Services/OpenAIService.php` — método `buildInjectionLogSummary`

Salva apenas o tamanho da resposta, não o conteúdo. Impossível revisar tentativas de injeção para melhoria do sistema.

---

## 🟢 Menores

- `global $session;` declarado duas vezes em `AttemptController::start` — redundante.
- `answers.question_id` FK sem `ON DELETE` explícito — RESTRICT implícito não documentado.

---

## Prioridade de Correção

| # | Item | Impacto |
|---|------|---------|
| 1 | FK turma delete sem CASCADE | Erro 500 em produção ao excluir turma |
| 2 | Dashboard N+1 | Performance degradada com escala |
| 3 | `hash_equals` plaintext | Correção de código |
| 4 | `sleep` bloqueante | Disponibilidade sob carga |
| 5 | Rotas `/detach` e `/delete` enganosas | Manutenibilidade |

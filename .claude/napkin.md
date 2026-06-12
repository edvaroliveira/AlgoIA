# Napkin Runbook

## Curation Rules
- Re-prioritize on every read.
- Keep recurring, high-value notes only.
- Max 10 items per category.
- Each item includes date + "Do instead".

## Execution & Validation (Highest Priority)
1. **[2026-06-10] Use the repository test runners**
   Do instead: run `php bin/run_tests.php` first, then use the DB and schema runners when their dependencies are available.
2. **[2026-06-10] Validate clean installs against the current runtime schema**
   Do instead: whenever adding an incremental migration, also update `001_create_tables.sql` and verify it contains every column and index required by current code.

## Shell & Command Reliability
1. **[2026-06-10] Inspect this dependency-free PHP app with native tooling**
   Do instead: use `php -l`, `rg`, and the scripts under `bin/`; do not assume Composer or a framework CLI exists.

## Domain Behavior Guardrails
1. **[2026-06-10] Preserve role and turma authorization boundaries**
   Do instead: verify student, teacher, and admin access at controller and model query boundaries whenever changing workflows.

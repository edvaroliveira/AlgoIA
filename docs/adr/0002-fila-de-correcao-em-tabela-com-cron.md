# Correção por IA assíncrona, com fila em tabela MySQL e worker via cron

O envio de uma Tentativa só grava as respostas e enfileira um registro em `grading_jobs`. A chamada à OpenAI é feita depois por `bin/process_grading_jobs.php`, agendado em cron. Chamar a IA dentro do request do aluno deixava o envio lento e frágil: um timeout ou erro 429 da OpenAI podia perder ou travar a entrega. Um broker (Redis, RabbitMQ, SQS) não está disponível na hospedagem compartilhada e seria uma peça a mais para operar. A fila usa o próprio banco: reserva via `SELECT … FOR UPDATE`, lease por `worker_id` renovado durante a correção, recuperação de jobs presos em `processing` (padrão 15 min), backoff e no máximo 3 tentativas (configurável por `GRADING_JOB_MAX_ATTEMPTS` e `GRADING_JOB_STALE_MINUTES`).

## Consequences

- Sem cron configurado, nenhuma Tentativa é corrigida. Ela fica parada em *Enviada* ("Em correção").
- A latência da correção é limitada pelo intervalo do cron, não pela OpenAI.
- Um único `attempt_id` por job (`uk_grading_jobs_attempt`). O Reprocessamento reaproveita o mesmo job em vez de criar outro.

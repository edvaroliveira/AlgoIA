# Backlog de Cadastro de Docente

Este backlog cobre a criacao de uma tela de cadastro de docentes controlada pelo administrador, permitindo habilitar ou desabilitar o cadastro publico conforme a necessidade da instituicao.

## P0 - Criar configuracao administrativa para cadastro de docentes

**Problema:** atualmente o cadastro de novos docentes depende de fluxo interno/manual e nao existe uma chave administrativa para liberar ou bloquear solicitacoes externas.

**Objetivo:** permitir que o administrador controle, pelo sistema, se a tela publica de cadastro de docente esta disponivel.

**Escopo:**
- Criar configuracao persistente para `teacher_registration_enabled`.
- Adicionar controle no painel administrativo para habilitar/desabilitar cadastro de docentes.
- Registrar auditoria quando a configuracao for alterada.
- Garantir valor padrao seguro: cadastro desabilitado.
- Exibir estado atual da configuracao para o administrador.
- Desabilitar o cadastro nao afeta solicitacoes ja pendentes — elas permanecem na fila para revisao.

**Criterios de aceite:**
- Admin consegue habilitar e desabilitar o cadastro de docentes.
- Cadastro fica bloqueado quando a configuracao estiver desabilitada.
- Alteracoes ficam registradas em auditoria com usuario, data e novo valor.
- O sistema continua funcionando mesmo se a configuracao ainda nao existir no banco.
- Solicitacoes pendentes existentes permanecem visiveis para revisao apos desabilitacao.

**Risco:** alto, pois controla criacao de contas com perfil docente.

## P0 - Criar tela publica de solicitacao de cadastro docente

**Problema:** nao ha uma tela propria para que um docente solicite acesso ao sistema.

**Objetivo:** criar um formulario publico de cadastro docente, protegido pela configuracao administrativa.

**Escopo:**
- Criar rota `GET /register/teacher`.
- Criar rota `POST /register/teacher`.
- Criar view publica para cadastro docente.
- Campos minimos: nome, e-mail, senha, confirmacao de senha e justificativa/instituicao.
- Validar nome, e-mail unico, senha minima e confirmacao de senha.
- Bloquear acesso e submissao quando o cadastro estiver desabilitado.
- Redirecionar ou exibir mensagem clara quando o cadastro estiver fechado.
- Aplicar rate limit simples por IP e por e-mail para prevenir spam na fila de aprovacao.

**Criterios de aceite:**
- Usuario consegue abrir a tela apenas quando o cadastro estiver habilitado.
- Formulario valida campos obrigatorios e e-mail duplicado.
- Mensagem de e-mail duplicado nao revela o tipo ou role da conta existente.
- Senha e armazenada com hash.
- Nenhum cadastro e criado quando a configuracao estiver desabilitada.
- Mensagens de erro nao revelam informacoes sensiveis alem do necessario.
- Tentativas repetidas por IP ou e-mail sao bloqueadas ou auditadas.

**Risco:** alto, pois adiciona superficie publica de autenticacao/cadastro.

## P0 - Criar fluxo de aprovacao administrativa para docentes

**Problema:** permitir cadastro direto como docente sem moderacao pode liberar acesso indevido a funcionalidades sensiveis.

**Objetivo:** cadastrar docentes inicialmente como pendentes e exigir aprovacao do administrador antes do acesso pleno.

**Escopo:**
- Adicionar `rejected` ao ENUM de status de usuarios (atualmente `pending`, `active`, `inactive`).
- Criar cadastro docente com `role = teacher` e `status = pending`.
- Bloquear login ou acesso ao painel para usuario pendente ou rejeitado.
- Ajustar mensagem de login para status `pending` com base no role: docente ve "aguarda aprovacao do administrador"; aluno mantem mensagem atual ("aguarda aprovacao do docente").
- Criar listagem administrativa de solicitacoes pendentes.
- Permitir aprovar ou rejeitar solicitacao.
- Registrar auditoria de solicitacao, aprovacao e rejeicao.

**Criterios de aceite:**
- Docente recem-cadastrado nao acessa funcionalidades antes da aprovacao.
- Admin visualiza solicitacoes pendentes com dados principais.
- Admin consegue aprovar uma solicitacao.
- Admin consegue rejeitar uma solicitacao.
- Login de usuario pendente exibe mensagem adequada e diferenciada por role.
- Login de usuario rejeitado exibe mensagem adequada.

**Risco:** alto, pois define o limite entre solicitacao publica e acesso real ao sistema.

## P1 - Notificar administradores sobre novas solicitacoes

**Problema:** solicitacoes pendentes podem ficar esquecidas se dependerem apenas de consulta manual.

**Objetivo:** dar visibilidade operacional para novas solicitacoes de docentes.

**Escopo:**
- Adicionar contador de docentes pendentes no dashboard administrativo.
- Adicionar link direto para a tela de solicitacoes.
- Opcionalmente exibir alerta visual quando houver pendencias.
- Preparar ponto futuro para notificacao por e-mail, se o sistema passar a enviar e-mails.

**Criterios de aceite:**
- Dashboard do admin mostra quantidade de solicitacoes pendentes.
- Admin consegue acessar a lista de pendencias em um clique.
- Contador atualiza conforme aprovacao/rejeicao.

**Risco:** medio, pois melhora operacao sem alterar diretamente a seguranca.

## P1 - Melhorar seguranca e antifraude do formulario

**Problema:** uma tela publica de cadastro pode receber spam, tentativas automatizadas ou dados maliciosos.

**Objetivo:** reduzir abuso sem dificultar demais o cadastro legitimo.

**Escopo:**
- Aplicar CSRF no formulario (ja existe no sistema via `Request::validateCsrf()`).
- Limitar tamanho dos campos textuais.
- Sanitizar exibicao da justificativa/instituicao.
- Registrar IP ou metadados basicos da solicitacao, se a estrutura permitir.

**Criterios de aceite:**
- Formulario possui protecao CSRF.
- Campos longos nao quebram layout nem banco.
- Dados inseridos pelo solicitante nao executam HTML/JS na area admin.

**Risco:** medio, pois protege uma nova rota publica.

## P2 - Permitir historico e revisao de solicitacoes

**Problema:** apos aprovar ou rejeitar uma solicitacao, pode ser necessario consultar o historico para suporte ou auditoria.

**Objetivo:** manter rastreabilidade das decisoes administrativas.

**Escopo:**
- Exibir solicitacoes aprovadas e rejeitadas em filtro separado.
- Mostrar data da solicitacao, decisao e administrador responsavel.
- Permitir consultar justificativa original.
- Permitir reavaliar uma solicitacao rejeitada: reavaliar significa aprovar diretamente (`rejected` -> `active`), registrando auditoria com o admin responsavel.

**Criterios de aceite:**
- Admin consegue consultar solicitacoes antigas.
- Cada solicitacao mostra seu status final.
- Historico deixa claro quem aprovou/rejeitou e quando.
- Reavaliacao de rejeitado registra auditoria e ativa a conta diretamente.

**Risco:** baixo, pois e melhoria de auditoria e suporte.

## Ordem Sugerida

1. Criar configuracao administrativa para cadastro de docentes.
2. Criar tela publica de solicitacao de cadastro docente.
3. Criar fluxo de aprovacao administrativa para docentes.
4. Notificar administradores sobre novas solicitacoes.
5. Melhorar seguranca e antifraude do formulario.
6. Permitir historico e revisao de solicitacoes.

## Sugestao de Estrutura Tecnica

**Banco de dados:**

Migration para adicionar `rejected` ao ENUM de status:
```sql
ALTER TABLE users
  MODIFY COLUMN status ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending';
```

Migration para colunas de aprovacao em `users`:
```sql
ALTER TABLE users
  ADD COLUMN registration_note TEXT NULL AFTER status,
  ADD COLUMN approved_by INT UNSIGNED NULL AFTER registration_note,
  ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
  ADD COLUMN rejected_at DATETIME NULL AFTER approved_at,
  ADD CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;
```

Criar tabela `system_settings` com chave, valor, usuario de atualizacao e datas.

**Models:**
- Criar `SystemSetting`.
- Atualizar `User` para criar docente pendente e listar solicitacoes.

**Controllers:**
- Criar ou ampliar controller administrativo para configuracoes.
- Criar fluxo publico em `AuthController` ou controller proprio de registro.
- Criar acoes administrativas de aprovar/rejeitar docente.
- Ajustar `AuthController::login` para mensagem de `pending` diferenciada por role.

**Views:**
- Tela publica de cadastro docente.
- Toggle administrativo de habilitacao.
- Lista administrativa de solicitacoes pendentes.
- Indicador no dashboard administrativo.

## Observacoes

- O cadastro publico deve nascer desabilitado por padrao.
- O docente cadastrado publicamente nao deve ser ativado automaticamente.
- A aprovacao administrativa deve ser requisito antes do acesso ao painel docente.
- O fluxo pode reaproveitar a politica de senha ja existente (`isStrongPassword`) e o reset por token implementado anteriormente.
- CSRF ja existe no sistema — nao reimplementar, apenas aplicar `Request::validateCsrf()` nas novas rotas.

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 22/05/2026 às 23:57
-- Versão do servidor: 5.7.44-48
-- Versão do PHP: 8.3.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `edvarp17_algoia`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `answers`
--

CREATE TABLE `answers` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `student_answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_score` decimal(4,1) DEFAULT NULL,
  `ai_feedback` text COLLATE utf8mb4_unicode_ci,
  `deduction_reasons_json` json DEFAULT NULL,
  `evaluated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `attempts`
--

CREATE TABLE `attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `exercise_id` int(10) UNSIGNED NOT NULL,
  `turma_id` int(10) UNSIGNED DEFAULT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` datetime DEFAULT NULL,
  `total_score` decimal(8,2) DEFAULT NULL,
  `status` enum('in_progress','submitted','graded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `actor_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercises`
--

CREATE TABLE `exercises` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `turma_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `opens_at` datetime DEFAULT NULL,
  `closes_at` datetime DEFAULT NULL,
  `max_attempts` int(11) DEFAULT NULL,
  `status` enum('draft','ready','active') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `admin_review_status` enum('approved','flagged','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `admin_review_note` text COLLATE utf8mb4_unicode_ci,
  `admin_reviewed_at` datetime DEFAULT NULL,
  `admin_reviewed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercise_turmas`
--

CREATE TABLE `exercise_turmas` (
  `exercise_id` int(10) UNSIGNED NOT NULL,
  `turma_id` int(10) UNSIGNED NOT NULL,
  `opens_at` datetime NOT NULL,
  `closes_at` datetime NOT NULL,
  `max_attempts` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `injection_logs`
--

CREATE TABLE `injection_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `answer_id` int(10) UNSIGNED DEFAULT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `flagged_pattern` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `questions`
--

CREATE TABLE `questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `exercise_id` int(10) UNSIGNED NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_answer_hint` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Conceitos esperados — visível ao aluno apenas após fechamento',
  `max_score` decimal(4,1) NOT NULL DEFAULT '10.0',
  `order_index` int(11) NOT NULL DEFAULT '0',
  `admin_review_status` enum('approved','flagged','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `admin_review_note` text COLLATE utf8mb4_unicode_ci,
  `admin_reviewed_at` datetime DEFAULT NULL,
  `admin_reviewed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_turma`
--

CREATE TABLE `student_turma` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `turma_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','active') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_key` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `password_reset_at` datetime DEFAULT NULL,
  `role` enum('admin','teacher','student') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `status` enum('pending','active','inactive','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `registration_note` text COLLATE utf8mb4_unicode_ci,
  `registration_source` enum('manual','student_public','teacher_public') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `must_change_password`, `password_reset_at`, `role`, `status`, `registration_note`, `registration_source`, `approved_by`, `approved_at`, `rejected_at`, `created_at`, `updated_at`) VALUES
(1, 'edvar', 'edvaroliveira@gmail.com', '$2y$10$A94qq1gx//vO1zr4iuCkG.7OEVl2KdG086rlFJrvDhmwqEVj6LCF.', 0, NULL, 'teacher', 'active', NULL, 'manual', NULL, NULL, NULL, '2026-05-20 18:01:37', '2026-05-20 18:01:37'),
(3, 'jams', 'edvar.oliveira@ufra.edu.br', '$2y$10$GuMFK1Ufldg.NNHt09LxGusehwiufEvOxOKpxY7BcYKkuaQYDA7dW', 0, NULL, 'student', 'active', NULL, 'manual', NULL, NULL, NULL, '2026-05-20 22:42:48', '2026-05-20 22:43:24'),
(13, 'admin', 'admin@edvar.pro.br', '$2y$10$F7tYzYd1iMEUeLRmdqhz1ONtds.KoNleS1Jhgem.Z0y7RNhLoiLZu', 0, NULL, 'admin', 'active', NULL, 'manual', NULL, NULL, NULL, '2026-05-21 23:32:03', '2026-05-21 23:32:03'),
(14, 'Esther', 'edvar.oliveira@isaci.org.br', '$2y$10$O.VZnOUODZUxHFZZrU8hx.7uqB5C0TBBxJCn703UsYzcNyI8SmyQm', 0, NULL, 'teacher', 'active', NULL, 'manual', NULL, NULL, NULL, '2026-05-22 15:17:20', '2026-05-22 15:17:20'),
(15, 'Thays', 'thays.c.oliveira@gmail.com', '$2y$10$H/5uU4x2lin1x4QrEQAQ8OjqHvWKGvl23E5A6rTjXcVUvO97PrWA6', 0, NULL, 'teacher', 'active', 'dfgsdfgsdfg\r\nsdf\r\ngsdf\r\ng\r\nsdfg', 'teacher_public', 13, '2026-05-22 17:10:22', NULL, '2026-05-22 17:09:47', '2026-05-22 19:21:19');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_answer` (`attempt_id`,`question_id`),
  ADD KEY `fk_ans_question` (`question_id`);

--
-- Índices de tabela `attempts`
--
ALTER TABLE `attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_att_student` (`student_id`),
  ADD KEY `fk_att_exercise` (`exercise_id`),
  ADD KEY `idx_attempts_turma` (`turma_id`);

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_actor` (`actor_user_id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_action` (`action`);

--
-- Índices de tabela `exercises`
--
ALTER TABLE `exercises`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ex_teacher` (`teacher_id`),
  ADD KEY `fk_ex_turma` (`turma_id`);

--
-- Índices de tabela `exercise_turmas`
--
ALTER TABLE `exercise_turmas`
  ADD PRIMARY KEY (`exercise_id`,`turma_id`),
  ADD KEY `fk_ex_turmas_turma` (`turma_id`);

--
-- Índices de tabela `injection_logs`
--
ALTER TABLE `injection_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inj_student` (`student_id`),
  ADD KEY `idx_injection_logs_answer` (`answer_id`);

--
-- Índices de tabela `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_q_exercise` (`exercise_id`);

--
-- Índices de tabela `student_turma`
--
ALTER TABLE `student_turma`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_student_turma` (`student_id`,`turma_id`),
  ADD KEY `fk_st_turma` (`turma_id`);

--
-- Índices de tabela `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_setting_key` (`setting_key`),
  ADD KEY `fk_settings_updated_by` (`updated_by`);

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_key` (`access_key`),
  ADD KEY `fk_turmas_teacher` (`teacher_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_approved_by` (`approved_by`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `attempts`
--
ALTER TABLE `attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercises`
--
ALTER TABLE `exercises`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `injection_logs`
--
ALTER TABLE `injection_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `student_turma`
--
ALTER TABLE `student_turma`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `fk_ans_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ans_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Restrições para tabelas `attempts`
--
ALTER TABLE `attempts`
  ADD CONSTRAINT `fk_att_exercise` FOREIGN KEY (`exercise_id`) REFERENCES `exercises` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_att_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `exercises`
--
ALTER TABLE `exercises`
  ADD CONSTRAINT `fk_ex_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ex_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`);

--
-- Restrições para tabelas `exercise_turmas`
--
ALTER TABLE `exercise_turmas`
  ADD CONSTRAINT `fk_ex_turmas_exercise` FOREIGN KEY (`exercise_id`) REFERENCES `exercises` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ex_turmas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `injection_logs`
--
ALTER TABLE `injection_logs`
  ADD CONSTRAINT `fk_inj_answer` FOREIGN KEY (`answer_id`) REFERENCES `answers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inj_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_q_exercise` FOREIGN KEY (`exercise_id`) REFERENCES `exercises` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `student_turma`
--
ALTER TABLE `student_turma`
  ADD CONSTRAINT `fk_st_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_st_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `turmas`
--
ALTER TABLE `turmas`
  ADD CONSTRAINT `fk_turmas_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

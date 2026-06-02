<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Decomposed into:
 *   AdminBaseController         — shared helpers
 *   AdminDashboardController    — dashboard, presets, grading job retry, teacher registration toggle
 *   AdminUserController         — user CRUD and batch status operations
 *   AdminTurmaController        — turma management and publication batch operations
 *   AdminExerciseController     — exercise moderation and publication management
 *   AdminAuditController        — audit log listing and exports
 *   AdminTeacherRequestController — teacher request approval workflow
 */
class AdminController
{
}

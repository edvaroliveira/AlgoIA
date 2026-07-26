<?php

declare(strict_types=1);

/** @var Core\Router $router */

// ── Public ──────────────────────────────────────────────────────────────────
$router->get('/',          'AuthController@showLogin');
$router->get('/login',     'AuthController@showLogin');
$router->post('/login',    'AuthController@login');
$router->get('/register',  'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/register/teacher',  'AuthController@showRegisterTeacher');
$router->post('/register/teacher', 'AuthController@registerTeacher');
$router->get('/password/change', 'AuthController@showChangePassword', ['auth']);
$router->post('/password/change', 'AuthController@changePassword', ['auth']);
$router->get('/password/reset', 'AuthController@showResetPassword');
$router->post('/password/reset', 'AuthController@resetPassword');
$router->post('/logout',   'AuthController@logout');

// ── Conta (perfil próprio) ───────────────────────────────────────────────────
$router->get('/conta',               'AccountController@show', ['auth']);
$router->post('/conta/foto',         'AccountController@uploadAvatar', ['auth']);
$router->post('/conta/foto/remover', 'AccountController@deleteAvatar', ['auth']);

// ── Teacher ──────────────────────────────────────────────────────────────────
$router->get('/teacher/dashboard', 'DashboardController@teacher', ['teacher']);

// ── Admin ───────────────────────────────────────────────────────────────────
$router->get('/admin/dashboard', 'AdminDashboardController@dashboard', ['admin']);
$router->post('/admin/presets/{scope}/save', 'AdminDashboardController@saveFilterPreset', ['admin']);
$router->post('/admin/presets/{scope}/delete', 'AdminDashboardController@deleteFilterPreset', ['admin']);
$router->post('/admin/grading-jobs/{id}/retry', 'AdminDashboardController@retryGradingJob', ['admin']);
$router->post('/admin/settings/teacher-registration', 'AdminDashboardController@toggleTeacherRegistration', ['admin']);
$router->get('/admin/users',     'AdminUserController@users', ['admin']);
$router->get('/admin/users/export', 'AdminUserController@exportUsers', ['admin']);
$router->get('/admin/users/export.json', 'AdminUserController@exportUsersJson', ['admin']);
$router->post('/admin/users/batch-activate', 'AdminUserController@activateUsersBatch', ['admin']);
$router->post('/admin/users/batch-deactivate', 'AdminUserController@deactivateUsersBatch', ['admin']);
$router->get('/admin/users/{id}', 'AdminUserController@showUser', ['admin']);
$router->get('/admin/users/{id}/edit', 'AdminUserController@editUser', ['admin']);
$router->post('/admin/users/{id}', 'AdminUserController@updateUser', ['admin']);
$router->post('/admin/users/{id}/status', 'AdminUserController@updateUserStatus', ['admin']);
$router->post('/admin/users/{id}/reset-password', 'AdminUserController@resetUserPassword', ['admin']);
$router->get('/admin/turmas',    'AdminTurmaController@turmas', ['admin']);
$router->get('/admin/turmas/export', 'AdminTurmaController@exportTurmas', ['admin']);
$router->get('/admin/turmas/export.json', 'AdminTurmaController@exportTurmasJson', ['admin']);
$router->post('/admin/turmas/batch-deactivate', 'AdminTurmaController@deactivateTurmasBatch', ['admin']);
$router->post('/admin/turmas/batch-reactivate', 'AdminTurmaController@reactivateTurmasBatch', ['admin']);
$router->get('/admin/turmas/{id}', 'AdminTurmaController@showTurma', ['admin']);
$router->post('/admin/turmas/{id}/publications/batch-close', 'AdminTurmaController@closeTurmaPublicationsBatch', ['admin']);
$router->post('/admin/turmas/{id}/publications/batch-reopen', 'AdminTurmaController@reopenTurmaPublicationsBatch', ['admin']);
$router->post('/admin/turmas/{id}/deactivate', 'AdminTurmaController@deactivateTurma', ['admin']);
$router->post('/admin/turmas/{id}/reactivate', 'AdminTurmaController@reactivateTurma', ['admin']);
$router->get('/admin/exercises', 'AdminExerciseController@exercises', ['admin']);
$router->get('/admin/exercises/export', 'AdminExerciseController@exportExercises', ['admin']);
$router->get('/admin/exercises/export.json', 'AdminExerciseController@exportExercisesJson', ['admin']);
$router->post('/admin/exercises/batch-close', 'AdminExerciseController@closeExercisesBatch', ['admin']);
$router->post('/admin/exercises/batch-reopen', 'AdminExerciseController@reopenExercisesBatch', ['admin']);
$router->post('/admin/exercises/{id}/moderate', 'AdminExerciseController@moderateExercise', ['admin']);
$router->get('/admin/exercises/{id}', 'AdminExerciseController@showExercise', ['admin']);
$router->post('/admin/exercises/{id}/publications/batch-close', 'AdminExerciseController@closeExercisePublicationsBatch', ['admin']);
$router->post('/admin/exercises/{id}/publications/batch-reopen', 'AdminExerciseController@reopenExercisePublicationsBatch', ['admin']);
$router->post('/admin/exercises/{id}/publications/{turmaId}', 'AdminExerciseController@updateExercisePublication', ['admin']);
$router->post('/admin/exercises/{id}/close', 'AdminExerciseController@closeExercise', ['admin']);
$router->post('/admin/exercises/{id}/reopen', 'AdminExerciseController@reopenExercise', ['admin']);
$router->post('/admin/exercises/{id}/publications/{turmaId}/close', 'AdminExerciseController@closeExercisePublication', ['admin']);
$router->post('/admin/exercises/{id}/publications/{turmaId}/reopen', 'AdminExerciseController@reopenExercisePublication', ['admin']);
$router->post('/admin/questions/{id}/moderate', 'AdminExerciseController@moderateQuestion', ['admin']);
$router->get('/admin/attempts/pending', 'AttemptController@pendingAdmin', ['admin']);
$router->get('/admin/attempts/{id}/result', 'AttemptController@resultAdmin', ['admin']);
$router->post('/admin/attempts/{id}/regrade', 'AttemptController@regradeAdmin', ['admin']);
$router->get('/admin/teacher-requests', 'AdminTeacherRequestController@teacherRequests', ['admin']);
$router->get('/admin/teacher-requests/history', 'AdminTeacherRequestController@teacherRequestHistory', ['admin']);
$router->post('/admin/teacher-requests/{id}/approve', 'AdminTeacherRequestController@approveTeacherRequest', ['admin']);
$router->post('/admin/teacher-requests/{id}/reject', 'AdminTeacherRequestController@rejectTeacherRequest', ['admin']);
$router->get('/admin/audit/export', 'AdminAuditController@exportAudit', ['admin']);
$router->get('/admin/audit/export.json', 'AdminAuditController@exportAuditJson', ['admin']);
$router->get('/admin/audit',     'AdminAuditController@audit', ['admin']);

// Turmas
$router->get('/teacher/turmas',                                   'TurmaController@index', ['teacher']);
$router->get('/teacher/turmas/create',                            'TurmaController@create', ['teacher']);
$router->post('/teacher/turmas',                                  'TurmaController@store', ['teacher']);
$router->get('/teacher/turmas/{id}',                              'TurmaController@show', ['teacher']);
$router->post('/teacher/turmas/{id}/key',                         'TurmaController@regenerateKey', ['teacher']);
$router->post('/teacher/turmas/{id}/approve/{studentId}',         'TurmaController@approveStudent', ['teacher']);
$router->post('/teacher/turmas/{id}/reject/{studentId}',          'TurmaController@rejectStudent', ['teacher']);

// Exercises
$router->get('/teacher/exercises',             'ExerciseController@index', ['teacher']);
$router->get('/teacher/exercises/create',      'ExerciseController@create', ['teacher']);
$router->post('/teacher/exercises',            'ExerciseController@store', ['teacher']);
$router->get('/teacher/exercises/{id}',        'ExerciseController@show', ['teacher']);
$router->get('/teacher/exercises/{id}/edit',   'ExerciseController@edit', ['teacher']);
$router->post('/teacher/exercises/{id}',       'ExerciseController@update', ['teacher']);
$router->post('/teacher/exercises/{id}/complete', 'ExerciseController@complete', ['teacher']);
$router->post('/teacher/exercises/{id}/activate', 'ExerciseController@activate', ['teacher']);
$router->post('/teacher/exercises/{id}/delete', 'ExerciseController@destroy', ['teacher']);

// Questions
$router->get('/teacher/exercises/{exerciseId}/questions/create', 'QuestionController@create', ['teacher']);
$router->post('/teacher/exercises/{exerciseId}/questions',       'QuestionController@store', ['teacher']);
$router->post('/teacher/questions/{id}/delete',                  'QuestionController@destroy', ['teacher']);

// Students list
$router->get('/teacher/students', 'StudentController@index', ['teacher']);
$router->post('/teacher/students/{id}/detach', 'StudentController@destroy', ['teacher']);
$router->get('/teacher/attempts/pending', 'AttemptController@pendingTeacher', ['teacher']);
$router->get('/teacher/attempts/{id}/result', 'AttemptController@resultTeacher', ['teacher']);
$router->post('/teacher/attempts/{id}/regrade',     'AttemptController@regradeTeacher', ['teacher']);

// ── Student ───────────────────────────────────────────────────────────────────
$router->get('/student/dashboard',                  'DashboardController@student', ['student']);
$router->get('/student/exercises',                  'ExerciseController@studentIndex', ['student']);
$router->get('/student/exercises/{id}',             'ExerciseController@studentShow', ['student']);
$router->post('/student/exercises/{id}/start',      'AttemptController@start', ['student']);
$router->post('/student/attempts/{id}/answer',      'AttemptController@saveAnswer', ['student']);
$router->post('/student/attempts/{id}/submit',      'AttemptController@submit', ['student']);
$router->get('/student/attempts/{id}/result',       'AttemptController@result', ['student']);
$router->post('/student/turma/join',                'TurmaController@join', ['student']);

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
$router->get('/password/change', 'AuthController@showChangePassword');
$router->post('/password/change', 'AuthController@changePassword');
$router->get('/password/reset', 'AuthController@showResetPassword');
$router->post('/password/reset', 'AuthController@resetPassword');
$router->post('/logout',   'AuthController@logout');

// ── Conta (perfil próprio) ───────────────────────────────────────────────────
$router->get('/conta',               'AccountController@show');
$router->post('/conta/foto',         'AccountController@uploadAvatar');
$router->post('/conta/foto/remover', 'AccountController@deleteAvatar');

// ── Teacher ──────────────────────────────────────────────────────────────────
$router->get('/teacher/dashboard', 'DashboardController@teacher');

// ── Admin ───────────────────────────────────────────────────────────────────
$router->get('/admin/dashboard', 'AdminDashboardController@dashboard');
$router->post('/admin/presets/{scope}/save', 'AdminDashboardController@saveFilterPreset');
$router->post('/admin/presets/{scope}/delete', 'AdminDashboardController@deleteFilterPreset');
$router->post('/admin/grading-jobs/{id}/retry', 'AdminDashboardController@retryGradingJob');
$router->post('/admin/settings/teacher-registration', 'AdminDashboardController@toggleTeacherRegistration');
$router->get('/admin/users',     'AdminUserController@users');
$router->get('/admin/users/export', 'AdminUserController@exportUsers');
$router->get('/admin/users/export.json', 'AdminUserController@exportUsersJson');
$router->post('/admin/users/batch-activate', 'AdminUserController@activateUsersBatch');
$router->post('/admin/users/batch-deactivate', 'AdminUserController@deactivateUsersBatch');
$router->get('/admin/users/{id}', 'AdminUserController@showUser');
$router->get('/admin/users/{id}/edit', 'AdminUserController@editUser');
$router->post('/admin/users/{id}', 'AdminUserController@updateUser');
$router->post('/admin/users/{id}/status', 'AdminUserController@updateUserStatus');
$router->post('/admin/users/{id}/reset-password', 'AdminUserController@resetUserPassword');
$router->get('/admin/turmas',    'AdminTurmaController@turmas');
$router->get('/admin/turmas/export', 'AdminTurmaController@exportTurmas');
$router->get('/admin/turmas/export.json', 'AdminTurmaController@exportTurmasJson');
$router->post('/admin/turmas/batch-deactivate', 'AdminTurmaController@deactivateTurmasBatch');
$router->post('/admin/turmas/batch-reactivate', 'AdminTurmaController@reactivateTurmasBatch');
$router->get('/admin/turmas/{id}', 'AdminTurmaController@showTurma');
$router->post('/admin/turmas/{id}/publications/batch-close', 'AdminTurmaController@closeTurmaPublicationsBatch');
$router->post('/admin/turmas/{id}/publications/batch-reopen', 'AdminTurmaController@reopenTurmaPublicationsBatch');
$router->post('/admin/turmas/{id}/deactivate', 'AdminTurmaController@deactivateTurma');
$router->post('/admin/turmas/{id}/reactivate', 'AdminTurmaController@reactivateTurma');
$router->get('/admin/exercises', 'AdminExerciseController@exercises');
$router->get('/admin/exercises/export', 'AdminExerciseController@exportExercises');
$router->get('/admin/exercises/export.json', 'AdminExerciseController@exportExercisesJson');
$router->post('/admin/exercises/batch-close', 'AdminExerciseController@closeExercisesBatch');
$router->post('/admin/exercises/batch-reopen', 'AdminExerciseController@reopenExercisesBatch');
$router->post('/admin/exercises/{id}/moderate', 'AdminExerciseController@moderateExercise');
$router->get('/admin/exercises/{id}', 'AdminExerciseController@showExercise');
$router->post('/admin/exercises/{id}/publications/batch-close', 'AdminExerciseController@closeExercisePublicationsBatch');
$router->post('/admin/exercises/{id}/publications/batch-reopen', 'AdminExerciseController@reopenExercisePublicationsBatch');
$router->post('/admin/exercises/{id}/publications/{turmaId}', 'AdminExerciseController@updateExercisePublication');
$router->post('/admin/exercises/{id}/close', 'AdminExerciseController@closeExercise');
$router->post('/admin/exercises/{id}/reopen', 'AdminExerciseController@reopenExercise');
$router->post('/admin/exercises/{id}/publications/{turmaId}/close', 'AdminExerciseController@closeExercisePublication');
$router->post('/admin/exercises/{id}/publications/{turmaId}/reopen', 'AdminExerciseController@reopenExercisePublication');
$router->post('/admin/questions/{id}/moderate', 'AdminExerciseController@moderateQuestion');
$router->get('/admin/attempts/pending', 'AttemptController@pendingAdmin');
$router->get('/admin/attempts/{id}/result', 'AttemptController@resultAdmin');
$router->post('/admin/attempts/{id}/regrade', 'AttemptController@regradeAdmin');
$router->get('/admin/teacher-requests', 'AdminTeacherRequestController@teacherRequests');
$router->get('/admin/teacher-requests/history', 'AdminTeacherRequestController@teacherRequestHistory');
$router->post('/admin/teacher-requests/{id}/approve', 'AdminTeacherRequestController@approveTeacherRequest');
$router->post('/admin/teacher-requests/{id}/reject', 'AdminTeacherRequestController@rejectTeacherRequest');
$router->get('/admin/audit/export', 'AdminAuditController@exportAudit');
$router->get('/admin/audit/export.json', 'AdminAuditController@exportAuditJson');
$router->get('/admin/audit',     'AdminAuditController@audit');

// Turmas
$router->get('/teacher/turmas',                                   'TurmaController@index');
$router->get('/teacher/turmas/create',                            'TurmaController@create');
$router->post('/teacher/turmas',                                  'TurmaController@store');
$router->get('/teacher/turmas/{id}',                              'TurmaController@show');
$router->post('/teacher/turmas/{id}/key',                         'TurmaController@regenerateKey');
$router->post('/teacher/turmas/{id}/approve/{studentId}',         'TurmaController@approveStudent');
$router->post('/teacher/turmas/{id}/reject/{studentId}',          'TurmaController@rejectStudent');

// Exercises
$router->get('/teacher/exercises',             'ExerciseController@index');
$router->get('/teacher/exercises/create',      'ExerciseController@create');
$router->post('/teacher/exercises',            'ExerciseController@store');
$router->get('/teacher/exercises/{id}',        'ExerciseController@show');
$router->get('/teacher/exercises/{id}/edit',   'ExerciseController@edit');
$router->post('/teacher/exercises/{id}',       'ExerciseController@update');
$router->post('/teacher/exercises/{id}/complete', 'ExerciseController@complete');
$router->post('/teacher/exercises/{id}/activate', 'ExerciseController@activate');
$router->post('/teacher/exercises/{id}/delete', 'ExerciseController@destroy');

// Questions
$router->get('/teacher/exercises/{exerciseId}/questions/create', 'QuestionController@create');
$router->post('/teacher/exercises/{exerciseId}/questions',       'QuestionController@store');
$router->post('/teacher/questions/{id}/delete',                  'QuestionController@destroy');

// Students list
$router->get('/teacher/students', 'StudentController@index');
$router->post('/teacher/students/{id}/detach', 'StudentController@destroy');
$router->get('/teacher/attempts/pending', 'AttemptController@pendingTeacher');
$router->get('/teacher/attempts/{id}/result', 'AttemptController@resultTeacher');
$router->post('/teacher/attempts/{id}/regrade',     'AttemptController@regradeTeacher');

// ── Student ───────────────────────────────────────────────────────────────────
$router->get('/student/dashboard',                  'DashboardController@student');
$router->get('/student/exercises',                  'ExerciseController@studentIndex');
$router->get('/student/exercises/{id}',             'ExerciseController@studentShow');
$router->post('/student/exercises/{id}/start',      'AttemptController@start');
$router->post('/student/attempts/{id}/answer',      'AttemptController@saveAnswer');
$router->post('/student/attempts/{id}/submit',      'AttemptController@submit');
$router->get('/student/attempts/{id}/result',       'AttemptController@result');
$router->post('/student/turma/join',                'TurmaController@join');

<?php

declare(strict_types=1);

/** @var Core\Router $router */

// ── Public ──────────────────────────────────────────────────────────────────
$router->get('/',          'AuthController@showLogin');
$router->get('/login',     'AuthController@showLogin');
$router->post('/login',    'AuthController@login');
$router->get('/register',  'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/password/change', 'AuthController@showChangePassword');
$router->post('/password/change', 'AuthController@changePassword');
$router->post('/logout',   'AuthController@logout');

// ── Teacher ──────────────────────────────────────────────────────────────────
$router->get('/teacher/dashboard', 'DashboardController@teacher');

// ── Admin ───────────────────────────────────────────────────────────────────
$router->get('/admin/dashboard', 'AdminController@dashboard');
$router->post('/admin/presets/{scope}/save', 'AdminController@saveFilterPreset');
$router->post('/admin/presets/{scope}/delete', 'AdminController@deleteFilterPreset');
$router->get('/admin/users',     'AdminController@users');
$router->get('/admin/users/export', 'AdminController@exportUsers');
$router->get('/admin/users/export.json', 'AdminController@exportUsersJson');
$router->post('/admin/users/batch-activate', 'AdminController@activateUsersBatch');
$router->post('/admin/users/batch-deactivate', 'AdminController@deactivateUsersBatch');
$router->get('/admin/users/{id}', 'AdminController@showUser');
$router->get('/admin/users/{id}/edit', 'AdminController@editUser');
$router->post('/admin/users/{id}', 'AdminController@updateUser');
$router->post('/admin/users/{id}/status', 'AdminController@updateUserStatus');
$router->post('/admin/users/{id}/reset-password', 'AdminController@resetUserPassword');
$router->get('/admin/turmas',    'AdminController@turmas');
$router->get('/admin/turmas/export', 'AdminController@exportTurmas');
$router->get('/admin/turmas/export.json', 'AdminController@exportTurmasJson');
$router->post('/admin/turmas/batch-deactivate', 'AdminController@deactivateTurmasBatch');
$router->post('/admin/turmas/batch-reactivate', 'AdminController@reactivateTurmasBatch');
$router->get('/admin/turmas/{id}', 'AdminController@showTurma');
$router->post('/admin/turmas/{id}/publications/batch-close', 'AdminController@closeTurmaPublicationsBatch');
$router->post('/admin/turmas/{id}/publications/batch-reopen', 'AdminController@reopenTurmaPublicationsBatch');
$router->post('/admin/turmas/{id}/deactivate', 'AdminController@deactivateTurma');
$router->post('/admin/turmas/{id}/reactivate', 'AdminController@reactivateTurma');
$router->get('/admin/exercises', 'AdminController@exercises');
$router->get('/admin/exercises/export', 'AdminController@exportExercises');
$router->get('/admin/exercises/export.json', 'AdminController@exportExercisesJson');
$router->post('/admin/exercises/batch-close', 'AdminController@closeExercisesBatch');
$router->post('/admin/exercises/batch-reopen', 'AdminController@reopenExercisesBatch');
$router->post('/admin/exercises/{id}/moderate', 'AdminController@moderateExercise');
$router->get('/admin/exercises/{id}', 'AdminController@showExercise');
$router->post('/admin/exercises/{id}/publications/batch-close', 'AdminController@closeExercisePublicationsBatch');
$router->post('/admin/exercises/{id}/publications/batch-reopen', 'AdminController@reopenExercisePublicationsBatch');
$router->post('/admin/exercises/{id}/publications/{turmaId}', 'AdminController@updateExercisePublication');
$router->post('/admin/exercises/{id}/close', 'AdminController@closeExercise');
$router->post('/admin/exercises/{id}/reopen', 'AdminController@reopenExercise');
$router->post('/admin/exercises/{id}/publications/{turmaId}/close', 'AdminController@closeExercisePublication');
$router->post('/admin/exercises/{id}/publications/{turmaId}/reopen', 'AdminController@reopenExercisePublication');
$router->post('/admin/questions/{id}/moderate', 'AdminController@moderateQuestion');
$router->get('/admin/audit/export', 'AdminController@exportAudit');
$router->get('/admin/audit/export.json', 'AdminController@exportAuditJson');
$router->get('/admin/audit',     'AdminController@audit');

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
$router->post('/teacher/students/{id}/delete', 'StudentController@destroy');

// ── Student ───────────────────────────────────────────────────────────────────
$router->get('/student/dashboard',                  'DashboardController@student');
$router->get('/student/exercises',                  'ExerciseController@studentIndex');
$router->get('/student/exercises/{id}',             'ExerciseController@studentShow');
$router->post('/student/exercises/{id}/start',      'AttemptController@start');
$router->post('/student/attempts/{id}/answer',      'AttemptController@saveAnswer');
$router->post('/student/attempts/{id}/submit',      'AttemptController@submit');
$router->get('/student/attempts/{id}/result',       'AttemptController@result');
$router->post('/student/turma/join',                'TurmaController@join');

<?php

declare(strict_types=1);

/** @var Core\Router $router */

// ── Public ──────────────────────────────────────────────────────────────────
$router->get('/',          'AuthController@showLogin');
$router->get('/login',     'AuthController@showLogin');
$router->post('/login',    'AuthController@login');
$router->get('/register',  'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->post('/logout',   'AuthController@logout');

// ── Teacher ──────────────────────────────────────────────────────────────────
$router->get('/teacher/dashboard', 'DashboardController@teacher');

// ── Admin ───────────────────────────────────────────────────────────────────
$router->get('/admin/dashboard', 'AdminController@dashboard');

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

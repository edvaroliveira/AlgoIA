<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PracticeAnswer;
use App\Services\OpenAIService;
use Core\Auth;
use Core\Request;
use Core\View;

/** Modo prática do Aluno: Questões do Banco de exercícios, sem nota, com Correção na hora. */
class PracticeController
{
  private const MAX_ANSWER_LENGTH = 10000;

  private PracticeAnswer $practice;

  public function __construct()
  {
    $this->practice = new PracticeAnswer();
  }

  /** GET /student/pratica — Temas e correções usadas hoje. */
  public function index(): void
  {
    $studentId = (int) Auth::id();
    View::render('student/practice/index', [
      'themes'    => $this->practice->themesForStudent($studentId),
      'usedToday' => $this->practice->countToday($studentId),
      'limit'     => PracticeAnswer::DAILY_LIMIT,
    ]);
  }

  /** GET /student/pratica/sortear?tema= — leva a uma Questão do Tema. */
  public function draw(): void
  {
    global $session;
    $theme = mb_substr(trim((string) Request::get('tema', '')), 0, 60);
    $questionId = $theme !== '' ? $this->practice->drawQuestion((int) Auth::id(), $theme) : false;

    if ($questionId === false) {
      $session->flash('error', 'Nenhuma Questão disponível neste Tema.');
      View::redirect('/student/pratica');
    }
    View::redirect("/student/pratica/questao/{$questionId}?tema=" . rawurlencode($theme));
  }

  /** GET /student/pratica/questao/{id} — enunciado, resposta e correções anteriores. */
  public function show(string $id): void
  {
    global $session;
    $studentId = (int) Auth::id();
    $question = $this->practice->findActiveQuestion((int) $id);
    if (!$question) {
      Auth::deny('Questão não encontrada.', 404);
    }

    View::render('student/practice/show', [
      'question'  => $question,
      'history'   => $this->practice->historyFor($studentId, (int) $id),
      'draft'     => (string) $session->getFlash('practice_answer', ''),
      'theme'     => mb_substr(trim((string) Request::get('tema', '')), 0, 60),
      'usedToday' => $this->practice->countToday($studentId),
      'limit'     => PracticeAnswer::DAILY_LIMIT,
    ]);
  }

  /** POST /student/pratica/questao/{id} — corrige na hora, dentro do limite diário. */
  public function answer(string $id): void
  {
    global $session;
    Request::validateCsrf();

    $studentId = (int) Auth::id();
    $question = $this->practice->findActiveQuestion((int) $id);
    if (!$question) {
      Auth::deny('Questão não encontrada.', 404);
    }

    $theme = mb_substr(trim((string) Request::post('tema', '')), 0, 60);
    $back = "/student/pratica/questao/{$id}" . ($theme !== '' ? '?tema=' . rawurlencode($theme) : '');
    $answer = trim(Request::text('answer'));

    if ($answer === '') {
      $session->flash('error', 'Escreva sua resposta antes de enviar.');
      View::redirect($back);
    }
    if (mb_strlen($answer) > self::MAX_ANSWER_LENGTH) {
      $session->flash('error', 'Resposta muito longa (máximo ' . self::MAX_ANSWER_LENGTH . ' caracteres).');
      $session->flash('practice_answer', mb_substr($answer, 0, self::MAX_ANSWER_LENGTH));
      View::redirect($back);
    }
    if ($this->practice->countToday($studentId) >= PracticeAnswer::DAILY_LIMIT) {
      $session->flash('error', 'Você atingiu o limite de ' . PracticeAnswer::DAILY_LIMIT . ' correções de prática por dia. Volte amanhã.');
      $session->flash('practice_answer', $answer);
      View::redirect($back);
    }

    // Uma Questão só; a chamada à IA pode levar alguns segundos.
    set_time_limit(120);

    try {
      $result = (new OpenAIService())->evaluateAnswer(
        (string) $question['text'],
        (string) $question['expected_answer_hint'],
        $answer,
        (float) $question['max_score'],
        null,
        $studentId
      );
      $this->practice->record($studentId, (int) $id, $answer, $result, (float) $question['max_score']);
      $session->flash('success', 'Resposta corrigida.');
    } catch (\Throwable $e) {
      error_log('practice grading failed: ' . $e->getMessage());
      $session->flash('error', 'Não foi possível corrigir agora. Sua resposta foi mantida; tente enviar de novo em instantes.');
      $session->flash('practice_answer', $answer);
    }

    View::redirect($back);
  }
}

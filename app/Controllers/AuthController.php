<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\Turma;
use Core\Auth;
use Core\Request;
use Core\View;

class AuthController
{
  private User $users;

  public function __construct()
  {
    $this->users = new User();
  }

  public function showLogin(): void
  {
    if (Auth::check()) {
      $this->redirectByRole();
    }
    View::render('auth/login', [], 'layouts/guest');
  }

  public function login(): void
  {
    Request::validateCsrf();

    $email    = Request::email('email');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
      View::render('auth/login', ['error' => 'Preencha todos os campos.'], 'layouts/guest');
      return;
    }

    $user = $this->users->findByEmail($email);

    if (!$user || !$this->users->verifyPassword($password, $user['password_hash'])) {
      View::render('auth/login', ['error' => 'E-mail ou senha incorretos.'], 'layouts/guest');
      return;
    }

    if ($user['status'] === 'pending') {
      View::render('auth/login', ['error' => 'Seu cadastro aguarda aprovação do docente.'], 'layouts/guest');
      return;
    }

    if ($user['status'] === 'inactive') {
      View::render('auth/login', ['error' => 'Conta desativada. Entre em contato com o docente.'], 'layouts/guest');
      return;
    }

    Auth::login($user);
    $this->redirectByRole();
  }

  public function showRegister(): void
  {
    if (Auth::check()) {
      $this->redirectByRole();
    }
    View::render('auth/register', [], 'layouts/guest');
  }

  public function register(): void
  {
    Request::validateCsrf();

    $name          = Request::str('name');
    $email         = Request::email('email');
    $password      = (string) ($_POST['password'] ?? '');
    $passwordConf  = (string) ($_POST['password_confirm'] ?? '');
    $turmaKey      = strtoupper(trim((string) ($_POST['turma_key'] ?? '')));

    $errors = [];

    if (mb_strlen($name) < 3) {
      $errors[] = 'Nome deve ter pelo menos 3 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'E-mail inválido.';
    }
    if (strlen($password) < 8) {
      $errors[] = 'Senha deve ter pelo menos 8 caracteres.';
    }
    if ($password !== $passwordConf) {
      $errors[] = 'As senhas não coincidem.';
    }
    if (strlen($turmaKey) !== 6) {
      $errors[] = 'Chave da turma deve ter exatamente 6 caracteres.';
    }

    if ($errors) {
      View::render('auth/register', [
        'errors' => $errors,
        'old'    => compact('name', 'email', 'turmaKey'),
      ], 'layouts/guest');
      return;
    }

    $turmaModel = new Turma();
    $turma      = $turmaModel->findByKey($turmaKey);

    if (!$turma) {
      View::render('auth/register', [
        'errors' => ['Chave da turma não encontrada ou inativa.'],
        'old'    => compact('name', 'email', 'turmaKey'),
      ], 'layouts/guest');
      return;
    }

    if ($this->users->findByEmail($email)) {
      View::render('auth/register', [
        'errors' => ['Este e-mail já está cadastrado.'],
        'old'    => compact('name', 'email', 'turmaKey'),
      ], 'layouts/guest');
      return;
    }

    $userId = $this->users->create($name, $email, $password, 'student', 'pending');
    $turmaModel->enrollStudent($userId, (int) $turma['id']);

    View::render('auth/register', [
      'success' => 'Cadastro realizado com sucesso! Aguarde a aprovação do docente para acessar a plataforma.',
    ], 'layouts/guest');
  }

  public function logout(): void
  {
    Auth::logout();
    View::redirect('/login');
  }

  private function redirectByRole(): never
  {
    if (Auth::isTeacher()) {
      View::redirect('/teacher/dashboard');
    }
    View::redirect('/student/dashboard');
  }
}

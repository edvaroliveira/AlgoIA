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
  private const LOGIN_MAX_ATTEMPTS = 5;
  private const LOGIN_LOCK_SECONDS = 300;

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

    global $session;

    if ($this->isLoginLocked()) {
      View::render('auth/login', [
        'error' => 'Muitas tentativas de login. Aguarde alguns minutos antes de tentar novamente.',
        'old'   => ['email' => Request::email('email')],
      ], 'layouts/guest');
      return;
    }

    $email    = Request::email('email');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
      View::render('auth/login', [
        'error' => 'Preencha todos os campos.',
        'old'   => ['email' => $email],
      ], 'layouts/guest');
      return;
    }

    $user = $this->users->findByEmail($email);

    if (!$user || !$this->users->verifyPassword($password, $user['password_hash'])) {
      $this->recordFailedLogin();
      View::render('auth/login', [
        'error' => 'E-mail ou senha incorretos.',
        'old'   => ['email' => $email],
      ], 'layouts/guest');
      return;
    }

    if ($user['status'] === 'pending') {
      View::render('auth/login', [
        'error' => 'Seu cadastro aguarda aprovação do docente.',
        'old'   => ['email' => $email],
      ], 'layouts/guest');
      return;
    }

    if ($user['status'] === 'inactive') {
      View::render('auth/login', [
        'error' => 'Conta desativada. Entre em contato com o docente.',
        'old'   => ['email' => $email],
      ], 'layouts/guest');
      return;
    }

    $this->clearLoginThrottle();
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
    if (!$this->isStrongPassword($password)) {
      $errors[] = 'Senha deve ter ao menos 10 caracteres, com letra maiúscula, minúscula e número.';
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
    Request::validateCsrf();
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

  private function isStrongPassword(string $password): bool
  {
    return strlen($password) >= 10
      && preg_match('/[A-Z]/', $password) === 1
      && preg_match('/[a-z]/', $password) === 1
      && preg_match('/\d/', $password) === 1;
  }

  private function isLoginLocked(): bool
  {
    global $session;
    $lockUntil = (int) $session->get('login_lock_until', 0);

    if ($lockUntil <= time()) {
      if ($lockUntil > 0) {
        $this->clearLoginThrottle();
      }
      return false;
    }

    return true;
  }

  private function recordFailedLogin(): void
  {
    global $session;

    $attempts = (int) $session->get('login_attempts', 0) + 1;
    $session->set('login_attempts', $attempts);

    if ($attempts >= self::LOGIN_MAX_ATTEMPTS) {
      $session->set('login_lock_until', time() + self::LOGIN_LOCK_SECONDS);
    }
  }

  private function clearLoginThrottle(): void
  {
    global $session;
    $session->remove('login_attempts');
    $session->remove('login_lock_until');
  }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\Turma;
use App\Models\SystemSetting;
use Core\Auth;
use Core\Request;
use Core\View;

class AuthController
{
  private const LOGIN_MAX_ATTEMPTS = 5;
  private const LOGIN_LOCK_SECONDS = 300;
  private const TEACHER_REG_MAX_ATTEMPTS = 5;
  private const TEACHER_REG_LOCK_SECONDS = 600;

  private User $users;

  public function __construct()
  {
    $this->users = new User();
  }

  public function showLogin(): void
  {
    if (Auth::check()) {
      if (Auth::mustChangePassword()) {
        View::redirect('/password/change');
      }
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
      $pendingMessage = ($user['role'] ?? '') === 'teacher'
        ? 'Seu cadastro aguarda aprovação do administrador.'
        : 'Seu cadastro aguarda aprovação do docente.';
      View::render('auth/login', [
        'error' => $pendingMessage,
        'old'   => ['email' => $email],
      ], 'layouts/guest');
      return;
    }

    if ($user['status'] === 'rejected') {
      View::render('auth/login', [
        'error' => 'Sua solicitação de acesso não foi aprovada. Entre em contato com a administração.',
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
    if (Auth::mustChangePassword()) {
      View::redirect('/password/change');
    }
    $this->redirectByRole();
  }

  public function showChangePassword(): void
  {
    Auth::requireAuth();

    if (!Auth::mustChangePassword()) {
      $this->redirectByRole();
    }

    View::render('auth/change_password', [], 'layouts/guest');
  }

  public function changePassword(): void
  {
    Auth::requireAuth();
    Request::validateCsrf();

    if (!Auth::mustChangePassword()) {
      $this->redirectByRole();
    }

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConf = (string) ($_POST['password_confirm'] ?? '');
    $user = $this->users->find((int) Auth::id());
    $errors = [];

    if (!$user || !$this->users->verifyPassword($currentPassword, $user['password_hash'])) {
      $errors[] = 'Senha temporária incorreta.';
    }
    if (!$this->isStrongPassword($password)) {
      $errors[] = 'Nova senha deve ter ao menos 10 caracteres, com letra maiúscula, minúscula e número.';
    }
    if ($password !== $passwordConf) {
      $errors[] = 'As senhas não coincidem.';
    }
    if ($currentPassword !== '' && $password !== '' && hash_equals($currentPassword, $password)) {
      $errors[] = 'A nova senha deve ser diferente da senha temporária.';
    }

    if ($errors) {
      View::render('auth/change_password', ['errors' => $errors], 'layouts/guest');
      return;
    }

    $this->users->updatePassword((int) Auth::id(), $password);
    Auth::clearMustChangePassword();

    \App\Services\AuditService::record('auth.password_change_required_completed', 'user', (int) Auth::id());

    global $session;
    $session->flash('success', 'Senha atualizada com sucesso.');
    $this->redirectByRole();
  }

  public function showResetPassword(): void
  {
    $token = trim((string) Request::get('token', ''));
    $user = $this->users->findByValidPasswordResetToken($token);

    View::render('auth/reset_password', [
      'token' => $token,
      'validToken' => $user !== false,
    ], 'layouts/guest');
  }

  public function resetPassword(): void
  {
    Request::validateCsrf();

    $token = trim((string) Request::post('token', ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConf = (string) ($_POST['password_confirm'] ?? '');
    $user = $this->users->findByValidPasswordResetToken($token);
    $errors = [];

    if (!$user) {
      $errors[] = 'Link de redefinição inválido ou expirado.';
    }
    if (!$this->isStrongPassword($password)) {
      $errors[] = 'Nova senha deve ter ao menos 10 caracteres, com letra maiúscula, minúscula e número.';
    }
    if ($password !== $passwordConf) {
      $errors[] = 'As senhas não coincidem.';
    }

    if ($errors) {
      View::render('auth/reset_password', [
        'token' => $token,
        'validToken' => $user !== false,
        'errors' => $errors,
      ], 'layouts/guest');
      return;
    }

    $this->users->updatePassword((int) $user['id'], $password);
    \App\Services\AuditService::record('auth.password_reset_token_completed', 'user', (int) $user['id']);

    global $session;
    $session->flash('success', 'Senha redefinida com sucesso. Faça login com a nova senha.');
    View::redirect('/login');
  }

  public function showRegister(): void
  {
    if (Auth::check()) {
      if (Auth::mustChangePassword()) {
        View::redirect('/password/change');
      }
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

  public function showRegisterTeacher(): void
  {
    if (Auth::check()) {
      if (Auth::mustChangePassword()) {
        View::redirect('/password/change');
      }
      $this->redirectByRole();
    }

    $settings = new SystemSetting();
    if (!$settings->getBool('teacher_registration_enabled')) {
      View::render('auth/register_teacher', ['disabled' => true], 'layouts/guest');
      return;
    }

    View::render('auth/register_teacher', [], 'layouts/guest');
  }

  public function registerTeacher(): void
  {
    Request::validateCsrf();

    $settings = new SystemSetting();
    if (!$settings->getBool('teacher_registration_enabled')) {
      View::render('auth/register_teacher', ['disabled' => true], 'layouts/guest');
      return;
    }

    global $session;

    if ($this->isTeacherRegLocked()) {
      View::render('auth/register_teacher', [
        'error' => 'Muitas tentativas de cadastro. Aguarde alguns minutos antes de tentar novamente.',
        'old'   => ['name' => Request::str('name'), 'email' => Request::email('email'), 'institution' => Request::str('institution')],
      ], 'layouts/guest');
      return;
    }

    $name        = Request::str('name');
    $email       = Request::email('email');
    $password    = (string) ($_POST['password'] ?? '');
    $passwordConf = (string) ($_POST['password_confirm'] ?? '');
    $institution = mb_substr(trim((string) ($_POST['institution'] ?? '')), 0, 500);

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
    if ($institution === '') {
      $errors[] = 'Informe sua instituição ou justificativa para o acesso.';
    }

    if ($errors) {
      $this->recordFailedTeacherReg();
      View::render('auth/register_teacher', [
        'errors' => $errors,
        'old'    => compact('name', 'email', 'institution'),
      ], 'layouts/guest');
      return;
    }

    if ($this->users->findByEmail($email)) {
      $this->recordFailedTeacherReg();
      View::render('auth/register_teacher', [
        'errors' => ['Este e-mail já está associado a uma conta.'],
        'old'    => compact('name', 'email', 'institution'),
      ], 'layouts/guest');
      return;
    }

    $newUserId = $this->users->create($name, $email, $password, 'teacher', 'pending', $institution);
    $this->clearTeacherRegThrottle();

    \App\Services\AuditService::record('auth.teacher_registration_request', 'user', $newUserId, [
      'email' => $email,
      'name' => $name,
    ]);

    View::render('auth/register_teacher', [
      'success' => 'Solicitação enviada! Sua conta será analisada pela administração. Você será notificado sobre a decisão.',
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
    $role = Auth::user()['role'] ?? null;

    if ($role === 'admin') {
      View::redirect('/admin/dashboard');
    }
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

  private function isTeacherRegLocked(): bool
  {
    global $session;
    $lockUntil = (int) $session->get('teacher_reg_lock_until', 0);

    if ($lockUntil <= time()) {
      if ($lockUntil > 0) {
        $this->clearTeacherRegThrottle();
      }
      return false;
    }

    return true;
  }

  private function recordFailedTeacherReg(): void
  {
    global $session;

    $attempts = (int) $session->get('teacher_reg_attempts', 0) + 1;
    $session->set('teacher_reg_attempts', $attempts);

    if ($attempts >= self::TEACHER_REG_MAX_ATTEMPTS) {
      $session->set('teacher_reg_lock_until', time() + self::TEACHER_REG_LOCK_SECONDS);
    }
  }

  private function clearTeacherRegThrottle(): void
  {
    global $session;
    $session->remove('teacher_reg_attempts');
    $session->remove('teacher_reg_lock_until');
  }
}

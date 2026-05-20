<?php

declare(strict_types=1);

namespace App\Models;

class User extends Model
{
  protected string $table = 'users';

  public function findByEmail(string $email): array|false
  {
    return $this->db->fetchOne(
      "SELECT * FROM users WHERE email = ?",
      [$email]
    );
  }

  public function create(
    string $name,
    string $email,
    string $password,
    string $role   = 'student',
    string $status = 'pending'
  ): int {
    return $this->db->insert(
      "INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)",
      [$name, $email, password_hash($password, PASSWORD_BCRYPT), $role, $status]
    );
  }

  public function verifyPassword(string $password, string $hash): bool
  {
    return password_verify($password, $hash);
  }

  public function updateStatus(int $id, string $status): void
  {
    $this->db->execute(
      "UPDATE users SET status = ? WHERE id = ?",
      [$status, $id]
    );
  }

  public function updatePassword(int $id, string $newPassword): void
  {
    $this->db->execute(
      "UPDATE users SET password_hash = ? WHERE id = ?",
      [password_hash($newPassword, PASSWORD_BCRYPT), $id]
    );
  }

  public function updateProfile(int $id, string $name, string $email): void
  {
    $this->db->execute(
      "UPDATE users SET name = ?, email = ? WHERE id = ?",
      [$name, $email, $id]
    );
  }

  public function getAllStudents(): array
  {
    return $this->db->fetchAll(
      "SELECT * FROM users WHERE role = 'student' ORDER BY name"
    );
  }

  public function emailExistsForOther(string $email, int $excludeId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM users WHERE email = ? AND id != ?",
      [$email, $excludeId]
    );
    return $row !== false;
  }
}

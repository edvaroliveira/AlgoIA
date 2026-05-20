<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

abstract class Model
{
  protected Database $db;
  protected string $table;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  public function find(int $id): array|false
  {
    return $this->db->fetchOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
  }

  public function delete(int $id): int
  {
    return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
  }
}

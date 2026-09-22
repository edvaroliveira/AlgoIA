<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

abstract class Model
{
  protected Database $db;
  protected string $table;

  public function __construct(?Database $db = null)
  {
    $this->db = $db ?? Database::getInstance();
  }

  public function find(int $id): array|false
  {
    return $this->db->fetchOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
  }

  public function delete(int $id): int
  {
    return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
  }

  /**
   * Proxies de transação — permitem que um controller componha uma checagem
   * bloqueante (ex.: contagem com FOR UPDATE) e uma escrita como uma única
   * operação atômica, sem expor a instância de Database.
   */
  public function beginTransaction(): void
  {
    $this->db->beginTransaction();
  }

  public function commit(): void
  {
    $this->db->commit();
  }

  public function rollback(): void
  {
    $this->db->rollback();
  }

  public function inTransaction(): bool
  {
    return $this->db->inTransaction();
  }
}

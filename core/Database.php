<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
  private static ?self $instance = null;
  private PDO $pdo;

  private function __construct()
  {
    $cfg = require ROOT_PATH . '/config/database.php';
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4";

    try {
      $this->pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]);
    } catch (PDOException $e) {
      error_log('DB connection failed: ' . $e->getMessage());
      die('Erro de conexão com o banco de dados.');
    }
  }

  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function query(string $sql, array $params = []): PDOStatement
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
  }

  public function fetchAll(string $sql, array $params = []): array
  {
    return $this->query($sql, $params)->fetchAll();
  }

  public function fetchOne(string $sql, array $params = []): array|false
  {
    return $this->query($sql, $params)->fetch();
  }

  public function insert(string $sql, array $params = []): int
  {
    $this->query($sql, $params);
    return (int) $this->pdo->lastInsertId();
  }

  public function execute(string $sql, array $params = []): int
  {
    return $this->query($sql, $params)->rowCount();
  }

  public function beginTransaction(): void
  {
    $this->pdo->beginTransaction();
  }
  public function commit(): void
  {
    $this->pdo->commit();
  }
  public function rollback(): void
  {
    $this->pdo->rollBack();
  }
}

<?php

declare(strict_types=1);

namespace App\Models;

class SystemSetting extends Model
{
  protected string $table = 'system_settings';

  public function get(string $key, string $default = ''): string
  {
    $row = $this->db->fetchOne(
      "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1",
      [$key]
    );

    return $row !== false ? (string) $row['setting_value'] : $default;
  }

  public function getBool(string $key, bool $default = false): bool
  {
    $row = $this->db->fetchOne(
      "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1",
      [$key]
    );

    if ($row === false) {
      return $default;
    }

    return in_array((string) $row['setting_value'], ['1', 'true', 'yes'], true);
  }

  public function set(string $key, string $value, int $updatedBy): void
  {
    $this->db->execute(
      "INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()",
      [$key, $value, $updatedBy]
    );
  }
}

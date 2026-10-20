<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use Core\Database;

/**
 * Fila de e-mail dos Avisos: envia, uma vez, os Avisos ainda não lidos do
 * último dia para quem deixou o e-mail ligado. Antes, gera os lembretes de
 * prazo de todos os Alunos (sem depender de abrirem o app).
 */
class NotificationMailer
{
  public const MAX_ATTEMPTS = 3;
  public const WINDOW_HOURS = 24;

  private Database $db;
  private Mailer $mailer;

  public function __construct(?Mailer $mailer = null, ?Database $db = null)
  {
    $this->db = $db ?? Database::getInstance();
    $this->mailer = $mailer ?? new Mailer();
  }

  /** @return array{sent: int, failed: int} */
  public function run(int $limit = 200): array
  {
    (new Notification($this->db))->generateDeadlineReminders(null);

    $safeLimit = max(1, $limit);
    $pending = $this->db->fetchAll(
      "SELECT n.id, n.type, n.title, n.body, n.link, n.email_attempts, u.email, u.name
             FROM notifications n
             JOIN users u ON u.id = n.user_id
             WHERE n.emailed_at IS NULL
               AND n.read_at IS NULL
               AND n.email_attempts < ?
               AND n.created_at >= NOW() - INTERVAL " . self::WINDOW_HOURS . " HOUR
               AND u.email_notifications = 1
               AND (u.status = 'active' OR n.type = ?)
             ORDER BY n.id
             LIMIT {$safeLimit}",
      [self::MAX_ATTEMPTS, Notification::TYPE_TEACHER_REGISTRATION]
    );

    $sent = 0;
    $failed = 0;
    foreach ($pending as $item) {
      // Reserva otimista: dois workers ao mesmo tempo não enviam o mesmo Aviso.
      $claimed = $this->db->execute(
        "UPDATE notifications SET email_attempts = email_attempts + 1
               WHERE id = ? AND emailed_at IS NULL AND email_attempts = ?",
        [(int) $item['id'], (int) $item['email_attempts']]
      );
      if ($claimed === 0) {
        continue;
      }

      if ($this->mailer->send((string) $item['email'], (string) $item['title'], $this->body($item))) {
        $this->db->execute("UPDATE notifications SET emailed_at = NOW() WHERE id = ?", [(int) $item['id']]);
        $sent++;
      } else {
        error_log('notification email failed: #' . (int) $item['id']);
        $failed++;
      }
    }

    return ['sent' => $sent, 'failed' => $failed];
  }

  private function body(array $item): string
  {
    $url = $this->mailer->appUrl();
    $lines = ['Olá, ' . $item['name'] . '.', '', (string) $item['title']];
    if (!empty($item['body'])) {
      $lines[] = (string) $item['body'];
    }
    if (!empty($item['link'])) {
      $lines[] = '';
      $lines[] = 'Acesse: ' . $url . $item['link'];
    }
    $lines[] = '';
    $lines[] = '—';
    $lines[] = 'AlgoIA. Este é um aviso automático; não responda.';
    $lines[] = 'Para não receber mais estes e-mails, desligue em Minha conta: ' . $url . '/conta';
    return implode("\n", $lines);
  }
}

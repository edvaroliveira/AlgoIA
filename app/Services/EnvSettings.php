<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Configurações do ambiente editáveis pelo Admin (gravadas no .env).
 * Cada grupo é validado e TESTADO (OpenAI, SMTP, banco) antes de salvar,
 * para um valor errado não derrubar o site, o worker ou o envio de e-mails.
 * Segredos nunca voltam para a tela: em branco = manter o atual.
 */
class EnvSettings
{
  public const GROUPS = [
    'ia' => [
      'title' => 'IA (OpenAI)',
      'help'  => 'Chave e modelo usados nas correções. Antes de salvar, a chave e o modelo são conferidos na OpenAI.',
      'fields' => [
        'OPENAI_API_KEY'         => ['label' => 'Chave da API', 'type' => 'secret'],
        'OPENAI_MODEL'           => ['label' => 'Modelo', 'type' => 'model', 'default' => 'gpt-4o'],
        'OPENAI_TIMEOUT_SECONDS' => ['label' => 'Tempo limite por chamada (s)', 'type' => 'int', 'min' => 5, 'max' => 300, 'default' => '30'],
        'OPENAI_MAX_RETRIES'     => ['label' => 'Tentativas por chamada', 'type' => 'int', 'min' => 1, 'max' => 10, 'default' => '3'],
      ],
    ],
    'mail' => [
      'title' => 'E-mail (SMTP)',
      'help'  => 'Envio dos Avisos por e-mail. Com SMTP, o login é testado antes de salvar (nenhum e-mail é enviado).',
      'fields' => [
        'MAIL_DRIVER'          => ['label' => 'Forma de envio', 'type' => 'select', 'options' => ['smtp' => 'SMTP autenticado', 'mail' => 'mail() do servidor'], 'default' => 'smtp'],
        'MAIL_FROM'            => ['label' => 'Remetente', 'type' => 'email'],
        'MAIL_FROM_NAME'       => ['label' => 'Nome do remetente', 'type' => 'text', 'default' => 'AlgoIA', 'optional' => true],
        'MAIL_SMTP_HOST'       => ['label' => 'Servidor SMTP', 'type' => 'host', 'optional' => true],
        'MAIL_SMTP_PORT'       => ['label' => 'Porta', 'type' => 'int', 'min' => 1, 'max' => 65535, 'default' => '465'],
        'MAIL_SMTP_ENCRYPTION' => ['label' => 'Criptografia', 'type' => 'select', 'options' => ['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'Nenhuma (só localhost)'], 'default' => 'ssl'],
        'MAIL_SMTP_USER'       => ['label' => 'Usuário', 'type' => 'text', 'optional' => true],
        'MAIL_SMTP_PASSWORD'   => ['label' => 'Senha', 'type' => 'secret', 'optional' => true],
      ],
    ],
    'db' => [
      'title' => 'Banco de dados',
      'help'  => 'A conexão é testada antes de salvar. Um valor errado aqui tiraria o site do ar, por isso nada é gravado se a conexão falhar.',
      'fields' => [
        'DB_HOST'     => ['label' => 'Servidor', 'type' => 'host', 'default' => 'localhost'],
        'DB_DATABASE' => ['label' => 'Banco', 'type' => 'name'],
        'DB_USERNAME' => ['label' => 'Usuário', 'type' => 'name'],
        'DB_PASSWORD' => ['label' => 'Senha', 'type' => 'secret', 'optional' => true],
      ],
    ],
    'app' => [
      'title' => 'Aplicação e fila de correção',
      'help'  => 'Identificação do site, modo de depuração, proxies confiáveis e limites da fila de correção.',
      'fields' => [
        'APP_NAME'                  => ['label' => 'Nome', 'type' => 'text', 'default' => 'AlgoIA'],
        'APP_URL'                   => ['label' => 'Endereço (URL)', 'type' => 'url'],
        'APP_ENV'                   => ['label' => 'Ambiente', 'type' => 'select', 'options' => ['production' => 'Produção', 'development' => 'Desenvolvimento'], 'default' => 'production'],
        'APP_DEBUG'                 => ['label' => 'Modo de depuração', 'type' => 'select', 'options' => ['false' => 'Desligado (recomendado)', 'true' => 'Ligado (mostra erros na tela)'], 'default' => 'false'],
        'TRUSTED_PROXIES'           => ['label' => 'Proxies confiáveis (IPs/CIDR, separados por vírgula)', 'type' => 'proxies', 'optional' => true],
        'GRADING_JOB_MAX_ATTEMPTS'  => ['label' => 'Tentativas por correção na fila', 'type' => 'int', 'min' => 1, 'max' => 10, 'default' => '3'],
        'GRADING_JOB_STALE_MINUTES' => ['label' => 'Minutos até recuperar job travado', 'type' => 'int', 'min' => 1, 'max' => 1440, 'default' => '15'],
      ],
    ],
  ];

  public function __construct(private EnvFile $file)
  {
  }

  public static function filePath(): string
  {
    $custom = trim((string) \Core\env('APP_ENV_FILE', ''));
    return $custom !== '' ? $custom : ROOT_PATH . '/.env';
  }

  /** Valor para exibir: segredos mascarados ("••••abcd"). */
  public static function display(array $field, ?string $value): string
  {
    if ($field['type'] !== 'secret') {
      return (string) ($value ?? $field['default'] ?? '');
    }
    if ($value === null || $value === '') {
      return '';
    }
    return '••••' . (strlen($value) > 8 ? substr($value, -4) : '');
  }

  /**
   * Valida o formulário de um grupo e devolve o que gravar.
   *
   * @return array<string, string> valores finais do grupo (segredo em branco = atual)
   * @throws \InvalidArgumentException mensagem para o Admin
   */
  public function validate(string $group, array $input): array
  {
    $definition = self::GROUPS[$group] ?? null;
    if ($definition === null) {
      throw new \InvalidArgumentException('Grupo de configuração desconhecido.');
    }
    $current = $this->file->read();
    $values = [];

    foreach ($definition['fields'] as $key => $field) {
      $raw = trim((string) ($input[$key] ?? ''));
      if (preg_match('/[\r\n\0]/', (string) ($input[$key] ?? '')) === 1) {
        throw new \InvalidArgumentException("{$field['label']}: não pode ter quebra de linha.");
      }
      if ($field['type'] === 'secret' && $raw === '') {
        $raw = (string) ($current[$key] ?? '');
      }
      if ($raw === '' && !empty($field['optional'])) {
        $values[$key] = '';
        continue;
      }
      if ($raw === '') {
        throw new \InvalidArgumentException("{$field['label']}: preenchimento obrigatório.");
      }
      if (mb_strlen($raw) > 500) {
        throw new \InvalidArgumentException("{$field['label']}: valor longo demais.");
      }
      $valid = match ($field['type']) {
        'int'     => preg_match('/^\d+$/', $raw) === 1 && (int) $raw >= $field['min'] && (int) $raw <= $field['max'],
        'select'  => array_key_exists($raw, $field['options']),
        'email'   => filter_var($raw, FILTER_VALIDATE_EMAIL) !== false,
        'url'     => filter_var($raw, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $raw) === 1,
        'host'    => preg_match('/^[A-Za-z0-9.\-:]+$/', $raw) === 1,
        'name'    => preg_match('/^[A-Za-z0-9_.\-]+$/', $raw) === 1,
        'model'   => preg_match('/^[A-Za-z0-9._:\-]+$/', $raw) === 1,
        'proxies' => preg_match('#^[0-9A-Fa-f:.,/\s]+$#', $raw) === 1,
        default   => true,
      };
      if (!$valid) {
        throw new \InvalidArgumentException("{$field['label']}: valor inválido.");
      }
      EnvFile::format($raw); // aspas simples e duplas juntas não cabem no .env
      $values[$key] = $raw;
    }

    return $values;
  }

  /**
   * Testa o grupo com os valores novos (sem gravar nada).
   *
   * @throws \RuntimeException mensagem para o Admin
   */
  public function test(string $group, array $values): void
  {
    match ($group) {
      'ia'   => $this->testOpenAi($values),
      'mail' => $this->testMail($values),
      'db'   => $this->testDatabase($values),
      default => null,
    };
  }

  private function testOpenAi(array $values): void
  {
    $apiUrl = (string) ((require ROOT_PATH . '/config/openai.php')['api_url']);
    $base = (string) preg_replace('#/chat/completions$#', '', $apiUrl);
    $ch = curl_init($base . '/models/' . rawurlencode($values['OPENAI_MODEL']));
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $values['OPENAI_API_KEY']],
      CURLOPT_TIMEOUT        => 15,
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    unset($ch);

    if ($error !== '') {
      throw new \RuntimeException('Não foi possível falar com a OpenAI para testar a chave: ' . $error);
    }
    if ($code === 401 || $code === 403) {
      throw new \RuntimeException('A OpenAI recusou a chave da API. Nada foi alterado.');
    }
    if ($code === 404) {
      throw new \RuntimeException('A OpenAI não encontrou o modelo "' . $values['OPENAI_MODEL'] . '" para esta chave. Nada foi alterado.');
    }
    if ($code !== 200) {
      throw new \RuntimeException("A OpenAI respondeu {$code} ao testar a chave. Tente de novo em instantes.");
    }
  }

  private function testMail(array $values): void
  {
    $config = [
      'driver'          => $values['MAIL_DRIVER'],
      'from'            => $values['MAIL_FROM'],
      'from_name'       => $values['MAIL_FROM_NAME'],
      'log_path'        => '',
      'app_url'         => '',
      'smtp_host'       => $values['MAIL_SMTP_HOST'],
      'smtp_port'       => (int) $values['MAIL_SMTP_PORT'],
      'smtp_encryption' => $values['MAIL_SMTP_ENCRYPTION'],
      'smtp_user'       => $values['MAIL_SMTP_USER'],
      'smtp_password'   => $values['MAIL_SMTP_PASSWORD'],
      'smtp_timeout'    => 15,
    ];
    $errors = (new Mailer($config))->configErrors();
    if ($errors !== []) {
      throw new \RuntimeException(implode(' ', $errors));
    }
    if ($values['MAIL_DRIVER'] !== Mailer::DRIVER_SMTP) {
      return;
    }
    try {
      (new SmtpTransport($config['smtp_host'], $config['smtp_port'], $config['smtp_encryption'], $config['smtp_user'], $config['smtp_password'], 15))
        ->verifyLogin($config['from']);
    } catch (\RuntimeException $e) {
      throw new \RuntimeException('Teste do SMTP falhou: ' . $e->getMessage() . ' Nada foi alterado.');
    }
  }

  private function testDatabase(array $values): void
  {
    try {
      new \PDO(
        "mysql:host={$values['DB_HOST']};dbname={$values['DB_DATABASE']};charset=utf8mb4",
        $values['DB_USERNAME'],
        $values['DB_PASSWORD'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]
      );
    } catch (\PDOException $e) {
      // Mensagem do driver sem a senha (o PDO não a inclui).
      throw new \RuntimeException('Não foi possível conectar ao banco com esses dados: ' . $e->getMessage() . ' Nada foi alterado.');
    }
  }
}

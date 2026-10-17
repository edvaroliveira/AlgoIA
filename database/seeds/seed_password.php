<?php

declare(strict_types=1);

/**
 * Senha de seed: obrigatória no ambiente, forte e diferente do placeholder do
 * .env.example. Não existe senha padrão — uma senha padrão versionada num repo
 * público vira credencial conhecida de quem ler o código (AU-10).
 *
 * @param string|null $value valor lido do ambiente (null = ausente)
 */
function seed_password(string $envKey, ?string $value): string
{
  $value = $value === null ? '' : trim($value);

  if ($value === '' || $value === 'defina_uma_senha_forte') {
    throw new \RuntimeException("Defina {$envKey} no .env com uma senha forte antes de rodar o seed.");
  }

  if (!\Core\password_is_strong($value)) {
    throw new \RuntimeException("{$envKey} fraca: use de 10 a 72 caracteres, com maiúscula, minúscula e número.");
  }

  return $value;
}

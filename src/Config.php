<?php

declare(strict_types=1);

namespace Doorbell;

use RuntimeException;

/**
 * Typed wrapper around config.php, with fail-fast validation.
 *
 * The placeholder checks are deliberate: a deployment that silently runs with
 * the example secrets would expose the dashboard to anyone who guesses the URL.
 */
final class Config
{
    public function __construct(
        public readonly string $domain,
        public readonly ?string $contactEmail,
        public readonly string $salt,
        public readonly string $statsToken,
        public readonly string $database,
        public readonly bool $behindCloudflare,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                "Missing config file: {$path}. Copy config.example.php to config.php first."
            );
        }

        /** @var array<string, mixed> $data */
        $data = require $path;

        return self::fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $salt = (string) ($data['salt'] ?? '');
        $token = (string) ($data['stats_token'] ?? '');

        foreach (['salt' => $salt, 'stats_token' => $token] as $key => $value) {
            if ($value === '' || str_starts_with($value, 'CHANGE_ME')) {
                throw new RuntimeException("Config value '{$key}' still holds its placeholder.");
            }
        }

        if (strlen($salt) < 16) {
            throw new RuntimeException("Config value 'salt' is too short to be useful.");
        }

        $email = $data['contact_email'] ?? null;

        return new self(
            domain: (string) ($data['domain'] ?? 'this domain'),
            contactEmail: is_string($email) && $email !== '' ? $email : null,
            salt: $salt,
            statsToken: $token,
            database: (string) ($data['database'] ?? __DIR__ . '/../var/doorbell.sqlite'),
            behindCloudflare: (bool) ($data['behind_cloudflare'] ?? false),
        );
    }
}

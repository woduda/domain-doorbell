<?php

declare(strict_types=1);

namespace Doorbell;

use Throwable;

/**
 * Turns one HTTP request into one row in the hit table.
 */
final class Recorder
{
    public function __construct(
        private readonly Config $config,
        private readonly Storage $storage,
        private readonly BotDetector $bots = new BotDetector(),
    ) {
    }

    /**
     * @param array<string, mixed> $server Usually $_SERVER.
     */
    public function record(array $server): void
    {
        $userAgent = $this->header($server, 'HTTP_USER_AGENT');
        $referer = $this->header($server, 'HTTP_REFERER');
        $day = gmdate('Y-m-d');

        $isBot = $this->bots->isBot($userAgent);

        // The core metric. An empty Referer from a non-bot client means the
        // visitor did not follow a link: they typed the domain, used a
        // bookmark, or opened it from a non-web app. For a parked domain
        // that is as close to proof of type-in traffic as HTTP allows.
        $isDirect = !$isBot && $referer === '';

        $statement = $this->storage->pdo()->prepare(<<<'SQL'
            INSERT INTO hit
                (ts, day, visitor, host, path, referer, user_agent, language, is_bot, is_direct)
            VALUES
                (:ts, :day, :visitor, :host, :path, :referer, :user_agent, :language, :is_bot, :is_direct)
            SQL);

        $statement->execute([
            'ts' => time(),
            'day' => $day,
            'visitor' => $this->visitorHash($this->clientIp($server), $day),
            'host' => substr($this->header($server, 'HTTP_HOST'), 0, 255),
            'path' => substr($this->header($server, 'REQUEST_URI', '/'), 0, 500),
            'referer' => substr($referer, 0, 500),
            'user_agent' => substr($userAgent, 0, 500),
            'language' => substr($this->header($server, 'HTTP_ACCEPT_LANGUAGE'), 0, 100),
            'is_bot' => (int) $isBot,
            'is_direct' => (int) $isDirect,
        ]);
    }

    /**
     * Recording must never take the page down. A parked domain that returns
     * a 500 to a potential buyer is worse than a missing log line.
     *
     * @param array<string, mixed> $server
     */
    public function recordQuietly(array $server): void
    {
        try {
            $this->record($server);
        } catch (Throwable) {
            // Intentionally swallowed.
        }
    }

    /**
     * Pseudonymised visitor identifier.
     *
     * The raw IP is never stored. The day is part of the HMAC input, so the
     * same person gets a new identifier every midnight UTC: enough to count
     * daily uniques, useless for tracking anyone across time.
     */
    private function visitorHash(string $ip, string $day): string
    {
        return substr(hash_hmac('sha256', $ip . '|' . $day, $this->config->salt), 0, 16);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function clientIp(array $server): string
    {
        if ($this->config->behindCloudflare) {
            $forwarded = $this->header($server, 'HTTP_CF_CONNECTING_IP');

            if ($forwarded !== '') {
                return $forwarded;
            }
        }

        return $this->header($server, 'REMOTE_ADDR', '0.0.0.0');
    }

    /**
     * @param array<string, mixed> $server
     */
    private function header(array $server, string $key, string $default = ''): string
    {
        $value = $server[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}

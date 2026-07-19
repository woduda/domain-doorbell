<?php

declare(strict_types=1);

namespace Doorbell;

use PDO;
use RuntimeException;

/**
 * SQLite storage. Creates the database file and schema on first request so
 * that deployment is nothing more than uploading files.
 */
final class Storage
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create database directory: {$directory}");
        }

        $pdo = new PDO('sqlite:' . $this->path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // WAL keeps the single writer from blocking dashboard reads.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 3000');

        $this->migrate($pdo);

        return $this->pdo = $pdo;
    }

    private function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS hit (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                ts        INTEGER NOT NULL,
                day       TEXT    NOT NULL,
                visitor   TEXT    NOT NULL,
                host      TEXT    NOT NULL DEFAULT '',
                path      TEXT    NOT NULL DEFAULT '/',
                referer   TEXT    NOT NULL DEFAULT '',
                user_agent TEXT   NOT NULL DEFAULT '',
                language  TEXT    NOT NULL DEFAULT '',
                is_bot    INTEGER NOT NULL DEFAULT 0,
                is_direct INTEGER NOT NULL DEFAULT 0
            )
            SQL);

        $pdo->exec('CREATE INDEX IF NOT EXISTS hit_day_idx ON hit (day)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS hit_visitor_idx ON hit (visitor)');
    }
}

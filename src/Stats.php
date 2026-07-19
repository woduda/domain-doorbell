<?php

declare(strict_types=1);

namespace Doorbell;

/**
 * Read-side queries. Everything is expressed in terms of *unique visitors per
 * day* rather than raw hits, because a single curious person reloading a page
 * five times is one data point, not five.
 */
final class Stats
{
    public function __construct(private readonly Storage $storage)
    {
    }

    /**
     * @return array{
     *     hits: int, bots: int, direct: int, humans: int,
     *     first_day: ?string, days: int, direct_per_week: float
     * }
     */
    public function totals(): array
    {
        $row = $this->storage->pdo()->query(<<<'SQL'
            SELECT
                COUNT(*)                                                  AS hits,
                COALESCE(SUM(is_bot), 0)                                  AS bots,
                COALESCE(SUM(is_direct), 0)                               AS direct,
                COUNT(DISTINCT CASE WHEN is_bot = 0 THEN visitor END)     AS humans,
                MIN(day)                                                  AS first_day
            FROM hit
            SQL)->fetch();

        $firstDay = is_string($row['first_day'] ?? null) ? $row['first_day'] : null;

        // Days of measurement, counted inclusively so a fresh install reports 1.
        $days = 1;
        if ($firstDay !== null) {
            $start = strtotime($firstDay . ' 00:00:00 UTC');
            $days = max(1, (int) floor((time() - (int) $start) / 86400) + 1);
        }

        $direct = (int) $row['direct'];

        return [
            'hits' => (int) $row['hits'],
            'bots' => (int) $row['bots'],
            'direct' => $direct,
            'humans' => (int) $row['humans'],
            'first_day' => $firstDay,
            'days' => $days,
            'direct_per_week' => round($direct / $days * 7, 1),
        ];
    }

    /**
     * Newest first, so the dashboard reads like a log.
     *
     * @return list<array{day: string, direct_uniques: int, uniques: int, bot_hits: int}>
     */
    public function daily(int $limit = 90): array
    {
        $statement = $this->storage->pdo()->prepare(<<<'SQL'
            SELECT
                day,
                COUNT(DISTINCT CASE WHEN is_direct = 1 THEN visitor END) AS direct_uniques,
                COUNT(DISTINCT CASE WHEN is_bot = 0 THEN visitor END)    AS uniques,
                COALESCE(SUM(is_bot), 0)                                 AS bot_hits
            FROM hit
            GROUP BY day
            ORDER BY day DESC
            LIMIT :limit
            SQL);

        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): array => [
                'day' => (string) $row['day'],
                'direct_uniques' => (int) $row['direct_uniques'],
                'uniques' => (int) $row['uniques'],
                'bot_hits' => (int) $row['bot_hits'],
            ],
            $statement->fetchAll(),
        );
    }

    /**
     * Human referrers only. On a parked domain this list is usually short, and
     * anything in it that is not a domain marketplace is worth investigating.
     *
     * @return list<array{value: string, hits: int}>
     */
    public function topReferers(int $limit = 25): array
    {
        return $this->topColumn('referer', $limit, "referer <> ''");
    }

    /**
     * Requested paths. Anything other than "/" suggests the domain once hosted
     * a real site and people still remember specific URLs — the strongest
     * signal this tool can surface.
     *
     * @return list<array{value: string, hits: int}>
     */
    public function topPaths(int $limit = 25): array
    {
        return $this->topColumn('path', $limit);
    }

    /**
     * Hour-of-day distribution (UTC). Human traffic clusters around waking
     * hours; a flat line across 24 hours means the bot filter is leaking.
     *
     * @return array<int, int>
     */
    public function hourHistogram(): array
    {
        $histogram = array_fill(0, 24, 0);

        $rows = $this->storage->pdo()->query(<<<'SQL'
            SELECT CAST(strftime('%H', ts, 'unixepoch') AS INTEGER) AS hour, COUNT(*) AS hits
            FROM hit
            WHERE is_bot = 0
            GROUP BY hour
            SQL)->fetchAll();

        foreach ($rows as $row) {
            $histogram[(int) $row['hour']] = (int) $row['hits'];
        }

        return $histogram;
    }

    /**
     * @return list<array{value: string, hits: int}>
     */
    private function topColumn(string $column, int $limit, string $extraCondition = '1=1'): array
    {
        // $column is never user input; it comes from the two callers above.
        $statement = $this->storage->pdo()->prepare(
            "SELECT {$column} AS value, COUNT(*) AS hits
             FROM hit
             WHERE is_bot = 0 AND {$extraCondition}
             GROUP BY value
             ORDER BY hits DESC
             LIMIT :limit"
        );

        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): array => [
                'value' => (string) $row['value'],
                'hits' => (int) $row['hits'],
            ],
            $statement->fetchAll(),
        );
    }
}

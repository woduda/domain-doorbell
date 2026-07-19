<?php

declare(strict_types=1);

namespace Doorbell;

/**
 * Daily figures as CSV, oldest first, ready to attach to a sales listing.
 *
 * Bot hits are included on purpose: showing what was filtered out is what
 * makes the human numbers credible to a sceptical buyer.
 */
final class CsvExport
{
    public function __construct(private readonly Stats $stats)
    {
    }

    public function render(string $domain): string
    {
        $handle = fopen('php://temp', 'r+b');
        assert($handle !== false);

        fputcsv($handle, ['domain', 'day', 'direct_unique_visitors', 'unique_visitors', 'filtered_bot_hits']);

        foreach (array_reverse($this->stats->daily(3650)) as $row) {
            fputcsv($handle, [
                $domain,
                $row['day'],
                $row['direct_uniques'],
                $row['uniques'],
                $row['bot_hits'],
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function filename(string $domain): string
    {
        $safe = preg_replace('/[^a-z0-9.-]+/i', '-', $domain) ?? 'domain';

        return $safe . '-traffic-' . gmdate('Y-m-d') . '.csv';
    }
}

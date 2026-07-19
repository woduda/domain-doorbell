<?php

declare(strict_types=1);

namespace Doorbell;

/**
 * User-agent based bot detection.
 *
 * This classifier is intentionally biased towards false positives: it would
 * rather discard a real human than inflate the numbers. The output of this
 * tool is meant to be handed to a buyer who may audit it, so an honest
 * undercount is far more valuable than an impressive overcount.
 */
final class BotDetector
{
    /**
     * Substrings that appear in the user agent of crawlers, monitoring
     * services, SEO tools, HTTP libraries and link previewers.
     *
     * Note that 'domain' and 'seo' catch the swarm of domain-appraisal and
     * backlink scrapers that hit every parked domain constantly. They are the
     * single biggest source of fake "traffic" on a domain that is for sale.
     *
     * @var list<string>
     */
    private const SIGNATURES = [
        'bot', 'crawl', 'spider', 'slurp', 'archiver', 'curl', 'wget',
        'python-requests', 'python-urllib', 'httpclient', 'go-http', 'java/',
        'okhttp', 'libwww', 'headless', 'phantomjs', 'scrapy', 'apache-http',
        'facebookexternalhit', 'preview', 'monitor', 'uptime', 'pingdom',
        'ahrefs', 'semrush', 'mj12', 'dotbot', 'petalbot', 'bytespider',
        'gptbot', 'claudebot', 'ccbot', 'perplexity', 'dataprovider',
        'domain', 'seo', 'scanner', 'nuclei', 'zgrab', 'masscan',
    ];

    public function isBot(string $userAgent): bool
    {
        // No user agent at all is never a browser opening a page.
        if ($userAgent === '') {
            return true;
        }

        $needle = strtolower($userAgent);

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($needle, $signature)) {
                return true;
            }
        }

        // Every mainstream browser still prefixes its UA with "Mozilla/5.0"
        // for historical reasons. Anything else is a client we did not expect.
        return !str_starts_with($needle, 'mozilla/');
    }
}

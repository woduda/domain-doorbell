# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

Domain Doorbell is a single-purpose PHP analytics page for a parked domain. It answers one
question — how many real people type the domain directly into a browser per week — while
filtering out the constant crawler/scraper/appraisal-bot traffic that hits every parked domain.
No JavaScript, no cookies, no IP storage. One SQLite file.

## Commands

There is no build step, no test suite, and no linter/formatter configured (no `require-dev` in
`composer.json`, no phpunit/phpstan/phpcs). Composer is optional — the app ships its own
autoloader (`bootstrap.php`) and runs dependency-free.

Setup for local work:
```bash
cp config.example.php config.php
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # run twice, for salt + stats_token
```
Edit `config.php` with the generated secrets. `Config::fromFile()` fails fast (throws
`RuntimeException`) if either secret is still a placeholder or the salt is under 16 chars — this
is intentional, not a bug to work around.

Run it locally:
```bash
php -S localhost:8000 -t public
```

CLI report (reads the same DB, does not affect the recorded stats):
```bash
php bin/report.php              # summary + last 14 days
php bin/report.php --days=60
php bin/report.php --csv
```

Sanity-check PHP syntax after edits (there's no test suite to catch mistakes otherwise):
```bash
php -l path/to/file.php
```

## Architecture

**Single front controller, two modes.** `public/index.php` is the only entry point. It branches
on one thing: does `$_GET['stats']` match `$config->statsToken` (via `hash_equals`, constant-time
on purpose)?
- Match → dashboard mode. Builds a `Stats` object and either streams a CSV (`?csv=1`) or renders
  `src/View/dashboard.php`. Dashboard requests are **not** recorded, so checking your own stats
  doesn't pollute the numbers.
- No match → parked-page mode. Calls `Recorder::recordQuietly()` to log the hit, then renders
  `src/View/parked.php`.

`.htaccess` (Apache) routes every non-file request to `index.php`, so deep links
(`/some/remembered/path`) get recorded instead of 404ing — catching those paths is called out in
the README as the single most valuable signal the tool can produce.

**Autoloading has a no-dependency fallback.** `bootstrap.php` uses Composer's autoloader if
`vendor/autoload.php` exists, otherwise registers its own minimal PSR-4 loader mapping
`Doorbell\*` to `src/*.php`. Both `public/index.php` and `bin/report.php` load config via
`require dirname(__DIR__) . '/bootstrap.php'` — i.e. they assume they live exactly one directory
below the project root. Don't move them without updating that path.

**The core metric lives in `Recorder`.** A hit is "direct" iff the client isn't classified as a
bot (`BotDetector::isBot`) AND the `Referer` header is empty. Visitor identity is never the raw
IP: it's `substr(hash_hmac('sha256', $ip . '|' . $day, $salt), 0, 16)`, so it changes every day at
midnight UTC — enough for daily-unique counting, useless for tracking anyone over time. Recording
errors are swallowed (`recordQuietly` / `catch (Throwable)`) because a parked domain returning 500
to a potential buyer is worse than a dropped log line.

**`BotDetector` is deliberately biased toward false positives** — see the `SIGNATURES` list
comment in `src/BotDetector.php`. It'd rather discard a real human than inflate the count, because
the numbers are meant to survive a skeptical buyer's audit. Any UA not starting with `mozilla/`
is treated as a bot regardless of the signature list.

**Storage migrates itself.** `Storage::pdo()` lazily creates the DB directory and the `hit` table
schema on first use (`CREATE TABLE IF NOT EXISTS`), so deployment is just uploading files — no
migration step. WAL mode + `busy_timeout` keep the single writer from blocking dashboard reads.

**`Stats` computes everything from `hit` rows in terms of unique visitors per day**, not raw hits
— a person reloading 5 times is one data point. `topReferers`/`topPaths` share a private
`topColumn()` helper; the column name there is never user input (only the two hardcoded callers
use it), so it's safe to interpolate directly into SQL.

**Views are plain PHP templates, not a template engine.** `Html::render($template, $data)`
extracts `$data` into scope and includes `src/View/{$template}.php`, buffering output. Templates
(`dashboard.php`, `parked.php`) are trusted, hand-written PHP with inline `<?= Html::esc(...) ?>`
escaping — there's no auto-escaping, so every dynamic value printed in a view must go through
`Html::esc()` explicitly.

**Config behind Cloudflare**: `Recorder::clientIp()` only trusts `CF-Connecting-IP` over
`REMOTE_ADDR` when `$config->behindCloudflare` is true. Don't assume this header without checking
that flag.

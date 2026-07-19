# Domain Doorbell

**Find out whether anyone still knocks on your parked domain.**

You own a domain you are not using. Before you sell it, renew it, or drop it,
there is one question worth answering: does anyone still type it into a browser?

Domain Doorbell is a single-purpose analytics page you point that domain at. It
serves a plain "for sale" page and answers exactly one question — **how many
real people arrive here on their own each week** — in a form you can hand to a
buyer.

No dependencies, no JavaScript, no cookies, no IP addresses stored. One SQLite
file.

---

## Why not just use analytics?

Because general-purpose analytics answers the wrong question for a domain that
is for sale.

| | Google Analytics / Plausible | Server logs + GoAccess | Domain Doorbell |
|---|---|---|---|
| Counts visitors without JavaScript | no | yes | yes |
| Separates type-in traffic as a metric | no | no | **yes** |
| Filters the appraisal-bot swarm | partly | no | **yes, aggressively** |
| Export shaped for a sales listing | no | no | **yes** |
| Setup on shared hosting | account + script | needs log access | upload files |

A parked domain is hit constantly by backlink crawlers, domain-appraisal
scrapers and port scanners. Raw log tools will happily tell you the domain gets
"400 visits a month". Any buyer who knows the market will discount that to zero
the moment they see the user agents. This tool reports the number that survives
scrutiny instead.

## What it measures

The headline metric is **direct unique visitors per day**: a request with an
empty `Referer` header, from a client that does not look like a bot, counted
once per visitor per day.

An empty referrer means the visitor did not follow a link. They typed the
domain, used a bookmark, or opened it from an app. For a domain with no site,
no marketing and no search presence, that is as close to proof of type-in
traffic as HTTP allows you to get.

Also surfaced:

- **Requested paths.** Anything other than `/` means someone is typing a
  remembered URL. This is the strongest signal the tool can produce — it means
  the domain once hosted something people used. Cross-check it against
  [web.archive.org](https://web.archive.org).
- **Referrers.** Usually empty on a parked domain. Anything in this list that
  is not a domain marketplace is worth investigating: something out there links
  to you.
- **Hour-of-day histogram.** Human traffic clusters around waking hours. A flat
  line across all 24 hours means the bot filter is leaking and the numbers
  should not be trusted yet.
- **Filtered bot count.** Shown deliberately, so you can prove what you removed.

## Honesty is the feature

The bot classifier is biased towards false positives. It will throw away some
real humans, and that is the intended trade-off.

The output of this tool is meant to be attached to a sales listing and read by
someone who may audit it. An honest undercount you can defend is worth more
than an impressive number that collapses under one question. If you want a
figure that flatters the domain, this is the wrong tool.

## Requirements

- PHP 8.1 or newer
- `pdo_sqlite` (bundled with PHP by default)
- A writable directory for the database

That is it. Composer is optional; the project ships its own autoloader.

## Install

```bash
git clone https://github.com/yourname/domain-doorbell.git
cd domain-doorbell
cp config.example.php config.php

# generate two secrets
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Edit `config.php`:

```php
return [
    'domain'            => 'kursy.net.pl',
    'contact_email'     => 'you@example.com',
    'salt'              => '<first generated secret>',
    'stats_token'       => '<second generated secret>',
    'database'          => __DIR__ . '/var/doorbell.sqlite',
    'behind_cloudflare' => false,
];
```

The application refuses to start while either secret still holds its
placeholder value, so a half-finished deployment cannot silently expose the
dashboard.

### Apache

Point the vhost `DocumentRoot` at `public/`. The bundled `.htaccess` routes
everything to the front controller and blocks direct access to the database.

### Nginx

```nginx
server {
    server_name kursy.net.pl www.kursy.net.pl;
    root /var/www/domain-doorbell/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Shared hosting without a configurable document root

Upload the whole project, move `public/index.php` and `public/.htaccess` into
your web root, and change the first `require` in `index.php` to point at
`bootstrap.php` wherever you put it. Keep `config.php`, `src/` and `var/`
outside the web root if you can.

## Usage

Open the dashboard:

```
https://kursy.net.pl/?stats=<stats_token>
```

Download the CSV to attach to a listing:

```
https://kursy.net.pl/?stats=<stats_token>&csv=1
```

From the command line:

```bash
php bin/report.php              # summary plus the last 14 days
php bin/report.php --days=60    # longer window
php bin/report.php --csv        # raw CSV to stdout
```

Requests to the dashboard are not recorded, so checking your own stats does not
pollute them.

## Two things worth doing alongside it

**Catch deep links.** Make sure unmatched paths reach the front controller
rather than returning 404 — the Apache and Nginx configs above already do this.
Deep type-ins are the most valuable thing this tool can catch, and a 404 throws
them away.

**Set up catch-all email.** If anything non-spam arrives at
`anything@yourdomain`, the domain was once used by a real business and is still
in people's address books. That can matter more to a buyer than HTTP traffic,
and this tool cannot see it for you.

## Privacy

- No cookies are set. No JavaScript is served. No third party receives anything.
- IP addresses are never written to disk. Each visitor is stored as
  `HMAC-SHA256(ip + date, salt)`, truncated to 16 hex characters.
- The date is part of the HMAC input, so the identifier changes at midnight
  UTC. It is enough to count daily uniques and useless for following anyone
  over time.
- Nothing is retained that identifies a person, which is the point: under GDPR
  this is pseudonymised, minimal, and hard to argue with.

Delete `var/doorbell.sqlite` to erase everything.

## Interpreting the result

After 30 days, read the direct-per-week figure:

- **0** — nobody types this domain. That is a real answer, and a useful one:
  price it as a keyword domain with no traffic, or let it drop.
- **1–10** — residual awareness. Worth mentioning in a listing with the CSV
  attached; not worth much on its own.
- **10+ with deep paths** — the domain has history. Stop and research what used
  to be there before you price it.

Run it for at least a month. A week of data on a parked domain is noise.

## Project layout

```
bin/report.php          CLI report
public/index.php        front controller: dashboard or parked page
public/.htaccess        rewrite rules + database protection
src/BotDetector.php     user-agent classifier
src/Config.php          typed config with fail-fast validation
src/CsvExport.php       daily figures as CSV
src/Html.php            escaping and template rendering
src/Recorder.php        request -> row, including the direct-traffic rule
src/Stats.php           read-side queries
src/Storage.php         SQLite connection and schema
src/View/               parked page and dashboard templates
bootstrap.php           autoloader + config loading
config.example.php      copy to config.php
```

## Limitations

- `Referer` is absent for reasons other than type-in: some privacy settings,
  HTTPS-to-HTTP downgrades, and links from native apps. The metric is a good
  proxy, not a measurement.
- User-agent filtering cannot catch a crawler that impersonates Chrome. The
  hour-of-day histogram exists to help you notice when that is happening.
- Single-writer SQLite is fine for parked-domain volumes and inappropriate for
  a busy site. If this domain ever gets real traffic, you have outgrown the
  tool — congratulations.

## License

MIT. See [LICENSE](LICENSE).

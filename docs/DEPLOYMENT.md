# Deployment runbook

Everything needed to take PMAS from the repo to a working
production install, in order. Read it once end to end before starting.

The app is a standard Laravel 13 / PHP 8.4 application with a MySQL database,
file sessions, file cache, and no queue worker or background jobs to run. The
scheduler only does housekeeping (cache pruning and nightly backups).

---

## 0. The domain first, because nothing else matters until it exists

Decide the host name before anything else. The examples below use
`pharmacy.example.com`; replace it with the real name.

Do this first, and in this order:

1. **Register the domain** with any registrar. A short, memorable name is
   easier for staff to type. If you need a country domain, register it through
   that country's registry or an authorised reseller.
2. **Point an A record at the host's IP.** One record, `pharmacy.example.com`.
   Give it a low TTL (300) while you are still moving things.
3. **Decide on `www`.** Either add a second A record for
   `www.pharmacy.example.com` and redirect it to the bare name, or add a CNAME.
   Pick one and redirect the other, so there is exactly one address staff
   bookmark.
4. **Confirm it resolves from outside your office.** This matters more than it
   sounds. A machine on the pharmacy's own network can resolve a name from a
   local cache or hosts file that the rest of the world cannot. Check from a
   phone on mobile data, not from the office Wi-Fi.
5. **Only then** apply for the certificate (step 8). Let's Encrypt validates
   over HTTP, so the name must already resolve, or issuance fails.

Nothing in the application is hardcoded to this name. It appears in two places,
both of them data:

- `APP_URL` in `.env` — what actually generates absolute links and redirects.
- `INSTANCE_DOMAIN` in `.env` — optional; when set, preflight checks that it
  matches the host in `APP_URL`.

Rename the site later and you change those two values plus the certificate.
No code change, no migration.

---

## 1. Server requirements

- PHP 8.4 with `pdo_mysql`, `gd`, `zip`, `fileinfo`, `openssl`, `mbstring`.
- MySQL 8.x or MariaDB reachable from the app.
- Node 24+ and Composer **on the build machine** (assets are compiled, then the
  server only needs PHP — no Node).
- The MySQL client tools (`mysqldump`) available for backups.

---

## 2. Environment file

Copy the template and fill it in:

```bash
cp .env.production.example .env
```

Must be set correctly:

| Key | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | the real URL users will visit |
| `APP_TIMEZONE` | the pharmacy's local timezone, e.g. `Europe/London` |
| `DB_*` | host, port, database, username, password |
| `SESSION_SECURE_COOKIE` | `true` once served over HTTPS |
| `TRUSTED_PROXIES` | proxy IPs, only if behind a reverse proxy |
| `ADMIN_USERNAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` | first administrator; the username is what you sign in with (see step 5) |

`APP_KEY` is **not** set here — it is generated in step 4.

> Business identity (name, logo, colours, VAT rate, receipt footer) is *not* in
> `.env`. It lives in the `settings` table and is edited in the app under
> Business Settings.

---

## 3. Build and install

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

`npm run build` is not optional and not a one-off. Tailwind only emits the
utility classes it finds in the Blade files at the moment it runs, so any new or
edited `.blade.php` that introduces a class the previous build never saw ships
**unstyled** until the assets are rebuilt. The same applies locally: after
pulling changes that add a screen, run `npm run build` (or keep `npm run dev`
running) before judging how the page looks.

---

## 4. Application key — do not reuse the development key

The development `APP_KEY` must not be reused in production, especially if it
ever appeared in a committed `.env` file:

```bash
php artisan key:generate --force
```

This rewrites `APP_KEY` in `.env`. Do it on the server, after copying `.env`.

**Optional hardening:** because the old key is in the repository history, treat
it as public forever. Rewriting published history (to scrub it) is destructive
and is left to the repository owner; if you want it gone, use
`git filter-repo` or the BFG repo cleaner and force-push, coordinating with
everyone who has a clone.

---

## 5. Database

```bash
php artisan migrate --force
php artisan db:seed --class=Database\Seeders\ProductionSeeder --force
# Optional: load your own opening catalogue with a seeder you control.
# php artisan db:seed --class=Database\Seeders\YourCatalogueSeeder --force
```

- `migrate` builds the schema. It also guarantees the core posting accounts the
  accounting code looks up (`1001`, `1002`, `1200`, `3000`, `4000`, `5000`), so
  the app will not crash on the first sale even before seeding.
- `ProductionSeeder` is idempotent and creates **only** what a real deployment
  needs: the Main Branch, the chart of accounts, the default expense categories,
  branding defaults and one administrator. It does **not** insert the demo
  drugs/products.
- If you have an opening catalogue, load it with a seeder you control. Keep it
  idempotent and match rows on natural keys so a second run adds nothing.
  Review minimum stock levels and packing before go-live: a seeder cannot know
  the reorder level for your shop, and guessing "sold whole" for a container
  hides the units inside it.
- If an existing database has medical supplies, vitamins or infant formula on
  the **Products** side, move them once with:

  ```bash
  php artisan pmas:move-products-to-drugs          # dry run, lists the rows
  php artisan pmas:move-products-to-drugs --apply  # moves them
  ```

  It keeps batches, stock and sales history, and merges a product into an
  existing drug when the names match. Run the dry run first and read the list.

- If `ADMIN_PASSWORD` is blank, the seeder generates a strong password and
  prints it once. Copy it, then sign in with the administrator **username**
  (`ADMIN_USERNAME`, default `admin`) and change the password immediately
  (see step 9). Staff sign in with a username, not an email address.

Do **not** run the default `php artisan db:seed` in production — it also loads
`CatalogSeeder`, which puts ~15 demo drugs and ~20 demo products into the live
catalog.

---

## 6. Storage and caches

```bash
php artisan storage:link
php artisan optimize
```

`storage:link` is what makes uploaded logos and the default logo visible under
`public/storage`. `optimize` caches config, routes and views — remember that
while these caches exist, edits to `.env`, route files and Blade templates are
ignored until you run `php artisan optimize:clear`.

Set the correct permissions so the web user can write to `storage/` and
`bootstrap/cache/`.

---

## 7. Scheduler (housekeeping + backups)

Add one cron line for the web user:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

This runs:

- `cache:prune-stale-tags` hourly (no-op unless a taggable store such as
  Redis is used), and
- `pmas:backup` nightly at 02:30.

Session garbage collection is automatic: Laravel's session handler performs it
on a lottery during normal requests, so there is no `session:gc` command to
schedule.

### Backups

`pmas:backup` shells out to `mysqldump` and writes a gzipped SQL dump to
`storage/app/private/backups`, keeping the newest `BACKUP_KEEP` files (default
14). It finds `mysqldump` automatically on Windows; elsewhere put it on `PATH`
or set `MYSQLDUMP_PATH` in `.env`.

```bash
php artisan pmas:backup                 # run now
php artisan pmas:backup --keep=30       # override retention for this run
```

The password is passed through the `MYSQL_PWD` environment variable, never on
the command line. Backups are body-of-the-database dumps (`CREATE TABLE` +
`INSERT`); restore with `mysql` (or `phpMyAdmin`):

```bash
gunzip -c pmas-YYYY-MM-DD_HHMMSS.sql.gz | mysql -u USER -p DATABASE
```

Store a copy **off the machine** as well — a backup on the same disk does not
survive a disk failure. `BACKUP_KEEP` only bounds disk growth, not disaster
recovery.

---

## 8. Serving over HTTPS

### 8a. Which web server

Ask your hosting provider before configuring anything; shared hosting is
usually Apache, but nginx is common too, and the setting differs. Use the
hosting panel's standard Laravel configuration for whichever server you have.

If the control panel only offers a "document root" field, you are on Apache:
set it to **`…/pmas/public`**, never the project root. Pointing the server
at the project root would serve `.env`, the database backups and `vendor/` to
anyone who guessed a URL — `public/.htaccess` already denies dotfiles, but that
does not help if the root is wrong.

Two things to get from support, whichever server it is:

- **`AllowOverride All`** (Apache). Without it `public/.htaccess` is ignored and
  every URL except `/` returns 404.
- **The PHP version** (8.3 or 8.4) and the **FastCGI socket path** for nginx.

### 8b. The proxy

If anything sits in front of PHP — almost always true on shared hosting, where
the control panel's own front end terminates the request — set:

- `SESSION_SECURE_COOKIE=true`, so the session cookie is never sent over plain
  HTTP. This is a **fail**: with it false, the cookie that identifies a
  signed-in staff member travels in clear text.
- `TRUSTED_PROXIES` to the proxy's address, so `X-Forwarded-Proto` is honoured.
  **Leave this blank and the HTTPS redirect in `SecurityHeaders` will loop
  forever** — every request looks like plain HTTP, so it redirects to HTTPS,
  which looks like plain HTTP again.
- re-run `php artisan optimize` so the new configuration is picked up.

Prefer a single IP over `*`. Use `*` only if nothing untrusted can reach PHP
directly.

### 8c. Certificate

Once step 0 is done and the name resolves:

```bash
sudo certbot certonly --webroot -w /path/to/pmas/public -d pharmacy.example.com
nginx -t && systemctl reload nginx     # or apache2ctl configtest && systemctl reload apache2
```

Certbot edits the server config for you. Afterwards the HTTP block should
redirect rather than serve, and `Strict-Transport-Security` (already set by
both `SecurityHeaders` and the server configs) starts doing its job.

`SESSION_SECURE_COOKIE=true` also depends on this: a secure-only cookie is not
sent at all over HTTP, so **enabling it before the certificate exists logs
everyone out and looks like a broken login.**

---

## 9. Go-live checklist

Run these in order. The first block has to happen before the host is useful at
all; the second is the last thing to do before telling anyone the address.

**Name and certificate**

- [ ] Domain registered and pointed at the host.
- [ ] `A` record for `pharmacy.example.com` pointing at the host.
- [ ] `www` decided on, and the other form redirects to it.
- [ ] Name verified to resolve **from a phone on mobile data**, not office Wi-Fi.
- [ ] Certificate issued and the HTTP block now redirects to HTTPS.
- [ ] `SESSION_SECURE_COOKIE=true` **after** the certificate exists.

**Install**

- [ ] `.env` filled in, `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `APP_URL` has **no trailing slash**: `https://pharmacy.example.com`.
- [ ] Fresh `APP_KEY` generated on the server (step 4). If an older key was
      ever committed to git, treat it as public and do **not** reuse it.
- [ ] `migrate --force` and the seeders completed without errors.
- [ ] Logged in as the seeded administrator and **changed its password**.
- [ ] Document root is `…/pmas/public`, and `AllowOverride All` is set if
      the host is Apache.
- [ ] `TRUSTED_PROXIES` set if anything fronts PHP.

**Business**

- [ ] Confirmed you can record a purchase, make a sale, and take an expense
      without errors (proves the chart of accounts is complete).
- [ ] Business Settings: correct name, logo, VAT rate and receipt footer.
- [ ] `storage:link` done; logo shows on the login page.
- [ ] Staff accounts created with roles matched to what each person may do, and
      the owner account is **not** left on the default password.
- [ ] Opened the site once and answered the drawer count at the prompt. It is
      remembered, not posted to the books — the opening float is a baseline for
      the shift's variance, not a transaction.

**Operations**

- [ ] Scheduler cron installed; `php artisan schedule:list` shows the jobs.
- [ ] `php artisan pmas:backup` produces a file, **restored onto a scratch
      database** to prove it works, then copied off the machine.
- [ ] `php artisan optimize` run; `php artisan about` reports production.
- [ ] Health endpoint `GET /up` returns 200 from outside the office.

**The one command that checks most of this**

```bash
php artisan pmas:preflight
```

It reads the live configuration and refuses to call the install ready while
anything blocking is wrong — debug on, the leaked `APP_KEY` still in use, a
plain-HTTP `APP_URL`, a non-secure session cookie, missing migrations, an
unwritable `storage/`. It exits non-zero when something fails, so it can gate a
deploy script:

```bash
php artisan optimize && php artisan pmas:preflight || echo "DEPLOY BLOCKED"
```

Add `--json` for machine-readable output, or `--skip-dns` on a box with no
outbound DNS. Read the warnings too — they will not fail the run, but "no backup
exists" and "storage link missing" are both worth knowing about before opening.

---

## 10. Cash handling and opening balances (addressed)

Both gaps found during the schema review are now implemented:

- **Till closing posts its variance.** Closing a till still records the cash
  count, and now also posts the difference between counted and expected cash to
  `5200 Cash Over/Short`: an overage debits `1001 Cash` and credits `5200`; a
  shortage debits `5200` and credits `1001`. A till that matches posts nothing,
  and the ledger cash balance then agrees with the physical drawer.
- **Opening balances are entered at go-live.** Owner/Admin have an **Opening
  Balances** page (Finance menu) to set day-one cash, Telebirr / CBE Birr / bank /
  card, receivables, stock at cost and supplier payables. Owner's capital is
  derived so the entry always balances, and the page posts only the difference
  from the current books — so re-saving the same figures, or correcting a
  typo, changes nothing extra. Do this **once, before recording sales or
  purchases**.
---

## 13. Hardening when you expose this app to the internet

The app is a single-tenant pharmacy system. Before it leaves the LAN (hosting, public
IP, remote access, reverse proxy):

- **TLS is mandatory.** Terminate HTTPS at the reverse proxy and set
  `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` (the proxy's IP), and re-run
  `php artisan optimize`. The template already has these knobs.
- **Keep `APP_ENV=production` and `APP_DEBUG=false`.** The `SecurityHeaders`
  middleware sends HSTS (`max-age=31536000; includeSubDomains`), `X-Frame-Options:
  SAMEORIGIN`, `X-Content-Type-Options: nosniff`, a `Referrer-Policy`,
  `Permissions-Policy` (camera/mic/geolocation disabled) and a relaxed
  `Content-Security-Policy`. It also 301-redirects plain HTTP to HTTPS.
- **Firewall the database.** MySQL should listen on `127.0.0.1` only (or a
  private interface); never expose port 3306. Restrict the app to the proxy only.
- **Lock admin access** - change the seeded password on first login, create one
  account per person, and deactivate leavers. Login is per-credential rate
  limited (`throttle:5,1`), but there is no 2FA, so restrict by IP or put the
  app behind Cloudflare Access / Tailscale / a VPN if your pharmacy does not need
  to be reached from arbitrary networks.
- **`X-Forwarded-*` headers** must not be trusted from arbitrary clients - set
  `TRUSTED_PROXIES` to the real proxy, not `*`, whenever you can.
- Keep secrets out of git (`.env`, keystores, uploads), and `optimize` only after
  `.env` and routes are final.

## 14. Latency: making it fast

Most perceived speed comes from the server and ops, not the app:

- **HTTP/2 (or 3)** on the reverse proxy - enabled by default in modern nginx;
  cannot be toggled from PHP. Multiplexes assets.
- **Gzip/Brotli** at nginx for text assets (`text/*`, `application/json`).
- **nginx location for static assets** `expires 1y` + `add_header Cache-Control
  "public, immutable"` for the `/build/` folder (Vite emits hashed filenames, so
  immutable caching is safe). Make sure `/sw.js` and `/manifest.webmanifest` are
  not cached (`Cache-Control: no-cache`) so PWA updates ship promptly.
- **OPcache** in the php.ini - `opcache.enable=1`,
  `opcache.memory_consumption=128`, `opcache.validate_timestamps=0` (call
  `opcache_reset()` from `php artisan` after deploys).
- **MySQL**: `innodb_buffer_pool_size` ~ 50-70% of RAM on a dedicated DB box.
  This database stays small (a few GB after years), so tuning beyond this is
  rarely needed.
- **Run `php artisan optimize`** so config, routes and views are cached
  (`optimize:clear` after `.env` or route edits).
- Keep the app on the same region as its users - no point racing intercontinental
  links for a pharmacy.

## 15. Internet dependency (offline behaviour)

The app was built for local, offline operation; hosting it puts sales on a link.

**What the app does now, online or off:**

- A service worker (`/sw.js`) caches the static assets (CSS, JS, logo) so the
  layout loads on a weak connection, and an `offline.html` page is shown for any
  navigation that fails because the network is down.
- A red banner appears at the top of every page whenever the browser is offline.
- Form submissions just fail with the browser's normal behaviour; no
  transaction is silently lost (no offline write queue ships with this version).

**What it does not do:** it cannot record a sale while disconnected - a
server-rendered POS needs the server to validate stock, prices, VAT, accounting
and number sequences.

**Mitigations, in order of decreasing value:**

1. **Keep a local fallback on site.** If possible, keep a second copy running
   `php artisan serve` on an always-on PC on the LAN. Staff switch to its URL
   (`http://<ip>:8010`) if the hosted one drops. Caveat: it must sync before
   anyone uses the hosted copy again, otherwise you double-sell stock - this
   split-brain case needs a written procedure, not just software.
2. **Network resilience:** wired LAN, a UPS on the router/switch, and a
   4G/5G failover router with automatic fallback. This fixes ~95% of real
   outages for cheap.
3. **Hosting region close to you** cuts latency and reduces the chance of an
   international link being the bottleneck.
4. **A documented "offline procedure"** (paper forms + take just cash, re-enter
   later) is, honestly, the cheapest and most robust option for rare outages.

If you need **true offline-first sales** (record in the browser while
disconnected, sync when back), that is a much larger feature (IndexedDB +
background sync + conflict resolution) and is best planned as a dedicated
phase rather than bolted on. Worth naming precisely so you know the limit of
the mitigation shipped here.

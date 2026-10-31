<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## PMAS — running the system locally

Laravel 13 / PHP 8.4 pharmacy management system (dispensing, purchases, supplier
payments, expenses, stock transfers, till closing, reports). The business name,
logo, colours and the VAT rate are **not** in `.env` — they are set in the app
under Business Settings, and the screens read them from the `settings` table.

Requirements: PHP 8.4 with `pdo_mysql`, `gd`, `zip`, `fileinfo` (Herd supplies
these), MySQL/MariaDB reachable on `127.0.0.1:3306`, Node 24+ and Composer.

First-time setup, from the project folder:

```bash
composer setup          # deps, .env, key:generate, migrate, seed, storage:link, npm build
```

That seeds the chart of accounts, the default expense categories, branding
defaults and one administrator from `ADMIN_USERNAME` / `ADMIN_EMAIL` /
`ADMIN_PASSWORD` in `.env` (defaults: `admin` / `admin@pmas.com`), plus a
starter catalog of demo drugs and products. If `ADMIN_PASSWORD` is blank, the
seeder generates a strong password and prints it once. Staff sign in with their
username, not their email.

Then point the database keys in `.env` at your server (`DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`) and run `php artisan migrate --force` again.

Serve it with any web server pointed at the `public/` folder, or use the built-in
development server:

```bash
composer serve          # php artisan serve --host=127.0.0.1 --port=8010 → http://127.0.0.1:8010
```

The `serve` script uses port 8010; change it in `composer.json` if that port is
taken.

### Hosting it for the pharmacy

Copy `.env.production.example` to `.env`, fill in the database credentials and
set `APP_ENV=production`, `APP_DEBUG=false`, then:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate --force                       # generate a NEW key; never reuse the dev key
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force
php artisan storage:link
php artisan optimize                                   # caches config, routes and views
```

`ProductionSeeder` creates only what a real deployment needs — the Main Branch,
the chart of accounts, the default expense categories, branding defaults and
one administrator — and deliberately does **not** insert the demo drugs and
products from `CatalogSeeder`. If `ADMIN_PASSWORD` is left blank the seeder
generates a strong password and prints it once; store it, log in, and change it
under the user's profile straight away.

Add the scheduler cron so session cleanup, cache pruning and the nightly
database backup run automatically:

```bash
# On the server, add this one line to crontab -e:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The nightly `pmas:backup` writes a gzipped SQL dump to
`storage/app/private/backups` and keeps the newest `BACKUP_KEEP` files (default
14). It needs `mysqldump`, which it finds automatically on Windows; otherwise
put it on `PATH` or set `MYSQLDUMP_PATH`. Run it by hand any time with
`php artisan pmas:backup`.

Before exposing this host beyond the local network, serve it over HTTPS:
terminate TLS at a reverse proxy and set `SESSION_SECURE_COOKIE=true`; if that
proxy is not on this host, set `TRUSTED_PROXIES` to its address(es) so the app
sees the real scheme and client IP.

For the full production runbook, see [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).
For the upload-based update loop, see [`docs/UPDATES.md`](docs/UPDATES.md).

`php artisan optimize:clear` afterwards — while those caches exist, edits to
`.env`, routes and Blade files are ignored. `APP_TIMEZONE` defaults to `UTC` in
both templates; set it to the pharmacy's local timezone so "due today" supplier
and bill reminders use the right day.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

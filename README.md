# Drive-Thru Plus

A bilingual (Arabic / English) multi-tenant SaaS for digital drive-thru ordering, built on Laravel 13 and Filament.
The platform super admin onboards restaurants. Each restaurant owner runs branches, a team in positions, menus, customers and live orders.

- Spec: [docs/MASTER_PROMPT.md](docs/MASTER_PROMPT.md)
- Phases and progress: [docs/ROADMAP.md](docs/ROADMAP.md)
- How it is built: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- What changed: [docs/CHANGELOG.md](docs/CHANGELOG.md)

## Requirements

PHP 8.4 (intl, mbstring, pdo_sqlite or pdo_mysql, sodium), Composer 2, Node 24.

## First run

```bash
composer setup
php artisan db:seed
composer dev
```

Then open http://127.0.0.1:8000.

| Area | URL | Demo account (password: `DEMO_PASSWORD`, default `password`) |
|---|---|---|
| Platform console | `/admin` | `admin@drivethru.test` (super admin), `support@drivethru.test` (Support role) |
| Restaurant workspace | `/app` | `owner@masar.test` (owner), `manager@masar.test`, `cashier@masar.test`, `kitchen@masar.test` |

Demo seeding refuses to run in production. For a real install, create the super admin with:

```bash
php artisan platform:install
```

## Quality

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/pest
```

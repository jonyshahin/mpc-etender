# MPC e-Tender

Digital procurement platform for construction projects. Internal MPC users manage tenders, vendors register and bid, evaluation committees score submissions, and approval workflows enforce spend authority.

## Tech stack

| Layer       | Technology                                          |
| ----------- | --------------------------------------------------- |
| Backend     | Laravel 13.x, PHP 8.4+                              |
| Frontend    | React 19 via Inertia.js (no separate REST API)      |
| Database    | MySQL 8.0+ (UUID primary keys on all tables)        |
| Cache/Queue | Redis (sessions, cache, queues via Horizon)         |
| Real-time   | Laravel Reverb (WebSocket)                          |
| Storage     | S3-compatible object storage                        |
| Search      | Laravel Scout + Meilisearch                         |
| Auth        | Sanctum, multi-guard (`web` for MPC, `vendor`)      |
| Testing     | Pest PHP (target ≥80% coverage)                     |
| Code style  | Laravel Pint                                        |
| Local dev   | Laravel Sail (Docker)                               |

## Prerequisites

- PHP 8.4+
- Composer 2.x
- Node 20+ and npm
- Docker Desktop (for Sail-managed MySQL/Redis/Meilisearch)

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
php artisan migrate --seed
composer run dev
```

## Common commands

```bash
composer run dev          # Dev server (Vite + queue + artisan serve)
php artisan test          # Run Pest test suite
php artisan test --coverage --min=80
./vendor/bin/pint         # Format PHP per Pint config
php artisan horizon       # Queue worker dashboard
php artisan reverb:start  # WebSocket server
php artisan migrate       # Apply migrations
php artisan db:seed       # Seed roles, permissions, categories, admin user
```

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — domain layout and conventions
- [DATABASE.md](DATABASE.md) — table reference
- [API.md](API.md) — route registry
- [CODING_STANDARDS.md](CODING_STANDARDS.md) — style and patterns
- [SECURITY.md](SECURITY.md) — RBAC, bid sealing, project isolation
- [TESTING.md](TESTING.md) — testing strategy
- [DEPLOYMENT.md](DEPLOYMENT.md) — Laravel Cloud deployment
- [LOCALIZATION.md](LOCALIZATION.md) — i18n and RTL
- [NOTIFICATION_CHANNELS.md](NOTIFICATION_CHANNELS.md) — WhatsApp/SMS/email
- [CHANGELOG.md](CHANGELOG.md) — sprint changelog

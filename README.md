# Prodmee Slate — API (Laravel)

REST API for Prodmee Slate: a film/TV development-slate tool. Passwordless OTP
auth, role-based access, S3 file storage, the **Sicala** AI proxy (Anthropic),
token-usage analytics, and public share links.

Pairs with the Vue SPA in `prodmee-slate-web`.

## Stack
- Laravel 13 + Sanctum (token auth for the SPA)
- PostgreSQL (SQLite supported for local/test)
- S3 (files), SES (email), Anthropic (Sicala)
- Docker (PHP-FPM + Nginx), AWS Fargate in production

## Local development

### Option A — Docker (Postgres included)
```bash
cp .env.example .env
php artisan key:generate            # or set APP_KEY manually
docker compose up --build
# API on http://localhost:8001
docker compose exec api php artisan migrate --seed
```

### Option B — bare PHP (SQLite, fastest)
```bash
composer install
cp .env.example .env
# set DB_CONNECTION=sqlite and create the file:
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8001
```

> Note: PHP's `glob()` treats `[` and `]` as character classes, so Laravel's
> migration discovery fails if the project path contains brackets (e.g.
> `/…/[PROJECT]/…`). Run from a bracket-free path, or use the Docker image where
> the app lives at `/var/www`.

## Auth flow (passwordless OTP)
1. `POST /api/auth/request-code { email }` — invite-gated; emails a 6-digit code (10-min, single-use). Always returns a generic message.
2. `POST /api/auth/verify-code { email, code }` — returns a Sanctum `token` + `user`.
3. Send `Authorization: Bearer <token>` on subsequent requests.

Seeded admin: `guillaume.de.fonvielle@prodmee.test` (request a code; in local
`MAIL_MAILER=log` the code is written to `storage/logs/laravel.log`).

## Key endpoints
- `GET /api/meta`, `GET /api/people`
- `GET|POST /api/projects`, `…/{id}` CRUD, `…/{id}/stage`, `…/{id}/access`
- `…/{id}/comments`, `…/{id}/checklist`, `…/{id}/links`, `…/{id}/files` (S3 presign)
- `GET|POST /api/buyers`, `GET|POST /api/pitches`, `…/{id}/status`
- `POST /api/ai/assistant`, `POST /api/ai/autofill` (Sicala)
- `settings/*` (admin only): `users`, `api-key`, `usage`
- `GET /api/share/{token}` (public, sanitized one-pager)

## Tests
```bash
php artisan test
```

## Deployment
See [DEPLOY.md](DEPLOY.md) — Terraform in `infra/` provisions ECS Fargate, RDS,
S3, CloudFront, SES, Route 53/ACM and Secrets Manager; GitHub Actions deploys.

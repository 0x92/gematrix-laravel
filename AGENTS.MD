# AGENTS.MD

This file defines working rules for coding agents operating in this repository.

## Scope

Applies to the entire project rooted at this directory.

## Core Principles

- Keep all user-facing UI text in English.
- Keep the visual style dark and consistent with Gematrix Ops.
- Prefer safe, incremental changes over broad rewrites.
- Do not remove existing data unless explicitly requested.

## Git Workflow (Mandatory)

- After every successful feature, bug fix, or requested adjustment, create a Git commit automatically.
- A change is "successful" when implementation is complete and relevant checks/tests have passed or were explicitly acknowledged as not runnable.
- Commit messages should be short and explicit, e.g.:
  - `fix: sanitize crawler source suffix handling`
  - `feat: add cipher card live filtering`

### Critical Change Handling

- The agent must decide if a change is critical based on risk.
- Treat a change as critical when at least one applies:
  - data migration or mass data rewrite/deletion
  - auth, permissions, or security-sensitive logic
  - infrastructure/runtime changes (deployment, Docker, scheduler behavior)
  - broad cross-module refactors with regression risk
- For critical changes:
  - create a dedicated branch first (example: `critical/<short-topic>`)
  - commit work on that branch
  - ask for user confirmation that behavior is as expected
  - merge into `main` only after explicit user confirmation

## Architecture Rules

- Framework: Laravel.
- Database: PostgreSQL.
- Runtime: Docker Compose services in `docker-compose.yml`.
- Scheduler must remain active via the `scheduler` service.

## Crawler Rules

When editing the news crawler:
- preserve autonomous operation via Laravel Scheduler
- never force-enable disabled sources during source sync
- sanitize incoming headlines before persistence
- prevent source suffix pollution (e.g. `- bloomberg.com`)
- reject obvious mojibake/garbled headline artifacts
- keep phrase and token enrichment in sync with `phrases`

Relevant files:
- `app/Services/NewsCrawlerService.php`
- `app/Http/Controllers/Admin/NewsCrawlerController.php`
- `resources/views/admin/news_crawler.blade.php`
- `config/news_crawler.php`
- `routes/console.php`

## Data Integrity

- Dedupe headlines using stable hashing (`url_hash`).
- Recompute and persist all cipher columns when syncing phrases.
- Keep cleanup operations idempotent.

## Coding Conventions

- Use PHP 8.4-compatible syntax.
- Prefer clear, explicit method names.
- Keep comments concise and only where useful.
- Avoid adding new dependencies unless necessary.

## Verification Checklist

After significant changes run:

```bash
docker compose exec -T app php -l app/Services/NewsCrawlerService.php
docker compose exec -T app php artisan test
docker compose exec -T app php artisan schedule:list
```

For crawler changes also run:

```bash
docker compose exec -T app php artisan news:crawl-headlines --limit=5
```

## Permissions / Ops Notes

- Ensure writeable paths remain owned by `www-data`:
  - `storage/`
  - `bootstrap/cache/`
- If Blade compile errors appear (`Permission denied`), fix ownership before other debugging.

## Non-Goals

- Do not introduce German content.
- Do not add light theme variants.
- Do not bypass Docker-based runtime assumptions.

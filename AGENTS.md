# AGENTS.md — shared guidance for all AI coding assistants

This repo is worked on by humans and by multiple AI assistants (Claude Code, and others).
Claude Code reads `CLAUDE.md` + `.claude/`; non-Claude tools read this file. Keep the two in
sync when you change a rule. Rules here are tool-agnostic on purpose.

## Stack

- Backend: Laravel 12, PHP 8.4, PostgreSQL.
- Frontend: Inertia 2 + React (in `resources/js/`), Vite, Tailwind.
- Storage: Supabase is **S3-compatible object storage only** (disk `bukti-kepatuhan` for
  compliance evidence). It is NOT the database. The database is PostgreSQL.
- AI context help: Claude Code uses the `laravel-boost` MCP for schema/error/doc lookup;
  replicate that by reading `database/` and `config/` directly if your tool lacks MCP.

## Layout

- `app/` backend · `resources/js/` Inertia/React frontend · `routes/` routes
  (`web.php`, `api.php`, `console.php`) · `database/` migrations/seeders/factories
  · `tests/` Pest tests · `config/filesystems.php` storage disks.

## Rules every assistant must follow

1. **Tests required for backend changes.** New backend behavior (controller, service, action,
   job) ships with a Pest test. Run `composer test` before pushing.
2. **Frontend testing — layered, not mandatory-by-count** (empirically: integration/behavior
   tests catch more user-visible bugs than unit; TypeScript+ESLint already remove a fault class):
   - **Static gate (mandatory):** TypeScript strict + ESLint on every PR (`npm run lint`).
   - **Unit tests only for non-trivial logic:** price/tax/date math, validation, state machines,
     prop transforms (Vitest). Don't unit-test pure presentational components.
   - **Integration (encourage):** React Testing Library, render real DOM, no shallow rendering.
   - **E2E (few critical only):** Playwright/Cypress for login→core / customer-visible journeys.
   - No coverage-% mandate.
2. **Format before committing.** PHP → `laravel/pint` (`composer format`, check `composer format:test`).
   Frontend → `npm run lint` (eslint --fix) and `npm run format` (prettier).
3. **Never edit `.env`.** Edit `.env.example`; create local `.env` via `cp .env.example .env`.
   Secrets stay out of the repo.
4. **Thin controllers / services.** Business logic lives in services or actions, not the controller.
5. **Validation at the boundary.** Use Laravel Form Requests; reuse them across endpoints.
6. **Eloquent over raw SQL**; eager-load (`with()`) to avoid N+1; verify with the schema when unsure.
7. **Inertia, not a hand-rolled API.** Server data flows to React via props from `Inertia::render`.
   Use `useForm()` / `useRemember()`; live validation via Laravel Precognition if wanted.
8. **Authorization via policies/`Gate`**, never inline permission checks in routes/controllers.
9. **Migrations are reversible** and one concern each; add indexes for filtered columns; FKs via `foreignId`.

## Commit hygiene (all tools)

- Branch from `main`; PR to `main`.
- Short imperative subject; minimal body.
- No `Co-Authored-By` trailer.

## Context-engineering notes (for tools that support it)

- Explore via schema/error tools before grepping migrations.
- Prefer targeted reads over whole-directory dumps; dispatch a sub-agent for broad investigations
  so the main context stays small.
- This file mirrors `CLAUDE.md`. If you change a rule, update both.

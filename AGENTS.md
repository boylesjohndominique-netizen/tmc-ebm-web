# tmc-ebm-system-web — Laravel API + React admin

Shared rules are in `../AGENTS.md`. This submodule holds two apps: `backend/`
(Laravel) and `frontend/` (React). Read the section you need.

## Backend — Laravel 12 / PHP 8.2
Facts: Laravel 12, PHP 8.2, MySQL database `tmc_equipment`, Laravel Sanctum
(token auth), Laravel Reverb (WebSockets). MVC with `app/Http/Controllers`
(+ `Admin/`), `Requests`, `Resources`, `Models`, `Services`, `Observers`,
`Events`, `Mail`. Routes in `routes/api.php`.

Commands (from `backend/`):
- `php artisan test` — PHPUnit; `--filter` to run a single test
- `vendor/bin/pint --dirty` — format changed PHP files
- `php artisan serve` — API on `http://127.0.0.1:8000`
- `php artisan migrate` — ASK before any destructive operation

Rules:
- Use Form Requests for validation; return API Resources; keep controllers thin.
- Enforce authorization (policies/permissions, `UserPermission` model); never
  trust client input. Return safe, consistent JSON error shapes.
- Use Eloquent relationships and parameterized/bound queries. Never edit applied
  migrations destructively; add a new migration instead.
- Do not run `migrate:fresh`, `db:wipe`, or seed data without approval. Keep
  `.env` secrets out of code and commits.
- Preserve Sanctum/Reverb behavior and broadcast-after-commit semantics.

## Frontend — React 19 / Vite 7
Facts: React 19, Vite 7, `react-router-dom` 7, `axios`, `laravel-echo` +
`pusher-js`. Layout: `src/components`, `context` (Auth/Notification/Theme),
`hooks`, `layouts`, `pages/{admin,auth}`, `services` (`api.js`, `realtime.js`),
`utils`. Styling is plain CSS (`src/index.css`) with TMC yellow/blue/white.

Commands (from `frontend/`):
- `npm run dev` — Vite on `http://localhost:5180`
- `npm run build` — production build; use it to verify changes
- No lint/test script is configured; do not invent one.

Rules:
- Reuse existing components, hooks (`useRealtimeResource`, etc.), contexts, and
  `services/api.js`; do not duplicate request logic or add dependencies.
- Keep the existing routing, auth context, and realtime refetch pattern intact.
- Provide loading, error, empty, and responsive states; keep accessibility
  (labels, focus, keyboard) and safely handle user input.
- Match existing CSS/theme conventions and verify no console errors.

## Shared API / MySQL contract
- The mobile app (`tmcebms/`) and the React app both consume the Laravel API;
  preserve request/response shapes you do not own. Check the controller/resource
  and `tmcebms/lib/data/api_service.dart` before changing an endpoint.
- Base API URL: `http://127.0.0.1:8000/api` (`VITE_API_URL`); endpoints in
  `routes/api.php`.
- Never change a response shape, field name, or auth mechanism without checking
  all three consumers.

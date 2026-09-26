# TMC Equipment Borrowing and Tracking Management System

Stack:
- Laravel 12 REST API
- Laravel Sanctum authentication
- Laravel Reverb (WebSockets) — realtime updates, no manual refreshing
- MySQL / phpMyAdmin
- React + Vite
- React Router
- Axios
- CSS using TMC Yellow, Blue and White

## Quick start (all three services at once)

The system needs three processes running: the Laravel API, the Reverb
WebSocket server (realtime), and the Vite frontend.

**Windows (simplest):** double-click `start-dev.bat` — it opens one window
per service. Run `stop-dev.bat` if a port ever stays busy after a crash.

**One terminal (Git Bash / WSL / macOS / Linux):**

```bash
./start-dev.sh
```

Press `Ctrl+C` once to stop all three. Custom ports if the defaults are
taken:

```bash
TMC_API_PORT=8010 TMC_REVERB_PORT=8090 TMC_VITE_PORT=5180 ./start-dev.sh
```

URLs: API `http://127.0.0.1:8000` · WebSockets `ws://127.0.0.1:8080` ·
Frontend `http://localhost:5173`

## Backend setup

1. Create a MySQL database named `tmc_equipment`.
2. Copy `backend/.env.example` to `backend/.env`.
3. Open a terminal in `backend`.
4. Run:
   - `composer install`
   - `php artisan key:generate`
   - `php artisan migrate --seed`
5. Start the backend with `php artisan serve` (or use `start-dev.bat` / `start-dev.sh`, which also start Reverb).
6. API runs at `http://127.0.0.1:8000`.

Default administrator:
- Username: `admin`
- Password: `password123`

Change this password after testing.

## Frontend setup

1. Open a second terminal in `frontend`.
2. Run:
   - `npm install`
   - `npm run dev`
3. Open the Vite URL, normally `http://localhost:5173`.

If needed, copy `frontend/.env.example` to `frontend/.env` and keep:

```
VITE_API_URL=http://127.0.0.1:8000/api
VITE_REVERB_APP_KEY=tmc-equipment-local
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

## Realtime architecture

- Backend observers broadcast lightweight change signals (`equipment`,
  `borrowings`, `returns`, `users`, notifications) over Reverb on the
  private `staff` channel and per-user `user.{id}` channels.
- The frontend (`src/services/realtime.js` + `useRealtimeResource` hook)
  refetches the affected API endpoints when a signal arrives, on tab
  focus, and after a reconnect — filters and UI state are preserved.
- Broadcasts fire only after database transactions commit and never fail
  an API request if the WebSocket server is down (degrades gracefully).

## Important

This is a complete starter implementation of the agreed architecture. Before final deployment, add production email password reset, stronger validation, audit logs, automated overdue notification scheduling, and formal test coverage.

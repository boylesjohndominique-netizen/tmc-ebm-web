#!/usr/bin/env bash
# ============================================================
#  TMC Equipment System — one-command dev launcher
#  (Git Bash, WSL, macOS, Linux)
#
#  Starts in this single terminal:
#    1. Laravel API       -> http://127.0.0.1:8000
#    2. Reverb WebSockets -> ws://127.0.0.1:8080  (realtime)
#    3. Vite frontend     -> http://localhost:5173
#
#  Press Ctrl+C ONCE to stop all three.
#
#  Port is taken? The script refuses to start instead of stacking a
#  second copy. Free the ports with stop-dev.bat (or close the other
#  windows) and run again. Custom ports are available as env overrides:
#    TMC_API_PORT=8010 TMC_REVERB_PORT=8090 TMC_VITE_PORT=5180 ./start-dev.sh
# ============================================================

set -u
cd "$(dirname "$0")"

API_PORT="${TMC_API_PORT:-8000}"
REVERB_PORT="${TMC_REVERB_PORT:-8080}"
VITE_PORT="${TMC_VITE_PORT:-5173}"

# ---- Preflight checks (fail early with a clear message) ----
if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php not found in PATH."; exit 1
fi
if [ ! -f backend/artisan ]; then
  echo "ERROR: backend/artisan not found — run this script from the project root."; exit 1
fi
if [ ! -f backend/vendor/autoload.php ]; then
  echo "ERROR: backend dependencies missing. Run:  cd backend && composer install"; exit 1
fi
if ! command -v npm >/dev/null 2>&1; then
  echo "ERROR: npm not found in PATH."; exit 1
fi
if [ ! -d frontend/node_modules ]; then
  echo "ERROR: frontend dependencies missing. Run:  cd frontend && npm install"; exit 1
fi

# ---- Port guard: never start on top of a running instance ----
port_busy() {
  if command -v netstat >/dev/null 2>&1; then
    netstat -aon 2>/dev/null | grep "[:.]$1 " | grep -qi LISTENING
  else
    (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null && { exec 3>&- 3<&-; return 0; } || return 1
  fi
}

for p in "$API_PORT" "$REVERB_PORT" "$VITE_PORT"; do
  if port_busy "$p"; then
    echo ""
    echo "ERROR: port $p is already in use — the system (or part of it) seems to be running already."
    echo "       Close the other windows/terminals first, or run stop-dev.bat to free the ports."
    echo "       (Different ports can be chosen with TMC_API_PORT / TMC_REVERB_PORT / TMC_VITE_PORT.)"
    exit 1
  fi
done

PIDS=()
CLEANED=0

# Map an MSYS/Git Bash pid to its real Windows pid (best effort).
win_pid_of() {
  command -v ps >/dev/null 2>&1 || return 1
  ps -W 2>/dev/null | awk -v p="$1" '$2==p {print $4; exit}'
}

# Kill a launched service and its whole process tree. On Windows a plain
# `kill` orphans the php/node children, so we taskkill the Windows pid
# tree instead — scoped to exactly the processes this script started.
kill_tree() {
  local pid="$1" wpid
  wpid="$(win_pid_of "$pid" || true)"
  if [ -n "$wpid" ] && command -v taskkill >/dev/null 2>&1; then
    taskkill //F //T //PID "$wpid" >/dev/null 2>&1
  fi
  kill "$pid" 2>/dev/null
}

cleanup() {
  [ "$CLEANED" -eq 1 ] && return   # the trap can fire more than once — run once
  CLEANED=1
  echo ""
  echo "Stopping all services..."
  for pid in "${PIDS[@]:-}"; do
    [ -n "${pid:-}" ] && kill_tree "$pid"
  done
  wait 2>/dev/null
  echo "All services stopped."
}
trap cleanup EXIT INT TERM

echo "==> Starting API         (http://127.0.0.1:$API_PORT)"
( cd backend && exec php artisan serve --port="$API_PORT" ) &
PIDS+=("$!")

echo "==> Starting WebSockets  (ws://127.0.0.1:$REVERB_PORT)"
( cd backend && exec php artisan reverb:start --host=127.0.0.1 --port="$REVERB_PORT" ) &
PIDS+=("$!")

echo "==> Starting frontend    (http://localhost:$VITE_PORT)"
( cd frontend && exec npm run dev -- --port "$VITE_PORT" ) &
PIDS+=("$!")

echo ""
echo "All services running. Press Ctrl+C to stop everything."
echo ""

wait

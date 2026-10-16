@echo off
rem ============================================================
rem  TMC Equipment System - development launcher (Windows)
rem
rem  Opens three windows:
rem    1. Laravel API      -> http://127.0.0.1:8000
rem    2. Reverb WebSockets -> ws://127.0.0.1:8080  (realtime)
rem    3. Vite frontend     -> http://localhost:5180
rem
rem  Close a window (or Ctrl+C inside it) to stop that service.
rem  If a port stays busy after a crash, run stop-dev.bat once.
rem ============================================================

cd /d "%~dp0"

echo Starting TMC Equipment System development services...

start "TMC API - artisan serve"      cmd /k "cd backend && php artisan serve --host=0.0.0.0 --port=8000"
start "TMC WebSockets - reverb"      cmd /k "cd backend && php artisan reverb:start"
start "TMC Frontend - vite dev"      cmd /k "cd frontend && npm run dev"

adb reverse tcp:8000 tcp:8000 >nul 2>&1
adb reverse tcp:8080 tcp:8080 >nul 2>&1

echo.
echo   API         : http://127.0.0.1:8000
echo   WebSockets  : ws://127.0.0.1:8080
echo   Frontend    : http://localhost:5180
echo.
echo Three windows were opened. Keep them running while developing.
echo This launcher window can be closed.
timeout /t 4 >nul

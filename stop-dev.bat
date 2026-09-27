@echo off
rem ============================================================
rem  TMC Equipment System - dev port cleaner (Windows)
rem
rem  Kills anything still listening on the three dev ports.
rem  Use this if a crashed service left a port busy and
rem  start-dev.bat can no longer bind it.
rem ============================================================

echo Stopping processes on dev ports 8000, 8080, 5173, 5180 ...

for %%p in (8000 8080 5173 5180) do (
    for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":%%p " ^| findstr "LISTENING"') do (
        echo   killing PID %%a on port %%p
        taskkill /F /PID %%a >nul 2>&1
    )
)

echo Done.
pause

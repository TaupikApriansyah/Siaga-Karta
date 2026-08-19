@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\scripts\demo-start.ps1"
pause

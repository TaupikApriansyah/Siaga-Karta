@echo off
cd /d "%~dp0"
echo PERINGATAN: perintah ini menghapus database Docker SIAGA KARTA yang lama.
powershell -NoProfile -ExecutionPolicy Bypass -File ".\scripts\demo-reset.ps1"
pause

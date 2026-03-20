@echo off
REM Start Autopush Script
REM This batch file starts the autopush PowerShell script in a new window

echo Starting MicroFin Autopush Script...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0autopush.ps1"
pause

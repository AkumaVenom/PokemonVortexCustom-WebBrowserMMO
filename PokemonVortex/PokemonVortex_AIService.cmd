@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0"
title Pokemon Vortex - Always-on AI Service
if not defined PV_PHP_EXE if exist "%~dp0..\..\php\php.exe" set "PV_PHP_EXE=%~dp0..\..\php\php.exe"
if not defined PV_PHP_EXE if exist "C:\xampp\php\php.exe" set "PV_PHP_EXE=C:\xampp\php\php.exe"
if not defined PV_PHP_EXE for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PV_PHP_EXE set "PV_PHP_EXE=%%P"
if not defined PV_PHP_EXE (
    echo PHP CLI was not found. Set PV_PHP_EXE to your php.exe path.
    pause
    exit /b 1
)
if "%~1"=="" (
    "%PV_PHP_EXE%" -d display_errors=0 -d display_startup_errors=0 "%~dp0ai_service.php" --continuous
) else (
    "%PV_PHP_EXE%" -d display_errors=0 -d display_startup_errors=0 "%~dp0ai_service.php" %*
)
set "PV_AI_EXIT_CODE=%ERRORLEVEL%"
if "%~1"=="" if not "%PV_AI_EXIT_CODE%"=="130" pause
exit /b %PV_AI_EXIT_CODE%

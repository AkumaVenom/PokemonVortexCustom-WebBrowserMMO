@echo off
setlocal EnableExtensions DisableDelayedExpansion
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title Pokemon Vortex - Local World Server Console

set "PV_PHP_EXE="
if exist "%~dp0..\..\php\php.exe" set "PV_PHP_EXE=%~dp0..\..\php\php.exe"
if not defined PV_PHP_EXE if exist "C:\xampp\php\php.exe" set "PV_PHP_EXE=C:\xampp\php\php.exe"
if not defined PV_PHP_EXE for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PV_PHP_EXE set "PV_PHP_EXE=%%P"

if not defined PV_PHP_EXE (
    echo.
    echo Pokemon Vortex Server Console could not find PHP.
    echo Place the game under XAMPP htdocs or configure PV_PHP_EXE in this file.
    if "%~1"=="" pause
    exit /b 1
)

set "PV_POWERSHELL=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
if not exist "%PV_POWERSHELL%" (
    echo Windows PowerShell was not found; using the PHP console entry point.
    "%PV_PHP_EXE%" -d display_errors=0 -d display_startup_errors=0 "%~dp0server_console.php" %*
) else (
    "%PV_POWERSHELL%" -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0PokemonVortex_ServerConsole.ps1" -PhpExe "%PV_PHP_EXE%" %*
)
set "PV_EXIT_CODE=%ERRORLEVEL%"
if "%~1"=="" (
    echo.
    echo Server console closed. Apache and MySQL remain running.
    pause
)
exit /b %PV_EXIT_CODE%

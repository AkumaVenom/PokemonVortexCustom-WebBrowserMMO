@echo off
setlocal EnableExtensions
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title Pokemon Vortex - Local World Server Console
mode con: cols=170 lines=45 >nul 2>&1

set "PHP_EXE="
if exist "%~dp0..\..\php\php.exe" set "PHP_EXE=%~dp0..\..\php\php.exe"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"

if not defined PHP_EXE (
    echo.
    echo Pokemon Vortex Server Console could not find PHP.
    echo.
    echo Place the game under XAMPP htdocs or edit this file and set PHP_EXE
    echo to your php.exe path.
    echo.
    pause
    exit /b 1
)

"%PHP_EXE%" -d display_errors=0 -d display_startup_errors=0 "%~dp0server_console.php" %*
set "EXIT_CODE=%ERRORLEVEL%"
echo.
echo Server console closed with code %EXIT_CODE%.
pause
exit /b %EXIT_CODE%

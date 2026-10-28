@echo off
setlocal enabledelayedexpansion
title YBT Management Software - Launcher
cd /d "%~dp0"

echo ================================================================
echo       YBT Management Software - Enterprise Edition
echo ================================================================
echo.

:: 1. Check Apache Port 80
echo [1/3] Checking Apache web server (Port 80)...
netstat -ano | findstr :80 | findstr LISTENING >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Apache is not detected on Port 80.
    if exist "C:\xampp\apache_start.bat" (
        echo [*] Attempting to start Apache via XAMPP...
        start "" /b "C:\xampp\apache_start.bat"
        timeout /t 3 /nobreak >nul
    ) else (
        echo [!] Please start Apache from XAMPP Control Panel.
    )
) else (
    echo [OK] Apache is running on Port 80.
)
echo.

:: 2. Check MySQL Port 3307
echo [2/3] Checking MySQL database server (Port 3307)...
netstat -ano | findstr :3307 | findstr LISTENING >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] MySQL is not detected on Port 3307.
    if exist "C:\xampp\mysql_start.bat" (
        echo [*] Attempting to start MySQL via XAMPP...
        start "" /b "C:\xampp\mysql_start.bat"
        timeout /t 3 /nobreak >nul
    ) else (
        echo [!] Please start MySQL from XAMPP Control Panel.
    )
) else (
    echo [OK] MySQL is running on Port 3307.
)
echo.

:: 3. Launch Browser
set "APP_URL=http://localhost/ybt-management-software-main"
echo [3/3] Opening application in default browser...
echo [*] URL: %APP_URL%
start "" "%APP_URL%"
echo.

echo ================================================================
echo                   APPLICATION READY
echo ================================================================
echo  URL:      %APP_URL%
echo  Database: 127.0.0.1:3307 (ybt_management)
echo.
echo  Default Demo Accounts:
echo    - Business Owner:     owner@ybt.com   / password123
echo    - Operations Manager: manager@ybt.com / password123
echo ================================================================
echo.
echo Press any key to close this launcher window...
pause >nul

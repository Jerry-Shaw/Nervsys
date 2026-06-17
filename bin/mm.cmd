@echo off
setlocal enabledelayedexpansion

REM ============================================
REM Nervsys Module Manager (mm)
REM ============================================

REM Check PHP availability
where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] PHP not found in PATH.
    pause
    exit /b 1
)

REM Get script directory and manager.php path
set "SCRIPT_DIR=%~dp0"
set "MANAGER_PHP=%SCRIPT_DIR%..\modules\manager\bin\manager.php"

REM Get current working directory (startup directory)
set "CWD=%cd%"

REM Get command (first argument)
set "COMMAND=%1"

REM Help
if "%COMMAND%"=="" goto :help
if "%COMMAND%"=="help" goto :help
if "%COMMAND%"=="-h" goto :help

REM Pass all arguments and append CWD as last argument
php "%MANAGER_PHP%" %* "%CWD%"
exit /b %errorlevel%

:help
echo.
echo Nervsys Module Manager (mm)
echo.
echo Usage:
echo   mm [target_dir] install {user/repo}[@tag][#https/#git]
echo   mm [target_dir] init {module_name}
echo   mm [target_dir] set-remote {git_source}
echo   mm help
echo.
echo Examples:
echo   mm modules install nervsys/logger
echo   mm modules install nervsys/logger@v1.0.0
echo   mm modules install nervsys/logger@v1.0.0#git
echo   mm custom init my_module
echo   mm any_dir set-remote gitee.com
echo.
exit /b 0
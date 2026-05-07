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
echo   mm install {user/repo}[@tag][@https/@git] [target_dir]
echo   mm init {module_name} [target_dir]
echo   mm setSource {git_source}
echo   mm help
echo.
echo Examples:
echo   mm install nervsys/logger
echo   mm install nervsys/logger@v1.0.0
echo   mm install nervsys/logger@v1.0.0@git
echo   mm init my_module
echo   mm init my_module custom_modules
echo   mm setSource gitee.com
echo.
exit /b 0
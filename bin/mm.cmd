@echo off

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

REM Paths
set "SCRIPT_DIR=%~dp0"
set "MANAGER_PHP=%SCRIPT_DIR%..\modules\manager\bin\manager.php"
set "PROJECT_ROOT=%cd%"

REM Get command
set "COMMAND=%1"

REM Help
if "%COMMAND%"=="" goto :help
if "%COMMAND%"=="help" goto :help
if "%COMMAND%"=="-h" goto :help

REM Pass all arguments and project root to PHP
php "%MANAGER_PHP%" %* "%PROJECT_ROOT%"
exit /b %errorlevel%

:help
echo.
echo Nervsys Module Manager (mm)
echo.
echo Usage:
echo   mm install {user/repo}[#tag] [target_dir] [git_type]
echo   mm setSource {git_source}
echo   mm help
echo.
echo Examples:
echo   mm install nervsys/logger
echo   mm install nervsys/logger#v1.0.0
echo   mm install nervsys/logger my_libs
echo   mm install nervsys/logger my_libs git
echo   mm setSource gitee.com
echo.
exit /b 0
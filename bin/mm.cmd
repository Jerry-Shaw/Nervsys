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

REM Paths
set "SCRIPT_DIR=%~dp0"
set "MANAGER_PHP=%SCRIPT_DIR%..\modules\manager\bin\manager.php"
set "PROJECT_ROOT=%cd%"

REM Get command
set "COMMAND=%1"
set "ARG1=%2"
set "ARG2=%3"

REM Help
if "%COMMAND%"=="" goto :help
if "%COMMAND%"=="help" goto :help
if "%COMMAND%"=="-h" goto :help

REM Install
if "%COMMAND%"=="install" (
    if "%ARG1%"=="" (
        echo [ERROR] Usage: mm install {user/repo}[#tag] [target_dir]
        exit /b 1
    )

    REM Build command with optional parameters
    set "CMD=php "%MANAGER_PHP%" install "%ARG1%""
    if not "%ARG2%"=="" set "CMD=!CMD! "%ARG2%""
    if not "%PROJECT_ROOT%"=="" set "CMD=!CMD! "%PROJECT_ROOT%""

    !CMD!
    exit /b %errorlevel%
)

REM SetSource
if "%COMMAND%"=="setSource" (
    if "%ARG1%"=="" (
        echo [ERROR] Usage: mm setSource {git_source}
        exit /b 1
    )
    php "%MANAGER_PHP%" setsource "%ARG1%"
    exit /b %errorlevel%
)

echo Unknown command: %COMMAND%
goto :help

:help
echo.
echo Nervsys Module Manager (mm)
echo.
echo Usage:
echo   mm install {user/repo}[#tag] [target_dir]
echo   mm setSource {git_source}
echo   mm help
echo.
echo Examples:
echo   mm install nervsys/logger
echo   mm install nervsys/logger#v1.0.0
echo   mm install nervsys/logger my_libs
echo   mm setSource gitee.com
echo.
exit /b 0
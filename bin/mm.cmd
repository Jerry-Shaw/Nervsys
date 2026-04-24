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
set "PHP_SCRIPT=%SCRIPT_DIR%mm.php"
set "PROJECT_ROOT=%cd%"

REM Defaults
set "MODULE_TARGET=modules"
set "COMMAND="
set "USER_REPO="
set "TAG="

REM Parse arguments
set "FIRST=1"
for %%a in (%*) do (
    if "!FIRST!"=="1" (
        set "COMMAND=%%a"
        set "FIRST=0"
    ) else (
        if "!COMMAND!"=="install" (
            if "!USER_REPO!"=="" (
                set "USER_REPO=%%a"
            ) else if "!MODULE_TARGET!"=="modules" (
                set "MODULE_TARGET=%%a"
            )
        ) else if "!COMMAND!"=="setSource" (
            set "GIT_SOURCE=%%a"
        )
    )
)

REM Help
if "%COMMAND%"=="" goto :help
if "%COMMAND%"=="help" goto :help
if "%COMMAND%"=="-h" goto :help

REM Execute command
if "%COMMAND%"=="install" goto :install
if "%COMMAND%"=="setSource" goto :setSource
echo Unknown command: %COMMAND%
goto :help

:install
if "%USER_REPO%"=="" (
    echo [ERROR] Usage: mm install {user/repo}[#tag] [target_dir]
    exit /b 1
)

REM Parse user/repo and tag
set "REPO_NAME=%USER_REPO%"
set "TAG_VALUE="
for /f "tokens=1,2 delims=#" %%a in ("%USER_REPO%") do (
    set "REPO_NAME=%%a"
    if not "%%b"=="" set "TAG_VALUE=%%b"
)

REM Build target path
set "TARGET_ROOT=%PROJECT_ROOT%\%MODULE_TARGET%"

REM Create directory if not exists
if not exist "%TARGET_ROOT%" mkdir "%TARGET_ROOT%" 2>nul

REM Build -d parameter
set "DATA_PARAM=user_repo=%REPO_NAME%"
if not "%TAG_VALUE%"=="" set "DATA_PARAM=%DATA_PARAM%&tag=%TAG_VALUE%"
set "DATA_PARAM=%DATA_PARAM%&root=%TARGET_ROOT%"

REM Execute
php "%PHP_SCRIPT%" -c"/Nervsys/modules/manager/go/install" -d"%DATA_PARAM%"
exit /b %errorlevel%

:setSource
if "%GIT_SOURCE%"=="" (
    echo [ERROR] Usage: mm setSource {git_source}
    exit /b 1
)

php "%PHP_SCRIPT%" -c"/Nervsys/modules/manager/go/setSource" -d"source=%GIT_SOURCE%"
exit /b %errorlevel%

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
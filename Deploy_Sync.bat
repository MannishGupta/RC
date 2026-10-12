:: Version: 1.2
@echo off
setlocal EnableDelayedExpansion

:: ---------------------------------------------------------
:: Configuration
:: ---------------------------------------------------------
set "LOCAL_DIR=C:\Users\gupta\OneDrive - Arthsathi Limited\Documents\GitHub\RC"
set "GIT_REMOTE_URL=https://github.com/MannishGupta/RC.git"

set "FTP1_HOST=103.195.185.254"
set "FTP1_USER=fusioe66"
set "FTP1_PASS=Digi@1970#"
set "FTP1_DIR=/rc"

set "FTP2_HOST=simcoauto.com"
set "FTP2_USER=webmaster@simcoauto.com"
set "FTP2_PASS=Simco@1962"
set "FTP2_DIR=/public_html"

:: ---------------------------------------------------------
:: Initialization & Directory Validation
:: ---------------------------------------------------------
title "Multi-Target Deployment: Git and FTP"
cls
echo ========================================================
echo       Deployment and Multi-Target Synchronization
echo ========================================================
echo Timestamp: %DATE% %TIME%
echo Target Local Directory: %LOCAL_DIR%
echo.

if not exist "%LOCAL_DIR%" (
    echo [ERROR] Target local directory not found: "%LOCAL_DIR%"
    pause
    exit /b 1
)

cd /d "%LOCAL_DIR%"

:: Prevent Git from tracking and modifying the running batch file
findstr /C:"Deploy_Sync.bat" .gitignore >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo Deploy_Sync.bat>> .gitignore
    echo Deploy_Sync.ps1>> .gitignore
    echo Deploy_Sync.exe>> .gitignore
    echo deploy_sync.log>> .gitignore
)

:: Prompt for Git Commit Description
set "COMMIT_MSG="
set /p COMMIT_MSG="Enter Git commit comment (Leave blank for default timestamp): "
if "!COMMIT_MSG!"=="" (
    set "COMMIT_MSG=Automatic deploy sync on %DATE% at %TIME%"
)

echo.
echo ========================================================
echo Stage 1: Synchronizing with GitHub Repository
echo ========================================================
if not exist ".git" (
    echo Initializing Git repository...
    git init
    git branch -M main
    git remote add origin %GIT_REMOTE_URL% 2>nul
)

git remote set-url origin %GIT_REMOTE_URL%
git add -A
git commit -m "!COMMIT_MSG!" 2>nul

echo Pushing committed changes to GitHub...
git push origin main
if %ERRORLEVEL% equ 0 (
    echo [SUCCESS] GitHub sync completed cleanly.
) else (
    echo [WARNING] Standard push rejected due to remote divergence.
    echo Overwriting remote with authoritative local state...
    git push origin main --force
    if !ERRORLEVEL! equ 0 (
        echo [SUCCESS] GitHub sync completed via authoritative force push.
    ) else (
        echo [ERROR] GitHub push failed. Check your network or credentials.
    )
)

echo.
echo ========================================================
echo Stage 2: Windows NT FTP Server Sync (Arthsathi / Digisofts)
echo Host: %FTP1_HOST%
echo Directory: %FTP1_DIR%
echo ========================================================

where winscp.com >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo WinSCP detected. Mirroring directory...
    winscp.com /command ^
        "open ftp://%FTP1_USER%:%FTP1_PASS%@%FTP1_HOST%/" ^
        "synchronize remote -delete \"%LOCAL_DIR%\" \"%FTP1_DIR%\"" ^
        "exit"
    if !ERRORLEVEL! equ 0 (
        echo [SUCCESS] Windows NT FTP sync completed via WinSCP.
    ) else (
        echo [ERROR] WinSCP synchronization failed on Windows NT FTP.
    )
) else (
    echo WinSCP not found. Transferring files using native FTP...
    set "FTP1_SCRIPT=%TEMP%\ftp1_script_%RANDOM%.txt"
    (
        echo open %FTP1_HOST%
        echo %FTP1_USER%
        echo %FTP1_PASS%
        echo binary
        echo prompt off
        echo cd %FTP1_DIR%
        echo mput *.*
        echo quit
    ) > "!FTP1_SCRIPT!"

    ftp -s:"!FTP1_SCRIPT!"
    del "!FTP1_SCRIPT!" >nul 2>&1
    echo [NOTICE] Windows native FTP transfer completed.
)

echo.
echo ========================================================
echo Stage 3: Linux FTP Server Sync (simcoauto.com)
echo Host: %FTP2_HOST%
echo Directory: %FTP2_DIR%
echo ========================================================

where winscp.com >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo WinSCP detected. Mirroring directory...
    winscp.com /command ^
        "open ftp://%FTP2_USER%:%FTP2_PASS%@%FTP2_HOST%/" ^
        "synchronize remote -delete \"%LOCAL_DIR%\" \"%FTP2_DIR%\"" ^
        "exit"
    if !ERRORLEVEL! equ 0 (
        echo [SUCCESS] Linux FTP sync completed via WinSCP.
    ) else (
        echo [ERROR] WinSCP synchronization failed on Linux FTP.
    )
) else (
    echo WinSCP not found. Transferring files using native FTP...
    set "FTP2_SCRIPT=%TEMP%\ftp2_script_%RANDOM%.txt"
    (
        echo open %FTP2_HOST%
        echo %FTP2_USER%
        echo %FTP2_PASS%
        echo binary
        echo prompt off
        echo cd %FTP2_DIR%
        echo mput *.*
        echo quit
    ) > "!FTP2_SCRIPT!"

    ftp -s:"!FTP2_SCRIPT!"
    del "!FTP2_SCRIPT!" >nul 2>&1
    echo [NOTICE] Linux native FTP transfer completed.
)

echo.
echo ========================================================
echo Synchronization Process Terminated
echo ========================================================
pause
exit /b 0
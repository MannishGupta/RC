:: Version: 1.5
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
:: WinSCP Binary Resolution
:: ---------------------------------------------------------
set "WINSCP_BIN="
if exist "%LOCALAPPDATA%\Programs\WinSCP\winscp.com" (
    set "WINSCP_BIN=%LOCALAPPDATA%\Programs\WinSCP\winscp.com"
) else if exist "%ProgramFiles(x86)%\WinSCP\winscp.com" (
    set "WINSCP_BIN=%ProgramFiles(x86)%\WinSCP\winscp.com"
) else if exist "%ProgramFiles%\WinSCP\winscp.com" (
    set "WINSCP_BIN=%ProgramFiles%\WinSCP\winscp.com"
) else (
    where winscp.com >nul 2>&1
    if !ERRORLEVEL! equ 0 set "WINSCP_BIN=winscp.com"
)

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
if defined WINSCP_BIN (
    echo WinSCP Engine: !WINSCP_BIN!
) else (
    echo [ERROR] winscp.com not detected. Aborting.
    pause
    exit /b 1
)
echo.

if not exist "%LOCAL_DIR%" (
    echo [ERROR] Target local directory not found: "%LOCAL_DIR%"
    pause
    exit /b 1
)

cd /d "%LOCAL_DIR%"

:: Exclude deployment scripts and logs from Git
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
        echo [ERROR] GitHub push failed. Check network or credentials.
    )
)

echo.
echo ========================================================
echo Stage 2: Windows NT FTP Server Sync (Arthsathi / Digisofts)
echo Host: %FTP1_HOST%
echo Directory: %FTP1_DIR%
echo ========================================================

"!WINSCP_BIN!" /command ^
    "open ftp://%FTP1_USER%:%FTP1_PASS%@%FTP1_HOST%/" ^
    "synchronize remote -filemask=\"| .git/; Deploy_Sync.*; *.log\" \"%LOCAL_DIR%\" \"%FTP1_DIR%\"" ^
    "exit"

if %ERRORLEVEL% equ 0 (
    echo [SUCCESS] Windows NT FTP sync completed via WinSCP.
) else (
    echo [ERROR] WinSCP transfer failed on Windows NT FTP.
)

echo.
echo ========================================================
echo Stage 3: Linux FTP Server Sync (simcoauto.com)
echo Host: %FTP2_HOST%
echo Directory: %FTP2_DIR%
echo ========================================================

"!WINSCP_BIN!" /command ^
    "open ftp://%FTP2_USER%:%FTP2_PASS%@%FTP2_HOST%/" ^
    "synchronize remote -filemask=\"| .git/; Deploy_Sync.*; *.log\" \"%LOCAL_DIR%\" \"%FTP2_DIR%\"" ^
    "exit"

if %ERRORLEVEL% equ 0 (
    echo [SUCCESS] Linux FTP sync completed via WinSCP.
) else (
    echo [ERROR] WinSCP transfer failed on Linux FTP.
)

echo.
echo ========================================================
echo Synchronization Process Terminated
echo ========================================================
pause
exit /b 0
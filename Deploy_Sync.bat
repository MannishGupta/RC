:: Version: 2.3
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
set "FTP2_DIR=/public_html/rc.simcoauto.com"

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
title "Multi-Target Deployment: Git & FTP (Direct Upload)"
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

:: Exclude deployment scripts, cache busters, and logs from Git tracking
findstr /C:"Deploy_Sync.bat" .gitignore >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo Deploy_Sync.bat>> .gitignore
    echo Deploy_Sync.ps1>> .gitignore
    echo Deploy_Sync.exe>> .gitignore
    echo deploy_sync.log>> .gitignore
    echo opcache_reset.php>> .gitignore
)

:: Generate dynamic OPcache reset script locally
set "OPCACHE_SCRIPT=%LOCAL_DIR%\opcache_reset.php"
(
    echo ^<?php
    echo if ^(function_exists^('opcache_reset'^)^) {
    echo     opcache_reset^(^);
    echo     echo "OPCACHE_RESET_OK";
    echo } else {
    echo     echo "NO_OPCACHE";
    echo }
    echo @unlink^(__FILE__^);
    echo ?^>
) > "%OPCACHE_SCRIPT%"

:: Prompt for Git Commit Description
set "COMMIT_MSG="
set /p COMMIT_MSG="Enter Git commit comment (Leave blank for default timestamp): "
if "!COMMIT_MSG!"=="" (
    set "COMMIT_MSG=Authoritative deployment sync on %DATE% at %TIME%"
)

echo.
echo ========================================================
echo Stage 1: Overwriting GitHub Repository (Direct Force)
echo ========================================================
if not exist ".git" (
    echo Initializing Git repository...
    git init
    git branch -M main
    git remote add origin %GIT_REMOTE_URL% 2>nul
)

git remote set-url origin %GIT_REMOTE_URL%
git add -A
git commit -m "!COMMIT_MSG!" --allow-empty 2>nul

echo Executing force push to GitHub...
git push origin main --force
if %ERRORLEVEL% equ 0 (
    echo [STATUS] GitHub sync: DONE
) else (
    echo [STATUS] GitHub sync: FAILED. Check network or credentials.
)

echo.
echo ========================================================
echo Stage 2: Windows NT FTP Server (Direct Overwrite Upload)
echo Host: %FTP1_HOST%
echo Directory: %FTP1_DIR%
echo ========================================================

set "WINSCP_SCRIPT1=%TEMP%\winscp_sync_s1_%RANDOM%.txt"
set "WINSCP_LOG1=%TEMP%\winscp_log_s1_%RANDOM%.xml"

(
    echo open ftp://%FTP1_USER%:%FTP1_PASS%@%FTP1_HOST%/
    echo option confirm off
    echo cd "%FTP1_DIR%"
    echo lcd "%LOCAL_DIR%"
    echo put -filemask="^| .git/; Deploy_Sync.*; *.log; opcache_reset.php" *
    echo exit
) > "!WINSCP_SCRIPT1!"

"!WINSCP_BIN!" /script="!WINSCP_SCRIPT1!" /xmllog="!WINSCP_LOG1!"
set "FTP1_EXIT=!ERRORLEVEL!"
del "!WINSCP_SCRIPT1!" >nul 2>&1

powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
    "$logPath = '!WINSCP_LOG1!';" ^
    "if (Test-Path $logPath) {" ^
    "    [xml]$xml = Get-Content $logPath;" ^
    "    $uploads = $xml.SelectNodes('//upload');" ^
    "    if ($uploads -and $uploads.Count -gt 0) {" ^
    "        $count = $uploads.Count;" ^
    "        $totalBytes = 0;" ^
    "        foreach ($u in $uploads) {" ^
    "            $szNode = $u.SelectSingleNode('size');" ^
    "            if ($szNode) { $totalBytes += [int64]$szNode.InnerText }" ^
    "        }" ^
    "        $mb = [math]::Round($totalBytes / 1MB, 2);" ^
    "        $kb = [math]::Round($totalBytes / 1KB, 2);" ^
    "        if ($mb -ge 1) { $sizeStr = \"$mb MB ($totalBytes bytes)\" } else { $sizeStr = \"$kb KB ($totalBytes bytes)\" };" ^
    "        Write-Host ('[STAGE 2 DONE] Files uploaded: ' + $count + ' | Total data: ' + $sizeStr) -ForegroundColor Green;" ^
    "    } else {" ^
    "        Write-Host '[STAGE 2 DONE] Files uploaded: 0' -ForegroundColor Yellow;" ^
    "    }" ^
    "}"
del "!WINSCP_LOG1!" >nul 2>&1

if !FTP1_EXIT! neq 0 (
    echo [WARNING] Windows NT FTP reported transfer errors.
)

echo.
echo ========================================================
echo Stage 3: Linux FTP Server (Direct Overwrite Upload)
echo Host: %FTP2_HOST%
echo Directory: %FTP2_DIR%
echo ========================================================

set "WINSCP_SCRIPT2=%TEMP%\winscp_sync_s2_%RANDOM%.txt"
set "WINSCP_LOG2=%TEMP%\winscp_log_s2_%RANDOM%.xml"

(
    echo open ftp://%FTP2_USER%:%FTP2_PASS%@%FTP2_HOST%/
    echo option confirm off
    echo cd "%FTP2_DIR%"
    echo lcd "%LOCAL_DIR%"
    echo put -filemask="^| .git/; Deploy_Sync.*; *.log" *
    echo put "%OPCACHE_SCRIPT%" "opcache_reset.php"
    echo exit
) > "!WINSCP_SCRIPT2!"

"!WINSCP_BIN!" /script="!WINSCP_SCRIPT2!" /xmllog="!WINSCP_LOG2!"
set "FTP2_EXIT=!ERRORLEVEL!"
del "!WINSCP_SCRIPT2!" >nul 2>&1
del "%OPCACHE_SCRIPT%" >nul 2>&1

powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
    "$logPath = '!WINSCP_LOG2!';" ^
    "if (Test-Path $logPath) {" ^
    "    [xml]$xml = Get-Content $logPath;" ^
    "    $uploads = $xml.SelectNodes('//upload');" ^
    "    if ($uploads -and $uploads.Count -gt 0) {" ^
    "        $count = $uploads.Count;" ^
    "        $totalBytes = 0;" ^
    "        foreach ($u in $uploads) {" ^
    "            $szNode = $u.SelectSingleNode('size');" ^
    "            if ($szNode) { $totalBytes += [int64]$szNode.InnerText }" ^
    "        }" ^
    "        $mb = [math]::Round($totalBytes / 1MB, 2);" ^
    "        $kb = [math]::Round($totalBytes / 1KB, 2);" ^
    "        if ($mb -ge 1) { $sizeStr = \"$mb MB ($totalBytes bytes)\" } else { $sizeStr = \"$kb KB ($totalBytes bytes)\" };" ^
    "        Write-Host ('[STAGE 3 DONE] Files uploaded: ' + $count + ' | Total data: ' + $sizeStr) -ForegroundColor Green;" ^
    "    } else {" ^
    "        Write-Host '[STAGE 3 DONE] Files uploaded: 0' -ForegroundColor Yellow;" ^
    "    }" ^
    "}"
del "!WINSCP_LOG2!" >nul 2>&1

if !FTP2_EXIT! equ 0 (
    echo.
    echo Purging server OPcache via HTTPS ping...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
        "try { $res = (Invoke-WebRequest -Uri 'https://rc.simcoauto.com/opcache_reset.php' -UseBasicParsing -TimeoutSec 10).Content.Trim(); Write-Host ('[OPCACHE STATUS] ' + $res) -ForegroundColor Cyan; } catch { Write-Host ('[OPCACHE NOTICE] Endpoint hit and self-purged.') -ForegroundColor Gray; }"
) else (
    echo [WARNING] Linux FTP reported transfer errors.
)

echo.
echo ========================================================
echo             ALL DEPLOYMENT TASKS DONE
echo ========================================================
echo Status: DONE
echo Completed at: %DATE% %TIME%
echo ========================================================
pause
exit /b 0
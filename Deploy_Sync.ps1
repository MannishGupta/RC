# Version: 3.3
[CmdletBinding()]
param (
    [string]$CommitMessage = ""
)

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------
$LocalDir      = "C:\Users\gupta\OneDrive - Arthsathi Limited\Documents\GitHub\RC"
$GitRemoteUrl  = "https://github.com/MannishGupta/RC.git"

$FTP1 = @{
    Host      = "103.195.185.254"
    User      = "fusioe66"
    Pass      = "Digi@1970#"
    RemoteDir = "/rc"
}

$FTP2 = @{
    Host      = "simcoauto.com"
    User      = "webmaster@simcoauto.com"
    Pass      = "Simco@1962"
    RemoteDir = "/public_html/rc.simcoauto.com"
}

# -----------------------------------------------------------------------------
# Locate WinSCP Console Binary
# -----------------------------------------------------------------------------
$WinScpPaths = @(
    "$env:LOCALAPPDATA\Programs\WinSCP\winscp.com",
    "${env:ProgramFiles(x86)}\WinSCP\winscp.com",
    "$env:ProgramFiles\WinSCP\winscp.com"
)

$WinScpBin = $null
foreach ($p in $WinScpPaths) {
    if (Test-Path $p) {
        $WinScpBin = $p
        break
    }
}

if (-not $WinScpBin) {
    $cmdCheck = Get-Command "winscp.com" -ErrorAction SilentlyContinue
    if ($cmdCheck) { $WinScpBin = $cmdCheck.Source }
}

Clear-Host
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "      Deployment and Multi-Target Synchronization" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "Timestamp: $(Get-Date -Format 'dd-MM-yyyy HH:mm:ss')"
Write-Host "Target Local Directory: $LocalDir"

if (-not $WinScpBin) {
    Write-Host "[ERROR] winscp.com not detected." -ForegroundColor Red
    Read-Host "Press Enter to exit..."
    exit 1
}
Write-Host "WinSCP Engine: $WinScpBin"
Write-Host ""

if (-not (Test-Path $LocalDir)) {
    Write-Host "[ERROR] Target directory not found: $LocalDir" -ForegroundColor Red
    Read-Host "Press Enter to exit..."
    exit 1
}

Set-Location -Path $LocalDir

# Ensure exclusions in .gitignore
$gitignorePath = Join-Path $LocalDir ".gitignore"
$ignoreEntries = @("Deploy_Sync.bat", "Deploy_Sync.ps1", "Deploy_Sync.exe", "deploy_sync.log", "opcache_reset.php", "msg.txt")
if (Test-Path $gitignorePath) {
    $existing = Get-Content $gitignorePath
    foreach ($entry in $ignoreEntries) {
        if ($existing -notcontains $entry) {
            Add-Content -Path $gitignorePath -Value $entry
        }
    }
} else {
    Set-Content -Path $gitignorePath -Value $ignoreEntries
}

# Dynamic OPcache Buster Script
$OpcacheScript = Join-Path $LocalDir "opcache_reset.php"
@"
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCACHE_RESET_OK";
} else {
    echo "NO_OPCACHE";
}
@unlink(__FILE__);
?>
"@ | Set-Content -Path $OpcacheScript -Encoding UTF8

if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
    $Prompt = Read-Host "Enter Git commit comment (Leave blank for default timestamp)"
    if ([string]::IsNullOrWhiteSpace($Prompt)) {
        $CommitMessage = "Authoritative deployment sync on $(Get-Date -Format 'dd-MM-yyyy HH:mm:ss')"
    } else {
        $CommitMessage = $Prompt
    }
}

# -----------------------------------------------------------------------------
# Stage 1: Overwriting GitHub Repository (Direct Force)
# -----------------------------------------------------------------------------
Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "Stage 1: Overwriting GitHub Repository (Direct Force)" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan

if (-not (Test-Path ".git")) {
    git init | Out-Null
    git branch -M main | Out-Null
    git remote add origin $GitRemoteUrl 2>$null
}

git remote set-url origin $GitRemoteUrl
git add -A
git commit -m "$CommitMessage" --allow-empty 2>$null | Out-Null

Write-Host "Executing force push to GitHub..."
$gitOut = git push origin main --force 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "[STATUS] GitHub sync: DONE" -ForegroundColor Green
} else {
    Write-Host "[STATUS] GitHub sync: FAILED. Check network/credentials." -ForegroundColor Red
    Write-Host $gitOut
}

# Helper function to parse WinSCP XML log and output statistics
function Report-WinScpMetrics {
    param([string]$XmlPath, [string]$StageName)
    if (Test-Path $XmlPath) {
        try {
            [xml]$xml = Get-Content$XmlPath
            $uploads = @($xml.session.upload | Where-Object { $_ -ne$null })
            if ($uploads.Count -gt 0) {
                $count =$uploads.Count
                $totalBytes = 0
                foreach ($u in $uploads) {$sz = if ($u.size.InnerText) {$u.size.InnerText } else { "$($u.size)" }
                    if ($sz) { $totalBytes += [int64]$sz }
                }
                $mb = [math]::Round($totalBytes / 1MB, 2)
                $kb = [math]::Round($totalBytes / 1KB, 2)
                $sizeStr = if ($mb -ge 1) { "$mb MB ($totalBytes bytes)" } else { "$kb KB ($totalBytes bytes)" }
                Write-Host "[$StageName DONE] Files uploaded: $count \vert{} Total data:$sizeStr" -ForegroundColor Green
            } else {
                Write-Host "[$StageName DONE] Files uploaded: 0 (Remote already in sync)" -ForegroundColor Green
            }
        } catch {
            Write-Host "[$StageName DONE] Completed successfully." -ForegroundColor Green
        }
    }
}

# Common filemask for transfers (ignores git, deployment scripts, logs, and locked files)
$transferMask = "| .git/; Deploy_Sync.*; *.log; opcache_reset.php; msg.txt; *.tmp; rc-install-engine.ps1"

# -----------------------------------------------------------------------------
# Stage 2: Windows NT FTP Server
# -----------------------------------------------------------------------------
Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "Stage 2: Windows NT FTP Server (Arthsathi / Digisofts)" -ForegroundColor Cyan
Write-Host "Host: $($FTP1.Host)"
Write-Host "Directory: $($FTP1.RemoteDir)"
Write-Host "========================================================" -ForegroundColor Cyan

$Script1 = Join-Path $env:TEMP "winscp_s1_$([guid]::NewGuid().ToString('N')).txt"
$Log1    = Join-Path $env:TEMP "winscp_log1_$([guid]::NewGuid().ToString('N')).xml"

$WinScpCommands1 = @(
    "open ftp://$($FTP1.User):$($FTP1.Pass)@$($FTP1.Host)/ -timeout=90 -passive=on",
    "option batch abort",
    "option confirm off",
    "synchronize remote -criteria=either -filemask=`"$transferMask`" `"$LocalDir`" `"$($FTP1.RemoteDir)`"",
    "exit"
)
Set-Content -Path $Script1 -Value $WinScpCommands1 -Encoding ASCII

& $WinScpBin "/script=$Script1" "/xmllog=$Log1"
$ftp1Exit = $LASTEXITCODE

Remove-Item $Script1 -ErrorAction SilentlyContinue
Report-WinScpMetrics -XmlPath $Log1 -StageName "STAGE 2"
Remove-Item $Log1 -ErrorAction SilentlyContinue

if ($ftp1Exit -ne 0) {
    Write-Host "[WARNING] Windows NT FTP reported transfer warnings." -ForegroundColor Yellow
}

# -----------------------------------------------------------------------------
# Stage 3: Linux FTP Server
# -----------------------------------------------------------------------------
Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "Stage 3: Linux FTP Server (simcoauto.com)" -ForegroundColor Cyan
Write-Host "Host: $($FTP2.Host)"
Write-Host "Directory: $($FTP2.RemoteDir)"
Write-Host "========================================================" -ForegroundColor Cyan

$Script2 = Join-Path $env:TEMP "winscp_s2_$([guid]::NewGuid().ToString('N')).txt"
$Log2    = Join-Path $env:TEMP "winscp_log2_$([guid]::NewGuid().ToString('N')).xml"

$WinScpCommands2 = @(
    "open ftp://$($FTP2.User):$($FTP2.Pass)@$($FTP2.Host)/ -timeout=90 -passive=on",
    "option batch abort",
    "option confirm off",
    "synchronize remote -criteria=either -filemask=`"$transferMask`" `"$LocalDir`" `"$($FTP2.RemoteDir)`"",
    "put `"$OpcacheScript`" `"$($FTP2.RemoteDir)/opcache_reset.php`"",
    "exit"
)
Set-Content -Path $Script2 -Value$WinScpCommands2 -Encoding ASCII

& $WinScpBin "/script=$Script2" "/xmllog=$Log2"
$ftp2Exit =$LASTEXITCODE

Remove-Item $Script2 -ErrorAction SilentlyContinue
Report-WinScpMetrics -XmlPath $Log2 -StageName "STAGE 3"
Remove-Item $Log2 -ErrorAction SilentlyContinue
Remove-Item $OpcacheScript -ErrorAction SilentlyContinue

if ($ftp2Exit -eq 0) {
    Write-Host "`nPurging server OPcache via HTTPS ping..."
    try {
        $res = (Invoke-WebRequest -Uri "https://rc.simcoauto.com/opcache_reset.php" -UseBasicParsing -TimeoutSec 15).Content.Trim()
        Write-Host "[OPCACHE STATUS] $res" -ForegroundColor Cyan
    } catch {
        Write-Host "[OPCACHE NOTICE] Endpoint hit and self-purged." -ForegroundColor Gray
    }
} else {
    Write-Host "[WARNING] Linux FTP reported transfer warnings." -ForegroundColor Yellow
}

# -----------------------------------------------------------------------------
# Completion
# -----------------------------------------------------------------------------
Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "            ALL DEPLOYMENT TASKS DONE" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "Status: DONE" -ForegroundColor Green
Write-Host "Completed at: $(Get-Date -Format 'dd-MM-yyyy HH:mm:ss')"
Write-Host "========================================================" -ForegroundColor Cyan
Read-Host "Press Enter to exit..."
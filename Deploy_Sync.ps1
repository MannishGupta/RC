# Version: 1.0
<#
.SYNOPSIS
    Multi-Target Deployment Engine: GitHub & Dual FTP Servers (Windows NT & Linux).
.DESCRIPTION
    Automates local git commit, upstream pull/rebase sync, and push to GitHub,
    followed by recursive .NET-based FTP synchronization across both endpoints.
#>

[CmdletBinding()]
param (
    [string]$CommitMessage = ""
)

# -----------------------------------------------------------------------------
# Configuration Settings
# -----------------------------------------------------------------------------
$Config = @{
    LocalDirectory = "C:\Users\gupta\OneDrive - Arthsathi Limited\Documents\GitHub\RC"
    GitRemoteUrl   = "https://github.com/MannishGupta/RC.git"
    
    # Windows NT Host (Arthsathi / Digisofts / Fusion)
    FTP1 = @{
        Host     = "103.195.185.254"
        Username = "fusioe66"
        Password = "Digi@1970#"
        RemoteDir = "/rc"
    }

    # Linux Host (simcoauto.com)
    FTP2 = @{
        Host     = "simcoauto.com"
        Username = "webmaster@simcoauto.com"
        Password = "Simco@1962"
        RemoteDir = "/public_html"
    }
}

# -----------------------------------------------------------------------------
# Core Helper Functions
# -----------------------------------------------------------------------------
function Write-Header {
    param ([string]$Title)
    Write-Host "`n========================================================" -ForegroundColor Cyan
    Write-Host " $Title" -ForegroundColor Cyan
    Write-Host "========================================================" -ForegroundColor Cyan
}

function Write-Status {
    param ([string]$Message, [string]$Type = "INFO")
    switch ($Type) {
        "SUCCESS" { Write-Host "[SUCCESS] $Message" -ForegroundColor Green }
        "WARNING" { Write-Host "[WARNING] $Message" -ForegroundColor Yellow }
        "ERROR"   { Write-Host "[ERROR] $Message" -ForegroundColor Red }
        default   { Write-Host "[INFO] $Message" -ForegroundColor Gray }
    }
}

function Invoke-FtpDirectoryUpload {
    param (
        [string]$ServerHost,
        [string]$Username,
        [string]$Password,
        [string]$LocalPath,
        [string]$RemoteBaseDir
    )

    $Credentials = New-Object System.Net.NetworkCredential($Username,$Password)

    # Normalize remote base directory path
    $RemoteBaseDir = "/" + ($RemoteBaseDir.Trim("/") -replace "\\", "/")
    if ($RemoteBaseDir -eq "/") { $RemoteBaseDir = "" }

    # Retrieve all files recursively, skipping internal .git repository artifacts
    $AllFiles = Get-ChildItem -Path$LocalPath -Recurse -File | Where-Object {
        $_.FullName -notmatch '\\\.git($|\\)'
    }

    $TotalFiles =$AllFiles.Count
    $CurrentIndex = 0

    Write-Status "Discovered $TotalFiles file(s) for synchronization."

    foreach ($File in$AllFiles) {
        $CurrentIndex++$RelativePath = $File.FullName.Substring($LocalPath.Length).Replace("\", "/")
        $RemoteFilePath = "ftp://${ServerHost}${RemoteBaseDir}${RelativePath}"

        # Ensure parent remote directories exist
        $RemoteSubDir = [System.IO.Path]::GetDirectoryName($RelativePath).Replace("\", "/")
        if ($RemoteSubDir -and$RemoteSubDir -ne "/") {
            $PathParts =$RemoteSubDir.Split("/", [System.StringSplitOptions]::RemoveEmptyEntries)
            $BuiltPath = ""
            foreach ($Part in$PathParts) {
                $BuiltPath += "/" + $Part
                $CheckUrl = "ftp://${ServerHost}${RemoteBaseDir}${BuiltPath}"
                try {
                    $MakeDirReq = [System.Net.FtpWebRequest]::Create($CheckUrl)
                    $MakeDirReq.Credentials =$Credentials
                    $MakeDirReq.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory$MakeDirReq.UseBinary = $true$MakeDirReq.KeepAlive = $false$MakeDirReq.UsePassive = $true$NullResponse = $MakeDirReq.GetResponse()$NullResponse.Close()
                } catch {
                    # Directory already exists or permission preserved; continue
                }
            }
        }

        # Perform Binary File Upload
        try {
            Write-Progress -Activity "Uploading files to $ServerHost" `
                           -Status "($CurrentIndex/$TotalFiles) $RelativePath" `
                           -PercentComplete (($CurrentIndex / $TotalFiles) * 100)

            $UploadRequest = [System.Net.FtpWebRequest]::Create($RemoteFilePath)$UploadRequest.Credentials = $Credentials$UploadRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
            $UploadRequest.UseBinary =$true
            $UploadRequest.KeepAlive =$false
            $UploadRequest.UsePassive =$true

            $FileBytes = [System.IO.File]::ReadAllBytes($File.FullName)
            $UploadRequest.ContentLength =$FileBytes.Length

            $ReqStream = $UploadRequest.GetRequestStream()$ReqStream.Write($FileBytes, 0,$FileBytes.Length)
            $ReqStream.Flush()$ReqStream.Close()

            $Response = $UploadRequest.GetResponse()$Response.Close()
        } catch {
            Write-Status "Failed to upload $RelativePath. Reason: $($_.Exception.Message)" "ERROR"
        }
    }

    Write-Progress -Activity "Uploading files to $ServerHost" -Completed
    Write-Status "Transfer cycle completed for $ServerHost." "SUCCESS"
}

# -----------------------------------------------------------------------------
# Main Execution Pipeline
# -----------------------------------------------------------------------------
Clear-Host
Write-Header "Deployment & Multi-Target Synchronization Engine"
Write-Status "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Status "Target Directory: $($Config.LocalDirectory)"

# 1. Directory Validation
if (-not (Test-Path -Path $Config.LocalDirectory)) {
    Write-Status "Directory does not exist: $($Config.LocalDirectory)" "ERROR"
    Read-Host "Press Enter to exit..."
    exit 1
}

Set-Location -Path $Config.LocalDirectory

# 2. Commit Message Resolution
if ([string]::IsNullOrWhiteSpace($CommitMessage)) {$PromptMsg = Read-Host "Enter Git commit message (leave blank for timestamped auto-commit)"
    if ([string]::IsNullOrWhiteSpace($PromptMsg)) {$CommitMessage = "Deployment sync: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    } else {
        $CommitMessage =$PromptMsg
    }
}

# -----------------------------------------------------------------------------
# Stage 1: GitHub Synchronization
# -----------------------------------------------------------------------------
Write-Header "Stage 1: Synchronizing with GitHub"

if (-not (Test-Path -Path ".git")) {
    Write-Status "Initializing new local Git repository..."
    git init | Out-Null
    git branch -M main | Out-Null
    git remote add origin $Config.GitRemoteUrl 2>$null
}

git remote set-url origin $Config.GitRemoteUrl
git add -A

$GitStatus = git status --porcelain
if ($GitStatus) {
    git commit -m "$CommitMessage"
    Write-Status "Local changes staged and committed." "SUCCESS"
} else {
    Write-Status "No modifications detected. Repository working tree clean."
}

# Rebase / Pull remote changes to avoid rejected non-fast-forward push
Write-Status "Pulling remote upstream changes to ensure parity..."
$PullOutput = git pull origin main --rebase --allow-unrelated-histories 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Status "Rebase encounter: trying standard merge pull..." "WARNING"
    git rebase --abort 2>$null
    git pull origin main --no-rebase --allow-unrelated-histories 2>&1 | Out-Null
}

Write-Status "Pushing branch upstream to GitHub (main)..."
$PushOutput = git push origin main 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Status "GitHub synchronization confirmed." "SUCCESS"
} else {
    Write-Status "Push rejected on standard main. Applying lease-protected push..." "WARNING"
    $ForcePush = git push origin main --force-with-lease 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Status "GitHub synchronized successfully via lease update." "SUCCESS"
    } else {
        Write-Status "GitHub push failed: $ForcePush" "ERROR"
    }
}

# -----------------------------------------------------------------------------
# Stage 2: Windows NT FTP Server Sync
# -----------------------------------------------------------------------------
Write-Header "Stage 2: Windows NT FTP Sync (103.195.185.254)"
Invoke-FtpDirectoryUpload -ServerHost $Config.FTP1.Host `
                         -Username $Config.FTP1.Username `
                         -Password $Config.FTP1.Password `
                         -LocalPath $Config.LocalDirectory `
                         -RemoteBaseDir $Config.FTP1.RemoteDir

# -----------------------------------------------------------------------------
# Stage 3: Linux FTP Server Sync
# -----------------------------------------------------------------------------
Write-Header "Stage 3: Linux FTP Sync (simcoauto.com)"
Invoke-FtpDirectoryUpload -ServerHost $Config.FTP2.Host `
                         -Username $Config.FTP2.Username `
                         -Password $Config.FTP2.Password `
                         -LocalPath $Config.LocalDirectory `
                         -RemoteBaseDir $Config.FTP2.RemoteDir

# -----------------------------------------------------------------------------
# Completion
# -----------------------------------------------------------------------------
Write-Header "Synchronization Pipeline Finished"
Write-Status "All operations finished successfully." "SUCCESS"
Read-Host "Press Enter to close..."
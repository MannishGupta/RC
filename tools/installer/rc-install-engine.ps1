#Requires -Version 3.0
<#  Resource Centre — web-app installer. Creates a browser shortcut + ARP entry.
    Metadata header is generated per tenant by RC; everything below it is static. #>
[CmdletBinding()]
param([switch]$Uninstall, [switch]$PerMachine, [switch]$Wow6432, [switch]$Quiet)
# ===== METADATA (generated per tenant by RC — do not hand-edit) =====
$Meta = @{
  AppId         = '{{APP_ID}}'
  DisplayName   = '{{DISPLAY_NAME}}'
  Publisher     = '{{PUBLISHER}}'
  Version       = '{{VERSION}}'
  Url           = '{{URL}}'
  IconUrl       = '{{ICON_URL}}'
  URLInfoAbout  = '{{URL_ABOUT}}'
  URLUpdateInfo = '{{URL_UPDATE}}'
  HelpLink      = '{{HELP_LINK}}'
  HelpTelephone = '{{HELP_PHONE}}'
  Contact       = '{{CONTACT}}'
  Comments      = '{{COMMENTS}}'
}
# ===================================================================
$ErrorActionPreference = 'Stop'
$script:IsAdmin = ([Security.Principal.WindowsPrincipal]`
  [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(`
  [Security.Principal.WindowsBuiltInRole]::Administrator)
$perMachine = $PerMachine.IsPresent -and $script:IsAdmin
if ($PerMachine.IsPresent -and -not $script:IsAdmin) {
  Write-Warning 'Per-machine requested without elevation; falling back to per-user.'
}
$installRoot = if ($perMachine) { Join-Path $env:ProgramFiles 'ResourceCentre' }
               else { Join-Path $env:LOCALAPPDATA 'Programs\ResourceCentre' }
$installDir  = Join-Path $installRoot $Meta.AppId
$selfCopy    = Join-Path $installDir 'rc-setup.ps1'
$iconPath    = Join-Path $installDir 'app.ico'
$shortcutName= "$($Meta.DisplayName).url"
function Get-UninstallBase {
  if ($perMachine) {
    $view = if ($Wow6432) { [Microsoft.Win32.RegistryView]::Registry32 }
            else { [Microsoft.Win32.RegistryView]::Registry64 }
    [Microsoft.Win32.RegistryKey]::OpenBaseKey('LocalMachine', $view)
  } else {
    [Microsoft.Win32.RegistryKey]::OpenBaseKey('CurrentUser',
      [Microsoft.Win32.RegistryView]::Default)
  }
}
$uninstallSub = "Software\Microsoft\Windows\CurrentVersion\Uninstall\$($Meta.AppId)"
function Remove-All {
  foreach ($d in @([Environment]::GetFolderPath('Desktop'),
                   [Environment]::GetFolderPath('Programs'))) {
    $p = Join-Path $d $shortcutName
    if (Test-Path $p) { Remove-Item $p -Force -ErrorAction SilentlyContinue }
  }
  try { $b = Get-UninstallBase; $b.DeleteSubKeyTree($uninstallSub, $false) } catch {}
  if (Test-Path $installDir) {
    Remove-Item $installDir -Recurse -Force -ErrorAction SilentlyContinue
  }
}
if ($Uninstall) {
  Remove-All
  if (-not $Quiet) { Write-Host "$($Meta.DisplayName) uninstalled." }
  exit 0
}
Remove-All
New-Item -ItemType Directory -Path $installDir -Force | Out-Null
Copy-Item -LiteralPath $PSCommandPath -Destination $selfCopy -Force
$iconPath = Join-Path $installDir 'app.ico'
try {
  Invoke-WebRequest -Uri $Meta.IconUrl -OutFile $iconPath -UseBasicParsing -TimeoutSec 20
  if (-not (Test-Path $iconPath) -or ((Get-Item $iconPath).Length -lt 32)) { throw 'icon too small' }
} catch {
  try {
    Invoke-WebRequest -Uri 'https://www.arthsathi.com/favicon.ico' -OutFile $iconPath -UseBasicParsing -TimeoutSec 15
  } catch {
    try {
      Invoke-WebRequest -Uri 'https://rc.arthsathi.com/favicon.ico' -OutFile $iconPath -UseBasicParsing -TimeoutSec 15
    } catch { $iconPath = '' }
  }
}
$urlBody = "[InternetShortcut]`r`nURL=$($Meta.Url)`r`nIconIndex=0`r`n"
if ($iconPath) { $urlBody += "IconFile=$iconPath`r`n" }
foreach ($d in @([Environment]::GetFolderPath('Desktop'),
                 [Environment]::GetFolderPath('Programs'))) {
  Set-Content -LiteralPath (Join-Path $d $shortcutName) -Value $urlBody -Encoding ASCII
}
$sizeKb = [int]((Get-ChildItem $installDir -Recurse -File |
  Measure-Object Length -Sum).Sum / 1024)
$ps = (Get-Command powershell).Source
$uninStr  = "`"$ps`" -NoProfile -ExecutionPolicy Bypass -File `"$selfCopy`" -Uninstall"
$quietUn  = "$uninStr -Quiet"
$base = Get-UninstallBase
$k = $base.CreateSubKey($uninstallSub)
$S = [Microsoft.Win32.RegistryValueKind]::String
$D = [Microsoft.Win32.RegistryValueKind]::DWord
$k.SetValue('DisplayName',       $Meta.DisplayName, $S)
if ($iconPath) { $k.SetValue('DisplayIcon', "$iconPath,0", $S) }
$k.SetValue('DisplayVersion',    $Meta.Version, $S)
$k.SetValue('Publisher',         $Meta.Publisher, $S)
$k.SetValue('URLInfoAbout',      $Meta.URLInfoAbout, $S)
$k.SetValue('URLUpdateInfo',     $Meta.URLUpdateInfo, $S)
$k.SetValue('HelpLink',          $Meta.HelpLink, $S)
$k.SetValue('HelpTelephone',     $Meta.HelpTelephone, $S)
$k.SetValue('Contact',           $Meta.Contact, $S)
$k.SetValue('Comments',          $Meta.Comments, $S)
$k.SetValue('InstallLocation',   $installDir, $S)
$k.SetValue('InstallDate',       (Get-Date).ToString('yyyyMMdd'), $S)
$k.SetValue('EstimatedSize',     $sizeKb, $D)
$k.SetValue('UninstallString',   $uninStr, $S)
$k.SetValue('QuietUninstallString', $quietUn, $S)
$k.SetValue('NoModify',          1, $D)
$k.SetValue('NoRepair',          1, $D)
$k.Close()
if (-not $Quiet) { Write-Host "$($Meta.DisplayName) installed. Shortcut + Installed-Apps entry created." }
exit 0

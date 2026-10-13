:: Version: 3.4
@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Unblock-File -Path '%~dp0Deploy_Sync.ps1' -ErrorAction SilentlyContinue; & '%~dp0Deploy_Sync.ps1'"
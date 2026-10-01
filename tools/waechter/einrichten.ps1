<#
.SYNOPSIS
    Richtet den Intranet-Wächter auf diesem Rechner ein (als Administrator ausführen).

.DESCRIPTION
    1. Fragt das SMTP-Kennwort ab (falls ein Benutzer eingetragen ist) und legt es geschützt
       (DPAPI, an diesen Rechner gebunden) in der Konfiguration ab.
    2. Schickt eine Testmail.
    3. Legt die geplante Aufgabe "Intranet-Waechter" an: alle 5 Minuten als SYSTEM.
#>
param(
    [Parameter(Mandatory = $true)][string]$Konfig,
    [int]$Minuten = 5
)

$ErrorActionPreference = 'Stop'
$Konfig = (Resolve-Path $Konfig).Path
$skript = Join-Path $PSScriptRoot 'intranet-waechter.ps1'
$cfg = Get-Content -Raw -Encoding UTF8 -Path $Konfig | ConvertFrom-Json

if ($cfg.smtp.benutzer) {
    $sicher = Read-Host -AsSecureString ('SMTP-Kennwort für ' + $cfg.smtp.benutzer)
    $klar = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sicher))
    Add-Type -AssemblyName System.Security
    $geschuetzt = [System.Security.Cryptography.ProtectedData]::Protect(
        [System.Text.Encoding]::UTF8.GetBytes($klar), $null,
        [System.Security.Cryptography.DataProtectionScope]::LocalMachine)
    $cfg.smtp | Add-Member -Force -NotePropertyName passwort_geschuetzt -NotePropertyValue ([Convert]::ToBase64String($geschuetzt))
    $cfg | ConvertTo-Json -Depth 5 | Set-Content -Encoding UTF8 -Path $Konfig
    Write-Output 'Kennwort geschützt abgelegt.'
}

& $skript -Konfig $Konfig -Testmail

$aktion = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $skript + '" -Konfig "' + $Konfig + '"')
$ausloeser = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes $Minuten) -RepetitionDuration (New-TimeSpan -Days 3650)
$konto = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$einst = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 4) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'Intranet-Waechter' -Action $aktion -Trigger $ausloeser -Principal $konto -Settings $einst -Force | Out-Null
Write-Output ('Geplante Aufgabe "Intranet-Waechter" angelegt: alle ' + $Minuten + ' Minuten. Protokoll: ' + (Join-Path (Split-Path -Parent $Konfig) 'waechter.log'))

<#
.SYNOPSIS
    Externer Wächter für das Intranet: fragt das Lebenszeichen ab und schlägt per Mail Alarm.

.DESCRIPTION
    Läuft als geplante Windows-Aufgabe (alle 5 Minuten, siehe einrichten.ps1) auf einem Rechner
    im selben Netz wie der Intranet-Server. Fragt GET .../webhooks/ekkon/{schluessel}/lebenszeichen ab.
    Störung = keine/fehlerhafte Antwort oder "ok": false (Scheduler seit über 10 Minuten still).
    Die Mail geht direkt per SMTP raus - am Intranet vorbei, das ja gerade gestört sein kann.

    Gemeldet wird nur bei Zustandswechsel: einmal "gestört" (nach N Fehlversuchen in Folge),
    während der Störung eine Erinnerung je X Minuten, einmal "wieder in Ordnung".
    Zustand in waechter-zustand.json, Protokoll in waechter.log (neben der Konfiguration).
#>
param(
    [Parameter(Mandatory = $true)][string]$Konfig,
    [switch]$Testmail
)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$cfg = Get-Content -Raw -Encoding UTF8 -Path $Konfig | ConvertFrom-Json
$ordner = Split-Path -Parent (Resolve-Path $Konfig)
$zustandDatei = Join-Path $ordner 'waechter-zustand.json'
$logDatei = Join-Path $ordner 'waechter.log'

function Schreibe-Log([string]$text) {
    if ((Test-Path $logDatei) -and ((Get-Item $logDatei).Length -gt 1MB)) {
        Move-Item -Force $logDatei ($logDatei + '.alt')
    }
    Add-Content -Encoding UTF8 -Path $logDatei -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $text)
}

function Sende-Mail([string]$betreff, [string]$inhalt) {
    $smtp = $cfg.smtp
    $param = @{
        SmtpServer = $smtp.server
        Port       = [int]$smtp.port
        From       = $smtp.absender
        To         = @($smtp.empfaenger)
        Subject    = $betreff
        Body       = $inhalt
        Encoding   = [System.Text.Encoding]::UTF8
    }
    if ($smtp.ssl -eq $true) { $param.UseSsl = $true }
    if ($smtp.benutzer) {
        Add-Type -AssemblyName System.Security
        $roh = [System.Security.Cryptography.ProtectedData]::Unprotect(
            [Convert]::FromBase64String([string]$smtp.passwort_geschuetzt), $null,
            [System.Security.Cryptography.DataProtectionScope]::LocalMachine)
        $kennwort = ConvertTo-SecureString ([System.Text.Encoding]::UTF8.GetString($roh)) -AsPlainText -Force
        $param.Credential = New-Object System.Management.Automation.PSCredential ([string]$smtp.benutzer, $kennwort)
    }
    Send-MailMessage @param
}

if ($Testmail) {
    Sende-Mail ('[Wächter ' + $cfg.name + '] Testmail') ('Der Wächter auf ' + $env:COMPUTERNAME + ' kann Mails verschicken.')
    Schreibe-Log 'Testmail verschickt.'
    Write-Output 'Testmail verschickt.'
    exit 0
}

# ── Zustand laden ────────────────────────────────────────────────────────
$zustand = [pscustomobject]@{ stoerung = $false; seit = ''; fehlversuche = 0; letzte_mail = ''; grund = '' }
if (Test-Path $zustandDatei) {
    $zustand = Get-Content -Raw -Encoding UTF8 -Path $zustandDatei | ConvertFrom-Json
}

# ── Lebenszeichen abfragen ───────────────────────────────────────────────
$problem = $null
$antwort = $null
try {
    $antwort = Invoke-RestMethod -Uri $cfg.url -Method Get -TimeoutSec 30 -UseBasicParsing
    if ($antwort.ok -ne $true) {
        $problem = 'Scheduler still: letzter Task-Start vor ' + $antwort.scheduler_alter_min + ' Minuten (' + $antwort.scheduler_letzter_lauf + ').'
    }
} catch {
    $problem = 'Keine gültige Antwort: ' + $_.Exception.Message
}

$jetzt = Get-Date
$fehlversucheBisAlarm = 2
if ($cfg.fehlversuche_bis_alarm) { $fehlversucheBisAlarm = [int]$cfg.fehlversuche_bis_alarm }
$erinnerungMinuten = 60
if ($cfg.erinnerung_minuten) { $erinnerungMinuten = [int]$cfg.erinnerung_minuten }

if ($null -ne $problem) {
    $zustand.fehlversuche = [int]$zustand.fehlversuche + 1
    $zustand.grund = $problem
    Schreibe-Log ('STÖRUNG (' + $zustand.fehlversuche + '): ' + $problem)

    if ($zustand.fehlversuche -ge $fehlversucheBisAlarm) {
        $faellig = $false
        if ($zustand.stoerung -ne $true) {
            $zustand.stoerung = $true
            $zustand.seit = $jetzt.ToString('yyyy-MM-dd HH:mm:ss')
            $faellig = $true
        } elseif (-not $zustand.letzte_mail -or ($jetzt - [datetime]$zustand.letzte_mail).TotalMinutes -ge $erinnerungMinuten) {
            $faellig = $true
        }
        if ($faellig) {
            $betreff = '[Wächter ' + $cfg.name + '] STÖRUNG seit ' + ([datetime]$zustand.seit).ToString('dd.MM. HH:mm')
            $inhalt = "Das Intranet antwortet nicht wie erwartet.`r`n`r`n" + $problem +
                "`r`n`r`nAdresse: " + ($cfg.url -replace '/webhooks/ekkon/[^/]+/', '/webhooks/ekkon/…/') +
                "`r`nGeprüft von: " + $env:COMPUTERNAME + "`r`nErinnerung alle " + $erinnerungMinuten + ' Minuten, bis es wieder läuft.'
            try {
                Sende-Mail $betreff $inhalt
                $zustand.letzte_mail = $jetzt.ToString('yyyy-MM-dd HH:mm:ss')
                Schreibe-Log 'Alarm-Mail verschickt.'
            } catch {
                Schreibe-Log ('Mail fehlgeschlagen: ' + $_.Exception.Message)
            }
        }
    }
} else {
    if ($zustand.stoerung -eq $true) {
        $betreff = '[Wächter ' + $cfg.name + '] wieder in Ordnung'
        $inhalt = 'Das Intranet antwortet wieder (Störung seit ' + $zustand.seit + ', zuletzt: ' + $zustand.grund + ').' +
            "`r`nLetzter Task-Start vor " + $antwort.scheduler_alter_min + ' Minuten.'
        try {
            Sende-Mail $betreff $inhalt
            Schreibe-Log 'Entwarnung verschickt.'
        } catch {
            Schreibe-Log ('Mail fehlgeschlagen: ' + $_.Exception.Message)
        }
    }
    $zustand = [pscustomobject]@{ stoerung = $false; seit = ''; fehlversuche = 0; letzte_mail = ''; grund = '' }
}

$zustand | ConvertTo-Json | Set-Content -Encoding UTF8 -Path $zustandDatei

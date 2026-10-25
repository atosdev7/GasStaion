# Runs the end-to-end tests (work plan 9.1 and 9.2): the web app on a scratch database + the Smart210 side as a console program.
#   powershell -File e2e\run.ps1            all scenarios
#   powershell -File e2e\run.ps1 basic      9.1 only
#   powershell -File e2e\run.ps1 offline    9.2 only
#   powershell -File e2e\run.ps1 cards      the card ledger only
#   powershell -File e2e\run.ps1 cardsmany  five cards: top-ups and fills several times, on the terminal and on the web app
#   powershell -File e2e\run.ps1 day        a business day: the terminal and the web staff
#   add -Builtin to serve the web app with PHP's built-in server instead of Apache
# The web app runs in XAMPP (D:\xampp, PHP 7.4.33): by default in a second Apache of that XAMPP on port 8124 (e2e\apache\httpd-test.conf, the Apache
# service on port 80 is not touched); the scratch database comes from the environment file this script writes for it.
# Never touches the dev database `gasstation`: it makes `gasstation_e2e` new for each scenario and checks afterwards that `gasstation` has the
# same row counts as before. Needs XAMPP's MariaDB running (it is left running).

param([string]$Only = "", [switch]$Builtin)

$ErrorActionPreference = "Continue"
$root = Split-Path -Parent $PSScriptRoot
$php = "D:\xampp\php\php.exe"
$httpd = "D:\xampp\apache\bin\httpd.exe"
$qt = "D:\Qt\5.15.2\mingw81_64\bin"
$mingw = "D:\Qt\Tools\mingw810_64\bin"
$build = Join-Path $env:TEMP "gasstation-e2e-build"
$work = Join-Path $env:TEMP "gasstation-e2e-work"
$port = 8124
$env:PATH = "$qt;$mingw;$env:PATH"
# The terminal program uses this PC's local time; the web app (and the restore marker, which is a time) must use the same time of day, as at a station.
$offset = [TimeZoneInfo]::Local.GetUtcOffset([DateTime]::Now)
$hours = [int]$offset.TotalHours   # a zone name like Etc/GMT+4 (= UTC-4) works in every PHP
$zone = $(if ($hours -eq 0) { "UTC" } else { "Etc/GMT" + $(if ($hours -lt 0) { "+" } else { "-" }) + [Math]::Abs($hours) })
$env:APP_TIMEZONE = $zone

# The tests of the end-to-end run use the sign 00000, not the real one of the business (config/ticket_sign.php).
$env:TICKET_SIGN = "00000"

New-Item -ItemType Directory -Force $build, $work | Out-Null

# 1. build the Smart210 side
Push-Location $build
& qmake "$root\e2e\e2e.pro" | Out-Null
& mingw32-make -j8 2>&1 | Where-Object { $_ -match "error" } | ForEach-Object { Write-Host $_ }
$buildCode = $LASTEXITCODE
Pop-Location
$exe = Join-Path $build "release\e2e.exe"
if ($buildCode -ne 0 -or -not (Test-Path $exe)) { throw "the e2e program was not built" }

# 2. the dev database must come out of this as it went in
Push-Location "$root\e2e"
& $php fingerprint.php | Set-Content -Encoding ascii "$work\dev.json"
Pop-Location

# 3. the web server for the scratch database. Dumps and backup exports of the scratch database go to their own folder, never to the real ones; the API limit
# of 60 requests a minute is raised (the tests send far more than a person does).
$scratch = @{
    "DB_DATABASE" = "gasstation_e2e"; "API_THROTTLE_PER_MINUTE" = "100000"; "BACKUP_DUMP_DIR" = "e2e-backups/daily"; "BACKUP_EXPORT_DIR" = "e2e-backups/exports";
    "APP_TIMEZONE" = $zone; "TICKET_SIGN" = "00000"
}
$apache = $null
$builtinServer = $null
if (-not $Builtin) {
    $lines = $scratch.GetEnumerator() | ForEach-Object { "SetEnv " + $_.Key + " " + $_.Value }
    # written, read back and written again until it is right: a file written on this PC's D: drive has come out empty once (a virus scanner holding it)
    $envFile = "$root\web\storage\logs\apache-e2e-env.conf"
    for ($try = 1; $try -le 10; $try++) {
        try { [System.IO.File]::WriteAllLines($envFile, [string[]]$lines) } catch { }
        $back = @(Get-Content $envFile -ErrorAction SilentlyContinue)
        if ($back.Count -eq @($lines).Count) { break }
        Start-Sleep -Seconds 1
    }
    if ($back.Count -ne @($lines).Count) { throw "could not write $envFile" }
    # an Apache of this configuration that is still running from an earlier run is stopped first (it has the old environment)
    Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" | Where-Object { $_.CommandLine -like "*httpd-test.conf*" } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Start-Sleep -Seconds 2
    $apache = Start-Process -FilePath $httpd -ArgumentList "-f", "$root\e2e\apache\httpd-test.conf" -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 4
    $answer = & curl.exe -s -o NUL -w "%{http_code}" "http://127.0.0.1:$port/login"
    if ($answer -ne "200") { throw "the web app did not answer on port $port (HTTP $answer): see web\storage\logs\apache-test-error.log" }
    Write-Host "web app: Apache + PHP 7.4.33 on port $port"
}

$scenarios = @("basic", "offline", "restore", "cards", "cardsmany", "day")
if ($Only -ne "") { $scenarios = @($Only) }
$failed = $false

try {
    foreach ($scenario in $scenarios) {
        Write-Host "`n############ $scenario ############"
        Push-Location "$root\e2e"
        # (the scratch database is made again; a file lock of the XAMPP temp folder on D: sometimes stops it, then it is tried again)
        foreach ($try in 1..6) {
            & $php prepare.php
            if ($LASTEXITCODE -eq 0) { break }
            Write-Host "prepare.php failed (try $try of 6), again"
            Start-Sleep -Seconds 3
        }
        Pop-Location
        Remove-Item -Recurse -Force "$root\web\storage\app\e2e-backups" -ErrorAction SilentlyContinue

        if ($Builtin) {
            foreach ($k in $scratch.Keys) { Set-Item -Path "Env:\$k" -Value $scratch[$k] }
            $builtinServer = Start-Process -FilePath $php -ArgumentList "-S", "127.0.0.1:$port", "-t", "$root\web\public", "$root\web\server.php" -PassThru -WindowStyle Hidden
            Remove-Item Env:\DB_DATABASE, Env:\API_THROTTLE_PER_MINUTE, Env:\BACKUP_DUMP_DIR, Env:\BACKUP_EXPORT_DIR
            $env:APP_TIMEZONE = $zone
            $env:TICKET_SIGN = "00000"
            Start-Sleep -Seconds 3
        }

        try {
            $facts = "$work\facts_$scenario.json"
            & $exe $scenario 127.0.0.1 $port $facts $php "$root\e2e"
            if ($LASTEXITCODE -ne 0) { $failed = $true }
            Push-Location "$root\e2e"
            & $php verify.php $scenario $facts "$work\dev.json"
            if ($LASTEXITCODE -ne 0) { $failed = $true }
            Pop-Location
        } finally {
            if ($builtinServer) { Stop-Process -Id $builtinServer.Id -Force; $builtinServer = $null }
        }
    }
} finally {
    if ($apache) {
        Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" | Where-Object { $_.CommandLine -like "*httpd-test.conf*" } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    }
}

if ($failed) { Write-Host "`nEND-TO-END TESTS FAILED"; exit 1 }
Write-Host "`nEND-TO-END TESTS PASSED"

# Runs the end-to-end tests (work plan 9.1 and 9.2): the web app on a scratch database + the Smart210 side as a console program.
#   powershell -File e2e\run.ps1            both scenarios
#   powershell -File e2e\run.ps1 basic      9.1 only
#   powershell -File e2e\run.ps1 offline    9.2 only
#   powershell -File e2e\run.ps1 cards      the card ledger only
# Never touches the dev database `gasstation`: it makes `gasstation_e2e` new for each scenario and checks afterwards that `gasstation` has the
# same row counts as before. Needs XAMPP's MariaDB running (it is left running).

param([string]$Only = "")

$ErrorActionPreference = "Continue"
$root = Split-Path -Parent $PSScriptRoot
$php = "D:\php72\php.exe"
$qt = "D:\Qt\5.15.2\mingw81_64\bin"
$mingw = "D:\Qt\Tools\mingw810_64\bin"
$build = Join-Path $env:TEMP "gasstation-e2e-build"
$work = Join-Path $env:TEMP "gasstation-e2e-work"
$port = 8124
$env:PATH = "$qt;$mingw;$env:PATH"
# The terminal program uses this PC's local time; the web app (and the restore marker, which is a time) must use the same time of day, as at a station.
$offset = [TimeZoneInfo]::Local.GetUtcOffset([DateTime]::Now)
$hours = [int]$offset.TotalHours   # PHP 7.2 takes zone names only: Etc/GMT+4 is UTC-4
$env:APP_TIMEZONE = $(if ($hours -eq 0) { "UTC" } else { "Etc/GMT" + $(if ($hours -lt 0) { "+" } else { "-" }) + [Math]::Abs($hours) })

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

$scenarios = @("basic", "offline", "restore", "cards")
if ($Only -ne "") { $scenarios = @($Only) }
$failed = $false

foreach ($scenario in $scenarios) {
    Write-Host "`n############ $scenario ############"
    Push-Location "$root\e2e"
    & $php prepare.php
    Pop-Location

    # the web app on the scratch database (the API limit of 60 requests a minute is raised: the tests send far more than a person does)
    $env:DB_DATABASE = "gasstation_e2e"
    $env:API_THROTTLE_PER_MINUTE = "100000"
    $server = Start-Process -FilePath $php -ArgumentList "-S", "127.0.0.1:$port", "-t", "$root\web\public", "$root\web\server.php" -PassThru -WindowStyle Hidden
    Remove-Item Env:\DB_DATABASE, Env:\API_THROTTLE_PER_MINUTE
    Start-Sleep -Seconds 3

    try {
        $facts = "$work\facts_$scenario.json"
        & $exe $scenario 127.0.0.1 $port $facts $php "$root\e2e"
        if ($LASTEXITCODE -ne 0) { $failed = $true }
        Push-Location "$root\e2e"
        & $php verify.php $scenario $facts "$work\dev.json"
        if ($LASTEXITCODE -ne 0) { $failed = $true }
        Pop-Location
    } finally {
        Stop-Process -Id $server.Id -Force
    }
}

if ($failed) { Write-Host "`nEND-TO-END TESTS FAILED"; exit 1 }
Write-Host "`nEND-TO-END TESTS PASSED"

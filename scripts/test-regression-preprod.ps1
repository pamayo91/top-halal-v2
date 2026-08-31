param(
    [string]$PreprodHost = $(if ($env:PREPROD_SSH_HOST) { $env:PREPROD_SSH_HOST } else { 'top-halal-preprod' }),
    [string]$PreprodPath = $(if ($env:PREPROD_APP_PATH) { $env:PREPROD_APP_PATH } else { '/home/meyo5199/top-halal-v2' }),
    [string]$BaseUrl = $(if ($env:PREPROD_BASE_URL) { $env:PREPROD_BASE_URL } else { 'https://dev.top-halal.fr' })
)

$ErrorActionPreference = 'Stop'
$remotePhp = '/opt/alt/php84/usr/bin/php'
$phpTests = & ssh $PreprodHost "cd $PreprodPath && $remotePhp artisan config:clear && $remotePhp artisan test --filter=Regression; $status=`$?; $remotePhp artisan config:cache; exit `$status"
if ($LASTEXITCODE -ne 0) {
    if ($phpTests) { Write-Error $phpTests }
    exit $LASTEXITCODE
}

$remote = "cd $PreprodPath && $remotePhp artisan regression:verify --json"
$sentinels = & ssh $PreprodHost $remote
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$sentinelPayload = $sentinels | ConvertFrom-Json
if ($sentinelPayload.errors.Count -gt 0) {
    $sentinelPayload.errors | ForEach-Object { Write-Error $_ }
    exit 1
}

$since = & ssh $PreprodHost "date -u +%Y-%m-%dT%H:%M:%SZ"
$env:PREPROD_BASE_URL = $BaseUrl
$env:REGRESSION_SENTINELS_JSON = $sentinels
& npx playwright test tests/e2e/regression
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$final = & ssh $PreprodHost "cd $PreprodPath && $remotePhp artisan regression:verify --json"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
$finalPayload = $final | ConvertFrom-Json
if ($finalPayload.errors.Count -gt 0) {
    $finalPayload.errors | ForEach-Object { Write-Error $_ }
    exit 1
}

$logs = & ssh $PreprodHost "cd $PreprodPath && $remotePhp artisan regression:check-logs --since=$since --json"
if ($LASTEXITCODE -ne 0) {
    if ($logs) { Write-Error $logs }
    exit $LASTEXITCODE
}

Write-Host "Regression preproduction passed since $since."

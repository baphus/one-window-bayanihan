param(
    [Parameter(Mandatory=$true, HelpMessage="Your dev tunnel URL, e.g. https://xxxx-8000.asse.devtunnels.ms")]
    [string]$Url
)

$envPath = Join-Path $PSScriptRoot ".env"

# 1. Update APP_URL in .env
$content = Get-Content $envPath -Raw
$content = $content -replace '^APP_URL=.*$', "APP_URL=$Url"
Set-Content $envPath -Value $content
Write-Host "[1/2] APP_URL updated to $Url"

# 2. Clear Laravel caches (forceRootUrl picks up the new APP_URL)
Write-Host "[2/2] Clearing Laravel caches..."
Push-Location $PSScriptRoot
$null = php artisan optimize:clear 2>&1
Pop-Location
if ($LASTEXITCODE -eq 0) {
    Write-Host "[2/2] Cache cleared"
} else {
    Write-Host "[2/2] optimize:clear FAILED" -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "Done. App is tunnel-ready at: $Url" -ForegroundColor Green
Write-Host ""
Write-Host "Note: the Vite dev server is still running (from composer run dev)." -ForegroundColor Yellow
Write-Host "Vite assets are cross-origin - handled by the CORS config in vite.config.js" -ForegroundColor Yellow
Write-Host "Route/redirect URLs will use the tunnel URL via forceRootUrl." -ForegroundColor Yellow
Write-Host ""
Write-Host "To switch back to local dev: .\local.ps1"

$envPath = Join-Path $PSScriptRoot ".env"
$hotPath = Join-Path $PSScriptRoot "public" "hot"

# 1. Restore APP_URL to localhost
$content = Get-Content $envPath -Raw
$content = $content -replace '^APP_URL=.*$', "APP_URL=http://127.0.0.1:8000"
Set-Content $envPath -Value $content
Write-Host "[1/3] APP_URL restored to http://127.0.0.1:8000"

# 2. Delete hot file so Vite generates a fresh one on next start
if (Test-Path $hotPath) {
    Remove-Item $hotPath -Force
    Write-Host "[2/3] public/hot deleted (Vite will regenerate on next start)"
} else {
    Write-Host "[2/3] no hot file to clean up"
}

# 3. Clear Laravel caches
Write-Host "[3/3] Clearing Laravel caches..."
Push-Location $PSScriptRoot
$null = php artisan optimize:clear 2>&1
Pop-Location
if ($LASTEXITCODE -eq 0) {
    Write-Host "[3/3] Cache cleared"
} else {
    Write-Host "[3/3] optimize:clear FAILED" -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "Back to local dev mode." -ForegroundColor Green
Write-Host "Start everything: composer run dev" -ForegroundColor Yellow

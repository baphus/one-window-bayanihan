param([string]$BackupFile = $null)

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$pgBin = Join-Path $env:LOCALAPPDATA "com.tinyapp.DBngin\Binaries\postgresql\17.0\bin"
$gzipBin = (Get-Command gzip -ErrorAction SilentlyContinue).Source
if (-not $gzipBin) { $gzipBin = "C:\Program Files\Git\usr\bin\gzip.exe" }
$backupDir = Join-Path $projectRoot "storage\backups"
[void](New-Item -ItemType Directory -Force -Path $backupDir)

if (-not $BackupFile) {
    $timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $filename = "bayanihan-db-$timestamp.sql.gz"
    $localPath = Join-Path $backupDir $filename
    
    Write-Host "Step 1: Backup bayanihan_test -> $filename"
    
    $q = '"'; $conn = "postgresql://postgres@127.0.0.1:5432/bayanihan_test"
    cmd /c "set PGPASSWORD= && $q$pgBin\pg_dump.exe$q $conn --no-owner --no-acl 2>nul | $q$gzipBin$q -c > $q$localPath$q" 2>&1
    
    if ($LASTEXITCODE -eq 0 -and (Test-Path $localPath)) {
        $size = (Get-Item $localPath).Length
        if ($size -gt 0) {
            Write-Host "OK Backup saved: $filename ($([math]::Round($size/1KB, 1)) KB)"
        } else {
            Write-Host "FAIL Backup file is empty (0 bytes)"
            exit 1
        }
    } else {
        Write-Host "FAIL Backup exit code: $LASTEXITCODE"
        exit 1
    }
    $BackupFile = $localPath
}

$testDb = "bayanihan_drill_$(Get-Date -Format 'yyyyMMddHHmmss')"
$decompressed = Join-Path $env:TEMP "restore-$(Get-Random).sql"

Write-Host "Step 2: Restore test from $BackupFile -> $testDb"

& "$pgBin\createdb.exe" -U postgres -h 127.0.0.1 $testDb 2>&1
if ($LASTEXITCODE -ne 0) { Write-Host "FAIL create test DB"; exit 1 }
Write-Host "OK Created: $testDb"

$q = '"'
cmd /c "$q$gzipBin$q -dc $q$BackupFile$q > $q$decompressed$q" 2>&1

if (-not (Test-Path $decompressed) -or (Get-Item $decompressed).Length -eq 0) {
    Write-Host "FAIL Decompression produced empty file"
    & "$pgBin\dropdb.exe" -U postgres -h 127.0.0.1 $testDb | Out-Null
    exit 1
}
$dsize = [math]::Round((Get-Item $decompressed).Length / 1KB, 1)
Write-Host "OK Decompressed ($dsize KB)"

& "$pgBin\psql.exe" -U postgres -h 127.0.0.1 -d $testDb -f $decompressed -q 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "FAIL Restore exit code: $LASTEXITCODE"
    & "$pgBin\dropdb.exe" -U postgres -h 127.0.0.1 $testDb | Out-Null
    Remove-Item -Force $decompressed -ErrorAction SilentlyContinue
    exit 1
}
Write-Host "OK Restore successful"

$tables = & "$pgBin\psql.exe" -U postgres -h 127.0.0.1 -d $testDb -t -A -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>&1
Write-Host "Tables restored: $tables"

Write-Host "Row counts:"
foreach ($t in @("cases","clients","users","referrals","milestones")) {
    $cnt = & "$pgBin\psql.exe" -U postgres -h 127.0.0.1 -d $testDb -t -A -c "SELECT count(*) FROM $t;" 2>&1
    Write-Host "  $t = $cnt"
}

Write-Host "Cleanup..."
Remove-Item -Force $decompressed -ErrorAction SilentlyContinue
& "$pgBin\dropdb.exe" -U postgres -h 127.0.0.1 $testDb | Out-Null
Write-Host "DRILL PASSED - backup integrity confirmed"

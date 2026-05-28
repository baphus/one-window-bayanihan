# Start Mailpit email sandbox (SMTP catch-all + Web UI)
# SMTP: 127.0.0.1:1025 | Web: http://127.0.0.1:8025
# Kill existing first, then start fresh
Get-Process mailpit -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Process -WindowStyle Hidden mailpit -ArgumentList "--smtp-binding 127.0.0.1:1025 --listen-bind-addr 127.0.0.1:8025"
Write-Host "Mailpit started! Web UI: http://127.0.0.1:8025" -ForegroundColor Green

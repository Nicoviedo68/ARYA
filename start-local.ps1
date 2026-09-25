# Arya CRM — arranque local
$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

if (-not (Test-Path "$Root\.env")) {
    Copy-Item "$Root\.env.example" "$Root\.env"
    Write-Host "Se creo .env desde .env.example. Revisa DB y APP_URL." -ForegroundColor Yellow
}

$envFile = Get-Content "$Root\.env" -Raw
if ($envFile -notmatch 'APP_ENV\s*=\s*local') {
    Write-Host "Aviso: APP_ENV no es 'local' en .env" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Arya CRM local" -ForegroundColor Cyan
Write-Host "  URL:  http://localhost:8080" -ForegroundColor Green
Write-Host "  Root: $Root\public" -ForegroundColor DarkGray
Write-Host "  Ctrl+C para detener" -ForegroundColor DarkGray
Write-Host ""

php -S localhost:8080 -t public

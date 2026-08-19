$ErrorActionPreference = "Stop"

Write-Host "[SIAGA KARTA] Rebuild Docker..." -ForegroundColor Cyan
docker compose down
docker compose up -d --build

Write-Host "[SIAGA KARTA] Menunggu service app sehat..." -ForegroundColor Cyan
$healthy = $false
$appId = ""
for ($i = 1; $i -le 60; $i++) {
    $appId = (docker compose ps -q app).Trim()
    if ($appId) {
        $status = docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' $appId 2>$null
        if ($status -eq "healthy") {
            $healthy = $true
            break
        }
    }
    Start-Sleep -Seconds 2
}

Write-Host ""
docker compose ps

if (-not $healthy) {
    Write-Host "`nAPP belum healthy. Log terakhir:" -ForegroundColor Red
    docker compose logs app --tail=200
    exit 1
}

# Docker Compose membaca APP_PORT dari .env. Untuk smoke-test ini ambil nilai
# tersebut jika tersedia, lalu fallback ke port paket SIAGA KARTA.
$port = "8080"
if (Test-Path ".env") {
    $portLine = Get-Content ".env" | Where-Object { $_ -match '^APP_PORT=' } | Select-Object -First 1
    if ($portLine) {
        $candidate = ($portLine -split '=', 2)[1].Trim().Trim('"').Trim("'")
        if ($candidate) { $port = $candidate }
    }
}

try {
    $response = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$port/up" -TimeoutSec 10
    if ($response.StatusCode -ne 200) { throw "HTTP $($response.StatusCode)" }
    Write-Host "`nSIAGA KARTA siap di http://127.0.0.1:$port" -ForegroundColor Green
} catch {
    Write-Host "`nContainer healthy, tetapi endpoint lokal belum dapat diverifikasi: $($_.Exception.Message)" -ForegroundColor Yellow
    exit 1
}

param(
    [switch]$ResetData,
    [switch]$NoBuild
)

$ErrorActionPreference = "Stop"
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

function Fail([string]$Message) {
    Write-Host "`n[ERROR] $Message" -ForegroundColor Red
    exit 1
}

function Get-ServiceState([string]$Service) {
    $id = (& docker compose ps -q $Service 2>$null | Out-String).Trim()
    if (-not $id) { return "missing" }
    $state = (& docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' $id 2>$null | Out-String).Trim()
    if (-not $state) { return "unknown" }
    return $state
}

function Wait-Service([string]$Service, [int]$Attempts = 90) {
    for ($i = 1; $i -le $Attempts; $i++) {
        $state = Get-ServiceState $Service
        if ($state -eq "healthy" -or ($Service -eq "scheduler" -and $state -eq "running")) { return $true }
        if ($state -eq "exited" -or $state -eq "dead") { return $false }
        Start-Sleep -Seconds 2
    }
    return $false
}

Write-Host "SIAGA KARTA - DEMO START" -ForegroundColor Cyan

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Fail "Docker tidak ditemukan. Jalankan Docker Desktop terlebih dahulu."
}

try { & docker info *> $null } catch { Fail "Docker Desktop belum siap/running." }

if (-not (Test-Path ".env")) {
    if (Test-Path ".env.demo") {
        Copy-Item ".env.demo" ".env"
        Write-Host "[OK] .env dibuat dari .env.demo" -ForegroundColor Green
    } else {
        Fail ".env dan .env.demo tidak ditemukan."
    }
}

Write-Host "[1] Validasi Docker Compose..." -ForegroundColor Cyan
& docker compose config --quiet
if ($LASTEXITCODE -ne 0) { Fail "docker compose config gagal." }

if ($ResetData) {
    Write-Host "[2] Reset container + volume database demo..." -ForegroundColor Yellow
    & docker compose down -v --remove-orphans
} else {
    Write-Host "[2] Stop container lama tanpa menghapus data..." -ForegroundColor Cyan
    & docker compose down --remove-orphans
}

Write-Host "[3] Menyalakan service..." -ForegroundColor Cyan
if ($NoBuild) {
    & docker compose up -d
} else {
    & docker compose up -d --build
}
if ($LASTEXITCODE -ne 0) {
    Write-Host "`n--- DB LOG ---" -ForegroundColor Yellow
    & docker compose logs db --tail=120
    Write-Host "`n--- APP LOG ---" -ForegroundColor Yellow
    & docker compose logs app --tail=160
    Fail "docker compose up gagal."
}

Write-Host "[4] Cek database..." -ForegroundColor Cyan
if (-not (Wait-Service "db" 90)) {
    & docker compose ps
    Write-Host "`n--- DB LOG ---" -ForegroundColor Yellow
    & docker compose logs db --tail=160
    Fail "Database belum healthy."
}
Write-Host "[OK] Database healthy" -ForegroundColor Green

Write-Host "[5] Cek aplikasi + migration + seeder..." -ForegroundColor Cyan
if (-not (Wait-Service "app" 120)) {
    & docker compose ps
    Write-Host "`n--- APP LOG ---" -ForegroundColor Yellow
    & docker compose logs app --tail=220
    Fail "Aplikasi belum healthy."
}
Write-Host "[OK] App healthy" -ForegroundColor Green

$port = "8080"
$portLine = Get-Content ".env" | Where-Object { $_ -match '^APP_PORT=' } | Select-Object -First 1
if ($portLine) {
    $candidate = ($portLine -split '=', 2)[1].Trim().Trim('"').Trim("'")
    if ($candidate) { $port = $candidate }
}

try {
    $health = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$port/up" -TimeoutSec 10
    if ($health.StatusCode -ne 200) { throw "HTTP $($health.StatusCode)" }
    $bootstrap = Invoke-RestMethod -Uri "http://127.0.0.1:$port/api/public/bootstrap" -TimeoutSec 10
    if ($null -eq $bootstrap.demo) { throw "API bootstrap tidak sesuai." }

    $regions = Invoke-RestMethod -Uri "http://127.0.0.1:$port/api/public/regions" -TimeoutSec 15
    $villages = @($regions.kelurahan)
    $districtIds = @($villages | ForEach-Object { $_.parent.id } | Where-Object { $_ } | Sort-Object -Unique)
    if ($villages.Count -ne 151) { throw "Master wilayah tidak lengkap: $($villages.Count) kelurahan, seharusnya 151." }
    if ($districtIds.Count -ne 30) { throw "Master wilayah tidak lengkap: $($districtIds.Count) kecamatan, seharusnya 30." }
} catch {
    & docker compose ps
    & docker compose logs app --tail=160
    Fail "Container healthy tetapi smoke test HTTP gagal: $($_.Exception.Message)"
}

Write-Host "`n==============================" -ForegroundColor Green
Write-Host " SIAGA KARTA SIAP UNTUK DEMO" -ForegroundColor Green
Write-Host "==============================" -ForegroundColor Green
Write-Host "URL      : http://localhost:$port"
Write-Host "Wilayah  : 30 kecamatan / 151 kelurahan"
Write-Host "Username : kota / kecamatan / kelurahan"
Write-Host "Password : Rajawali21"
Write-Host "`nStatus container:"
& docker compose ps

$ErrorActionPreference = "Stop"
& (Join-Path $PSScriptRoot "demo-start.ps1") -ResetData
exit $LASTEXITCODE

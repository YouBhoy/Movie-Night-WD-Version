param(
    [string]$XamppRoot = 'C:\xampp',
    [string]$BindAddress = '127.0.0.1',
    [int]$Port = 8080
)
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$phpPath = Join-Path $XamppRoot 'php\php.exe'
if (!(Test-Path -LiteralPath $phpPath)) { throw "PHP was not found at $phpPath" }
Write-Host "Movie Night: http://$($BindAddress):$Port (Ctrl+C to stop)"
& $phpPath -S "$($BindAddress):$Port" -t (Join-Path $projectPath 'public')
if ($LASTEXITCODE -ne 0) { throw 'The development server stopped with an error.' }

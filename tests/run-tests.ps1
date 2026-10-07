param([string]$XamppRoot = 'C:\xampp')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpPath = Join-Path $XamppRoot 'php\php.exe'
$mysqlPath = Join-Path $XamppRoot 'mysql\bin'
$runtimePath = Join-Path $projectRoot '.test-runtime'
$dataPath = Join-Path $runtimePath 'mysql'
$serverProcess = $null

function Invoke-Checked([string]$Executable, [string[]]$Arguments) {
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Command failed: $Executable" }
}

# Refuse to use an existing server: this suite resets only its isolated test database.
$probe = New-Object System.Net.Sockets.TcpClient
try {
    try { $probe.Connect('127.0.0.1', 33079) } catch {}
    if ($probe.Connected) { throw 'Port 33079 is already in use. Stop that process or choose another test environment.' }
} finally { $probe.Dispose() }
New-Item -ItemType Directory -Path $runtimePath -Force | Out-Null
try {
    Invoke-Checked -Executable $phpPath -Arguments @((Join-Path $PSScriptRoot 'booking-service-test.php'))
    Invoke-Checked -Executable $phpPath -Arguments @((Join-Path $PSScriptRoot 'endpoint-security-test.php'))
    $configurationPath = Join-Path $dataPath 'my.ini'
    if (!(Test-Path -LiteralPath $configurationPath)) {
        Invoke-Checked -Executable (Join-Path $mysqlPath 'mysql_install_db.exe') -Arguments @("--datadir=$dataPath", '--port=33079', '--silent')
    }
    $serverProcess = Start-Process -FilePath (Join-Path $mysqlPath 'mysqld.exe') -ArgumentList @("--defaults-file=`"$configurationPath`"", '--bind-address=127.0.0.1', '--port=33079') -WindowStyle Hidden -PassThru
    $ready = $false
    for ($attempt = 0; $attempt -lt 50; $attempt++) {
        & $phpPath (Join-Path $PSScriptRoot 'database-ready.php')
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        if ($serverProcess.HasExited) { throw 'Isolated MariaDB process exited during startup.' }
        Start-Sleep -Milliseconds 200
    }
    if (!$ready) { throw 'Isolated MariaDB did not become ready.' }
    Invoke-Checked -Executable $phpPath -Arguments @((Join-Path $PSScriptRoot 'mysql-integration-test.php'))
    $phpFiles = Get-ChildItem -LiteralPath $projectRoot -Filter '*.php' -File
    $phpFiles += Get-ChildItem -LiteralPath (Join-Path $projectRoot 'services') -Filter '*.php' -File
    $phpFiles += Get-ChildItem -LiteralPath $PSScriptRoot -Filter '*.php' -File
    foreach ($file in $phpFiles) { Invoke-Checked -Executable $phpPath -Arguments @('-l', $file.FullName) }
} finally {
    if ($serverProcess -and !$serverProcess.HasExited) {
        & (Join-Path $mysqlPath 'mysqladmin.exe') --host=127.0.0.1 --port=33079 --user=root shutdown
        if (!$serverProcess.WaitForExit(10000)) { Stop-Process -Id $serverProcess.Id -Force }
    }
}

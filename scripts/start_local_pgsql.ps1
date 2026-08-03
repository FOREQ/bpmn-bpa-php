param(
    [string]$PostgresRoot = 'C:\PostgreSQL\18',
    [string]$PostgresData = 'C:\PostgreSQL\data',
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'

$appRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $appRoot '.env.local'
$php = 'C:\xampp\php\php.exe'
$pgCtl = Join-Path $PostgresRoot 'bin\pg_ctl.exe'
$pgIsReady = Join-Path $PostgresRoot 'bin\pg_isready.exe'
$pgLog = Join-Path $PostgresData 'server.log'

foreach ($requiredPath in @($envFile, $php, $pgCtl, $pgIsReady)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Required local file was not found: $requiredPath"
    }
}

foreach ($line in Get-Content -LiteralPath $envFile -Encoding UTF8) {
    if ($line -match '^\s*([^#=]+?)\s*=\s*(.*)\s*$') {
        [Environment]::SetEnvironmentVariable($matches[1], $matches[2], 'Process')
    }
}

if ($env:DB_DRIVER -ne 'pgsql') {
    throw 'DB_DRIVER=pgsql is required in .env.local'
}

& $pgIsReady -h $env:DB_HOST -p $env:DB_PORT | Out-Null

if ($LASTEXITCODE -ne 0) {
    & $pgCtl start -D $PostgresData -l $pgLog -w -t 30

    if ($LASTEXITCODE -ne 0) {
        throw "PostgreSQL did not start. Check $pgLog"
    }
}

Write-Host "PostgreSQL is ready. Site: http://127.0.0.1:$Port"
& $php -S "127.0.0.1:$Port" -t $appRoot (Join-Path $appRoot 'router.php')

param([string]$OutputDirectory = 'docs/evidencias/local')
$ErrorActionPreference = 'Stop'
$project = Split-Path $PSScriptRoot -Parent
Push-Location $project
try {
    New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
    $checks = @(
        @{Name='roles'; Program='php'; Arguments=@('tests/role_access.php')},
        @{Name='frontend'; Program='node'; Arguments=@('tests/role_frontend.mjs')},
        @{Name='api'; Program='php'; Arguments=@('tests/api_access.php')},
        @{Name='integracion'; Program='php'; Arguments=@('tests/integration.php')}
    )
    $results = @()
    foreach($check in $checks) {
        $start = [DateTimeOffset]::UtcNow
        $program=$check.Program; $arguments=$check.Arguments
        $output = & $program @arguments 2>&1
        $code = $LASTEXITCODE
        $path = Join-Path $OutputDirectory ($check.Name+'.txt')
        $output | Set-Content -Encoding utf8 -LiteralPath $path
        $results += @{prueba=$check.Name; inicio_utc=$start.ToString('o'); fin_utc=[DateTimeOffset]::UtcNow.ToString('o'); exit_code=$code; sha256=(Get-FileHash -Algorithm SHA256 -LiteralPath $path).Hash.ToLowerInvariant()}
        Write-Output ($check.Name+': exit '+$code)
        if($code -ne 0) { $output | Write-Output; throw ('Falló '+$check.Name) }
    }
    @{generado_utc=[DateTimeOffset]::UtcNow.ToString('o'); entorno='XAMPP local'; resultados=$results} | ConvertTo-Json -Depth 6 | Set-Content -Encoding utf8 (Join-Path $OutputDirectory 'manifest.json')
} finally { Pop-Location }

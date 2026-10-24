$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Join-Path $PSScriptRoot 'wedding-studio')
Write-Host 'Website: http://127.0.0.1:8000'
Write-Host 'Quan tri: http://127.0.0.1:8000/admin'
Write-Host 'Nhan Ctrl+C de dung.'
php artisan serve --host=127.0.0.1 --port=8000 --no-reload

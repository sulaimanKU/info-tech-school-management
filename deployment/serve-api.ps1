param(
 [string]$BindAddress='127.0.0.1',
 [ValidateRange(1024,65535)][int]$Port=8000,
 [string]$PhpBinary='C:\xampp\php\php.exe',
 [ValidateSet('mysql','pgsql')][string]$DatabaseDriver='mysql'
)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
$apiRoot=Join-Path $projectRoot 'api'
$router=Join-Path $apiRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
if(!(Test-Path -LiteralPath $PhpBinary)){throw 'PHP executable was not found. Supply -PhpBinary.'}
if(!(Test-Path -LiteralPath $router)){throw 'Install the existing API Composer dependencies first.'}
Push-Location (Join-Path $apiRoot 'public')
try {
 # Run the HTTP worker itself with the extensions. Artisan serve does not forward parent -d flags.
 $extensionArguments=@('-d','extension=gd')
 # PostgreSQL is an explicit legacy development option, never a MySQL runtime requirement.
 if($DatabaseDriver -eq 'pgsql'){$extensionArguments+=@('-d','extension=pdo_pgsql')}
 & $PhpBinary @extensionArguments -S "${BindAddress}:$Port" $router
 if($LASTEXITCODE -ne 0){throw 'Development API server stopped with an error.'}
}finally{Pop-Location}

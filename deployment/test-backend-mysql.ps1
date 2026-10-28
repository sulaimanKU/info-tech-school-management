param([string]$PhpBinary='php')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
$apiRoot=Join-Path $projectRoot 'api'
$snapshot=@{}
$values=@{
 APP_ENV='testing'; APP_DEBUG='false'; DB_CONNECTION='mysql'; DB_HOST='127.0.0.1';
 DB_PORT='53317'; DB_DATABASE='infotech_compatibility_test'; DB_USERNAME='root';
 DB_PASSWORD='(empty)'; DB_URL='(null)'; CACHE_STORE='array'; SMS_DRIVER='disabled'
}
foreach($name in $values.Keys){$snapshot[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
Push-Location $apiRoot
try {
 foreach($name in $values.Keys){[Environment]::SetEnvironmentVariable($name,$values[$name],'Process')}
 # Refuse cached configuration and every database outside this disposable local instance.
 & $PhpBinary tests/Support/assert-validation-database.php
 if($LASTEXITCODE -ne 0){throw 'Isolated MySQL validation database check failed.'}
 & $PhpBinary -d extension=gd vendor/phpunit/phpunit/phpunit -c phpunit.xml
 if($LASTEXITCODE -ne 0){throw 'MySQL compatibility test suite failed.'}
} finally {
 Pop-Location
 foreach($name in $snapshot.Keys){[Environment]::SetEnvironmentVariable($name,$snapshot[$name],'Process')}
}

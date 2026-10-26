param([ValidateRange(1024,65535)][int]$Port=8001)
$ErrorActionPreference='Stop'
$validationEnvironmentSnapshot=@{}
foreach($validationEnvironmentName in @('APP_KEY','APP_ENV','APP_DEBUG','SMS_DRIVER','CACHE_STORE','DB_URL','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','PGPASSWORD')){
 $validationEnvironmentSnapshot[$validationEnvironmentName]=[Environment]::GetEnvironmentVariable($validationEnvironmentName,'Process')
}

$projectRoot=Split-Path $PSScriptRoot -Parent
$pgBin='C:\Program Files\PostgreSQL\18\bin'
$pgData=Join-Path $projectRoot 'database\.validation-data'
$secretPath=Join-Path $projectRoot 'database\.env.validation'
if(!(Test-Path -LiteralPath $secretPath)){throw 'Run test-backend.ps1 first.'}
$env:DB_URL='';$env:DB_CONNECTION='pgsql';$env:DB_HOST='127.0.0.1';$env:DB_PORT='55433';$env:DB_DATABASE='infotech_school_test';$env:DB_USERNAME='infotech_validation';$env:DB_PASSWORD=[System.IO.File]::ReadAllText($secretPath)
$env:APP_ENV='local';$env:APP_DEBUG='false';$env:SMS_DRIVER='local'
$env:CACHE_STORE='file'
. (Join-Path $PSScriptRoot 'validation-key.ps1')
Set-ValidationEncryptionKey
$started=$false
try {
 & "$pgBin\pg_ctl.exe" -D $pgData status *> $null
 if($LASTEXITCODE -ne 0){ & "$pgBin\pg_ctl.exe" -D $pgData -l (Join-Path $projectRoot 'database\.validation.log') -o '-h 127.0.0.1 -p 55433' -w start }
 if($LASTEXITCODE -ne 0){throw 'Validation cluster startup failed.'};$started=$true
 Push-Location (Join-Path $projectRoot 'api')
 try {
  & php -d extension=pdo_pgsql -d extension=gd tests/Support/assert-validation-database.php
  if($LASTEXITCODE -ne 0){throw 'Refusing to migrate or test a non-validation database.'}
  & php -d extension=pdo_pgsql -d extension=gd artisan migrate:fresh --seed --force
  if($LASTEXITCODE -ne 0){throw 'Isolated migration failed.'}
  & php -d extension=pdo_pgsql -d extension=gd artisan school:seed-mobile
  & php -d extension=pdo_pgsql -d extension=gd artisan school:seed-validation-fixtures
  if($LASTEXITCODE -ne 0){throw 'Fictional fixture setup failed.'}
  Push-Location (Join-Path $projectRoot 'api\public')
  try { & php -d extension=pdo_pgsql -d extension=gd -S "127.0.0.1:$Port" (Join-Path $projectRoot 'api\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php') } finally { Pop-Location }
 }finally{Pop-Location}
}finally{
 if($started){& "$pgBin\pg_ctl.exe" -D $pgData -m fast -w stop}
 Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue
 foreach($validationEnvironmentName in $validationEnvironmentSnapshot.Keys){
 [Environment]::SetEnvironmentVariable($validationEnvironmentName,$validationEnvironmentSnapshot[$validationEnvironmentName],'Process')
}
}

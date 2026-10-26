param([switch]$MigrationsOnly)
$ErrorActionPreference='Stop'
$validationEnvironmentSnapshot=@{}
foreach($validationEnvironmentName in @('APP_KEY','APP_ENV','APP_DEBUG','SMS_DRIVER','CACHE_STORE','DB_URL','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','PGPASSWORD')){
 $validationEnvironmentSnapshot[$validationEnvironmentName]=[Environment]::GetEnvironmentVariable($validationEnvironmentName,'Process')
}

$projectRoot=Split-Path $PSScriptRoot -Parent
$pgBin='C:\Program Files\PostgreSQL\18\bin'
$pgData=Join-Path $projectRoot 'database\.validation-data'
$secretPath=Join-Path $projectRoot 'database\.env.validation'
$pgStarted=$false
$previousValidationAppKey=$env:APP_KEY
if(!(Test-Path -LiteralPath $secretPath)){
 $secret=[guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
 [System.IO.File]::WriteAllText($secretPath,$secret)
}else{$secret=[System.IO.File]::ReadAllText($secretPath)}
try {
 if(!(Test-Path -LiteralPath (Join-Path $pgData 'PG_VERSION'))){
  & "$pgBin\initdb.exe" -D $pgData -U infotech_validation --auth=scram-sha-256 --pwfile=$secretPath --encoding=UTF8 --locale=C
  if($LASTEXITCODE -ne 0){throw 'Test cluster initialization failed.'}
 }
 & "$pgBin\pg_ctl.exe" -D $pgData -l (Join-Path $projectRoot 'database\.validation.log') -o '-h 127.0.0.1 -p 55433' -w start
 if($LASTEXITCODE -ne 0){throw 'Test cluster startup failed.'}
 $pgStarted=$true
 $env:PGPASSWORD=$secret
 $exists=& "$pgBin\psql.exe" -h 127.0.0.1 -p 55433 -U infotech_validation -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='infotech_school_test'"
 if($exists -ne '1'){& "$pgBin\createdb.exe" -h 127.0.0.1 -p 55433 -U infotech_validation infotech_school_test}
 $env:DB_URL='';$env:DB_CONNECTION='pgsql';$env:DB_HOST='127.0.0.1';$env:DB_PORT='55433';$env:DB_DATABASE='infotech_school_test';$env:DB_USERNAME='infotech_validation';$env:DB_PASSWORD=$secret
 . (Join-Path $PSScriptRoot 'validation-key.ps1')
 Set-ValidationEncryptionKey
 Push-Location (Join-Path $projectRoot 'api')
 try {
  & php -d extension=pdo_pgsql -d extension=gd tests/Support/assert-validation-database.php
  if($LASTEXITCODE -ne 0){throw 'Refusing to migrate or test a non-validation database.'}
  if($MigrationsOnly){
   & php -d extension=pdo_pgsql -d extension=gd artisan migrate:fresh --seed --force
  }else{& php -d extension=pdo_pgsql -d extension=gd vendor/bin/phpunit -c phpunit.pgsql.xml}
  if($LASTEXITCODE -ne 0){throw 'Backend PostgreSQL validation failed.'}
 }finally{Pop-Location}
}finally{
 if($pgStarted){& "$pgBin\pg_ctl.exe" -D $pgData -m fast -w stop}
 Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
 Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue
 if($null -eq $previousValidationAppKey){Remove-Item Env:APP_KEY -ErrorAction SilentlyContinue}else{$env:APP_KEY=$previousValidationAppKey}
 foreach($validationEnvironmentName in $validationEnvironmentSnapshot.Keys){
 [Environment]::SetEnvironmentVariable($validationEnvironmentName,$validationEnvironmentSnapshot[$validationEnvironmentName],'Process')
}
}

$ErrorActionPreference='Stop'
$validationEnvironmentSnapshot=@{}
foreach($validationEnvironmentName in @('APP_KEY','APP_ENV','APP_DEBUG','SMS_DRIVER','CACHE_STORE','DB_URL','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','PGPASSWORD')){
 $validationEnvironmentSnapshot[$validationEnvironmentName]=[Environment]::GetEnvironmentVariable($validationEnvironmentName,'Process')
}

$projectRoot=Split-Path $PSScriptRoot -Parent
$env:DB_URL='';$env:APP_ENV='local';$env:SMS_DRIVER='local';$env:CACHE_STORE='file'
$env:DB_CONNECTION='pgsql';$env:DB_HOST='127.0.0.1';$env:DB_PORT='55433';$env:DB_DATABASE='infotech_school_test';$env:DB_USERNAME='infotech_validation';$env:DB_PASSWORD=[System.IO.File]::ReadAllText((Join-Path $projectRoot 'database\.env.validation'))
. (Join-Path $PSScriptRoot 'validation-key.ps1')
Set-ValidationEncryptionKey
Push-Location (Join-Path $projectRoot 'api')
try{& php -d extension=pdo_pgsql -d extension=gd artisan school:validation-inject-otp --adb=C:/Android/Sdk/platform-tools/adb.exe}finally{Pop-Location;Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue;foreach($validationEnvironmentName in $validationEnvironmentSnapshot.Keys){
 [Environment]::SetEnvironmentVariable($validationEnvironmentName,$validationEnvironmentSnapshot[$validationEnvironmentName],'Process')
}}

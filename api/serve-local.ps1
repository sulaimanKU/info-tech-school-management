param([string]$BindAddress='127.0.0.1',[ValidateRange(1024,65535)][int]$Port=8000)
$ErrorActionPreference='Stop'
$envFile=Join-Path $PSScriptRoot '.env'
if(!(Test-Path -LiteralPath $envFile)){throw 'Configure the existing local .env first.'}
$localConfig=Get-Content -LiteralPath $envFile | Where-Object {$_ -match '^APP_ENV\s*=\s*["\x27]?local["\x27]?\s*$'}
if(!$localConfig -or ($env:APP_ENV -and $env:APP_ENV -ne 'local')){throw 'This script is for APP_ENV=local only.'}
$previousSms=$env:SMS_DRIVER
try {
 # Explicit simulator opt-in for this local server process, never edits .env.
 $env:SMS_DRIVER='local'
 Write-Output 'Local encrypted SMS simulator enabled. Use school:local-otp with the request challenge ID; no real SMS is sent.'
 & (Join-Path (Split-Path $PSScriptRoot -Parent) 'deployment\serve-api.ps1') -BindAddress $BindAddress -Port $Port
}finally{$env:SMS_DRIVER=$previousSms}

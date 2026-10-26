function Set-ValidationEncryptionKey {
 if($env:DB_DATABASE -ne 'infotech_school_test' -or $env:DB_PORT -ne '55433'){throw 'Validation encryption key is restricted to the isolated test database.'}
 $validationKeyPath=Join-Path (Split-Path $PSScriptRoot -Parent) 'database\.env.validation-app-key'
 if(!(Test-Path -LiteralPath $validationKeyPath)){
  $validationKeyBytes=New-Object byte[] 32
  $validationKeyGenerator=[System.Security.Cryptography.RandomNumberGenerator]::Create()
  try{$validationKeyGenerator.GetBytes($validationKeyBytes)}finally{$validationKeyGenerator.Dispose()}
  [System.IO.File]::WriteAllText($validationKeyPath,('base64:'+[Convert]::ToBase64String($validationKeyBytes)))
 }
 $env:APP_KEY=[System.IO.File]::ReadAllText($validationKeyPath)
}

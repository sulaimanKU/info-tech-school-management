$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
$injector=Join-Path $PSScriptRoot 'inject-validation-otp.ps1'
$injectorOutput=Join-Path $env:TEMP 'infotech-validation-otp-output.log'
$injectorError=Join-Path $env:TEMP 'infotech-validation-otp-error.log'
$helper=Start-Process -FilePath powershell.exe -ArgumentList @('-NoProfile','-File',('"{0}"' -f $injector)) -WindowStyle Hidden -RedirectStandardOutput $injectorOutput -RedirectStandardError $injectorError -PassThru
# Native Gradle/JVM warnings use stderr; actual process exit codes determine failure.
$ErrorActionPreference='Continue'
Push-Location (Join-Path $projectRoot 'mobile')
try {
 try {
  & flutter drive --driver=test_driver/final_dashboard_driver.dart --target=integration_test/final_dashboard_workflows_test.dart -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
  if($LASTEXITCODE -ne 0){throw 'Six-role native workflow failed.'}
 }finally{if(!$helper.HasExited){Stop-Process -Id $helper.Id -ErrorAction SilentlyContinue}}
 & flutter drive --driver=test_driver/final_dashboard_driver.dart --target=integration_test/session_restart_test.dart -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=PREPARE_SESSION=true --keep-app-running
 if($LASTEXITCODE -ne 0){throw 'Native session persistence preparation failed.'}
 & 'C:\Android\Sdk\platform-tools\adb.exe' -s emulator-5554 shell am force-stop com.infoteach.school
 if($LASTEXITCODE -ne 0){throw 'Could not force-stop the validation app.'}
 & flutter drive --driver=test_driver/final_dashboard_driver.dart --target=integration_test/session_restart_test.dart -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
 if($LASTEXITCODE -ne 0){throw 'Native session restoration after process restart failed.'}
 Write-Output 'All three native validation scenarios passed.'
}finally{Pop-Location}

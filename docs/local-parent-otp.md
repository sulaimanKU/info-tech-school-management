# Local parent OTP workflow

This is an explicit encrypted simulator, not an SMS gateway and not an OTP bypass. Use only fictional development identities for testing. Real parent authentication still requires the actual verified guardian mobile, active account and normal OTP verification.

## Start the local API

From `E:\info-tech-school-management\api`:

```powershell
.\serve-local.ps1
```

Default: loopback port8000. The script requires APP_ENV=local and enables SMS_DRIVER=local only for its process; it never edits .env. It directly starts the worker with pdo_pgsql and GD. For a same-Wi-Fi phone, after checking the current interface address:

```powershell
.\serve-local.ps1 -BindAddress 192.168.100.107
```

Use the emulator API URL `http://10.0.2.2:8000/api/v1`. Keep the existing valid encryption key and PostgreSQL configuration. Do not run a second Artisan serve listener on the same port: on this machine its child drops parent -d extension flags.

## Obtain a local code

Request the code from Parent login, then run this in a **second local terminal** in the API directory:

```powershell
$env:SMS_DRIVER = 'local'
php -d extension=pdo_pgsql -d extension=gd artisan school:local-otp
Remove-Item Env:SMS_DRIVER
```

The command prints the latest pending eligible request's development OTP once. If several local requests are active, supply the specific nonsecret challenge UUID from the request response:

```powershell
$env:SMS_DRIVER = 'local'
php -d extension=pdo_pgsql -d extension=gd artisan school:local-otp <challenge-uuid>
Remove-Item Env:SMS_DRIVER
```

Enter the displayed value in the app; never paste it into a report/chat/log. Reading delivery consumes the local cache entry, not the authentication challenge: normal server verification is still required. Expiry300seconds, resend cooldown60seconds, five failed attempts, request/verification throttles and replay protection remain enforced. Unknown/inactive/unverified guardians receive the same neutral202 contract without an actual local delivery. Do not interpret neutral202 as proof of SMS delivery.

OTP cache values are encrypted using the application's existing key; challenge OTPs are hashed in PostgreSQL. No raw OTP enters API responses or application logs. Cache-write failure returns503 and rolls back challenge creation rather than reporting delivery success. No permanent/test OTP is accepted.

## Production

The local provider and CLI retrieval refuse production use. The default provider remains disabled; no real SMS delivery has been configured or certified. Implement/connect the actual provider through the existing SmsProvider contract, supply its real credentials and validate delivery/failure behavior before production. Keep production encryption keys stable and secret. Neither the simulator nor the debug HTTP server is a production deployment.

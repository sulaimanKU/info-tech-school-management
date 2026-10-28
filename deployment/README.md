# Deployment and validation boundary

The Android/backend implementation is validated locally; production deployment is not performed or claimed. Read [production configuration](../docs/production-configuration.md), [security review](../docs/security-review.md) and [final validation report](../docs/final-mobile-report.md).

The current MariaDB runner is `test-backend-mysql.ps1`; see [the database audit](../docs/database-compatibility-report.md) for its isolated server setup.

The legacy, explicitly invoked `test-backend.ps1` owns a separate PostgreSQL validation cluster on 127.0.0.1:55433, protects its local password, checks the resolved database identity and runs all PHPUnit suites with PostgreSQL/GD enabled. It stops the test server afterward. `-MigrationsOnly` resets only that isolated test database. Never point test tooling at normal data.

For emulator tests, first run `start-mobile-validation.ps1` in one terminal. It resets only the same isolated database, installs clearly fictional fixtures (the fixture command refuses every other database), then serves the test API on port 8001. In a second terminal run `inject-validation-otp.ps1`; the one-use local delivery is entered through ADB without printing its value. From mobile run:

```powershell
flutter test integration_test/mobile_workflows_test.dart -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8001/api/v1
```

The device test covers real local staff/parent authentication and representative workflows. Stop the fixture server before running the backend test suite. These tools share an isolated database and must not run simultaneously. After device testing, rebuild the normal APK with port 8000: integration testing overwrites the build output with a test entry point.

Production requires approved HTTPS/API hosting, protected application/CNIC keys, MariaDB/MySQL runtime privileges, backups/restore drills, private persistent storage, scheduler, real SMS/Firebase setup where enabled, school policy/contact approval and Android signing/Play configuration. No external message is sent by the disabled providers. No default production credentials are seeded, no keystore is invented, and the desktop/web portal is outside this task.


## Extension-correct local API and final validation

From the project root, use `powershell -File deployment/serve-api.ps1` for loopback. For the currently authorized physical phone interface use `powershell -File deployment/serve-api.ps1 -BindAddress 192.168.100.107` after checking the Wi-Fi IP. This runs the HTTP worker with GD and the installed PDO MySQL extension; PostgreSQL loading requires the explicit legacy `-DatabaseDriver pgsql` option; this machine's Artisan serve child drops parent -d extension flags. Do not expose the debug API indiscriminately.

`deployment/test-backend.ps1` uses the guarded isolated PostgreSQL test database. `validation-key.ps1` creates/loads a private gitignored test-only key and callers restore their environment afterward. Never substitute that key into the development `.env`; restore its original valid key from backup.

`deployment/validate-mobile-dashboard.ps1` orchestrates the fictional isolated six-role emulator case and two persistent-session stages. Keep the app installed between preparation and force-stop restoration. Final evidence: 118 backend tests/864 assertions,64 Flutter tests,clean analyze/Pint,3 functional native cases verified across runs. See docs/final-auth-dashboard-report.md and docs/final-rbac-report.md for limitations and exact APK URL/path.


## Current parent OTP / photos follow-up

Use `api/serve-local.ps1` for explicit local encrypted OTP simulation with extension-correct PHP. Read docs/local-parent-otp.md before local parent testing. docs/final-media-report.md records the current key integrity status, private photo model, exact tests and APK. Earlier invalid-key notes describe the earlier session. Local/phone debug transport is not production.

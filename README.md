# INFO-TECH School Management System — Android and API

Flutter Android application and Laravel/MariaDB backend for INFO-TECH High School & Educational Campus, Sangao Campus · Mardan. Phases 2–6 are implemented; final mobile validation is complete. No desktop/web management UI is included.

**Final checks:** 64 PostgreSQL tests / 402 assertions; 26 Flutter unit/widget tests; 1 native emulator integration case; clean Flutter analysis/format check; debug APK built/installed/launched. See [complete final report](docs/final-mobile-report.md) and [validation evidence/limits](docs/validation.md). Real SMS/Firebase/hardware/signing/deployment remain external configuration, not verified production integrations.

**MariaDB compatibility:** audited with PHP 8.2 and MariaDB 10.11 using `DB_CONNECTION=mysql`. See the [current database audit and test results](docs/database-compatibility-report.md); PostgreSQL counts below are historical validation evidence.

## Project layout

- `api/`: Laravel API, permission/object authorization, domain services, MariaDB/MySQL-compatible migrations and tests.
- `mobile/`: branded Flutter Android app (`com.infoteach.school`), role-aware features, English/Urdu RTL, light/dark/system appearance and secure sessions/drafts.
- `docs/`: [architecture/RBAC/offline](docs/mobile-architecture.md), [domain workflows](docs/domain-workflows.md), [database/ER](docs/database-schema.md), [APIs](docs/api.md), [imports/reports](docs/imports-and-documents.md), [production configuration](docs/production-configuration.md), [security review](docs/security-review.md), [source inventory](docs/file-inventory.md).
- `deployment/`: isolated PostgreSQL validation/emulator helpers and deployment guidance.

## Existing development installation

Keep the configured ignored `.env` and application/identity keys. Do not recreate the database or rerun key generation against existing protected data. All 13 migrations/reference seeders have been applied to the development database. Normal seeders create reference configuration, not fictional children or default credentials.

PHP 8.2+ requires PDO MySQL, GD, mbstring, OpenSSL, fileinfo, XML and ZIP for the implemented workflows. Use `DB_CONNECTION=mysql` with MariaDB 10.11. The local helper loads GD per process without changing global PHP files:

```powershell
cd E:\info-tech-school-management\api
.\serve-local.ps1
```

For a genuinely fresh checkout, install locked Composer dependencies, configure a private MariaDB/MySQL database and APP_KEY, run additive migrations/default reference seeders, then `php artisan school:seed-mobile`. Do not run `migrate:fresh` on normal development or production data. Production setup and secret/identity-key handling are documented in [production configuration](docs/production-configuration.md).

## Create the first Super Admin / Managing Director

From `api/`, run `php -d extension=gd artisan school:create-super-admin` in an interactive terminal. Enter your full name/email and a password twice at the hidden prompts. No default account or password is seeded. See [bootstrap instructions](docs/super-admin-bootstrap.md).

## Android

```powershell
cd E:\info-tech-school-management\mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Original official assets are `assets/1.jpeg` (logo) and `assets/2.jpeg` (campus). Preferences persist locally; Remember Me stores only the secure session token. Parent login needs a configured SMS adapter or the strictly local development simulator. No real SMS/Firebase credentials or production signing key are included.

Final debug APK: `mobile/build/app/outputs/flutter-apk/app-debug.apk`. It points to the local emulator host API and is not a production distribution build.

## Repeat validation

```powershell
cd E:\info-tech-school-management
.\deployment\test-backend-mysql.ps1
cd mobile
dart format --output=none --set-exit-if-changed .
flutter analyze
flutter test
flutter build apk --debug --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

The MySQL helper requires a disposable MariaDB 10.11 instance at `127.0.0.1:53317`, database `infotech_compatibility_test`, and verifies its identity before tests. See [database compatibility audit](docs/database-compatibility-report.md) for setup and results. The older `test-backend.ps1` remains an explicitly invoked PostgreSQL-only validation tool on port 55433; the application does not use it. Never run the emulator fixture server and destructive test runner against that isolated database simultaneously. Emulator reproduction instructions and fictional fixtures are described in the final report/production documentation. No automatic commit has been made.

## Final mobile UI/UX polish

See [the complete UI/UX report](docs/final-ui-report.md), [source audit](docs/final-ui-audit.md), and [reviewed emulator screenshots](docs/screenshots/final-ui/).

## Parent Experience completion

See [the validated Parent Experience report](docs/final-parent-report.md), [changed/created files](docs/final-parent-files.md), and [Android screenshots](docs/screenshots/final-parent/). The report separates emulator evidence from remaining phone and production checks.

## Staff Profile & Account

See [the staff account completion report](docs/final-staff-account-report.md) for the missing-employment-link diagnosis, safe account editing/password/photos, salary ownership, validation evidence and administrator linking steps. [Complete file manifest](docs/final-staff-account-files.md).

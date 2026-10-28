# Readable school data storage — 28 October 2026

Implemented locally. No deployment, commit, live database conversion, APP_KEY change, or production migration was performed. Existing unrelated workspace changes were preserved.

## Audit and resulting policy

Inspected schema/migrations, User casts/profile serialization, GuardianIdentity, guardian CRUD/search, parent authentication, import staging/dispatcher, report/export data, SMS provider binding, session issuance, Flutter guardian screens/privacy text and secure device storage. IDs/UUIDs, admission numbers, names, email, staff contacts, student addresses and other profile fields were already ordinary database values. No encrypted email column/cast or encrypted primary key was found. Email uniqueness, existing accounts and UUID relationships are unchanged. Reports do not decrypt guardian values or export raw guardian identity; imports use GuardianController and therefore inherit the new normalization, uniqueness and storage behavior.

| Previously protected field | Result |
| --- | --- |
| parent_guardians.cnic_ciphertext | Normalized, readable cnic CHAR(13), unique; leading zeros preserved |
| parent_guardians.mobile_ciphertext | Readable mobile VARCHAR(13), existing Pakistan mobile validation |
| parent_guardians.cnic_lookup / cnic_last4 | Obsolete guardian helper columns; removed only by validated finalization |
| import_batches.encrypted_rows | Readable source_rows LONGTEXT containing JSON; existing expiry, authorization, confirmation cleanup and preview masking retained |

Controllers, User profile serialization and parent CNIC queries no longer depend on legacy columns. Guardian API responses still explicitly select masked identity/contact representations. Unlinking, suspension and contact edits retain session/challenge/device revocation and audit behavior. Flutter JSON response shapes remain unchanged; its privacy wording now describes readable server records and masked app display.

Intentionally protected: User passwords retain Laravel's hashed cast and Hash::check; OTPs retain Hash::make/Hash::check, expiry, cooldown, attempt limits and consumption. Authentication session tokens remain SHA-256 digests. Device push tokens remain encrypted token_ciphertext; FCM access tokens remain encrypted in cache; service-account private keys and gateway credentials remain secrets. Local simulator OTP cache stays encrypted, short-lived and one-use, and is prohibited in production. Flutter secure-storage session tokens, per-account offline drafts and caches remain device protected. These are credential/device protections, not server profile-field hiding.

GuardianIdentity::lookup remains only for keyed OTP challenge correlation/locking and legacy digest validation, not guardian record lookup. Keep APP_KEY and any CNIC_LOOKUP_KEY unchanged through this procedure. APP_KEY is still needed for actual secrets after conversion.

## Encryption occurrence inventory

[Exact source occurrences with paths and line numbers](readable-data-encryption-scan.txt) cover app, config, schema, routes, tests and Flutter. [Whole-project references](readable-data-project-scan.txt) additionally include deployment/config documentation and historical audit statements. Generated scan files, dependencies, archives and Git internals are excluded. No encrypted Eloquent casts were found. Historical reports describe their original release, not the new policy.

Before this change, Crypt operations were present in:

- GuardianController: decrypt mobile for masking; encrypt mobile edits; encrypt CNIC/mobile on creation. Removed.
- User::profile: decrypt guardian mobile for masking. Removed.
- ParentAuthController: decrypt OTP destination mobile. Removed; provider now receives readable mobile.
- ImportController: encrypt uploads/reuploads and decrypt preview/validation rows. Removed; uses JSON source_rows.
- LocalSmsProvider: encrypt/decrypt temporary OTP delivery. Kept for secret protection.
- PushDeviceController: encrypt device token. Kept.
- PushDispatcher: decrypt device token for provider delivery. Kept.
- FcmPushProvider: encrypt/decrypt cached OAuth access token. Kept.

Legacy column definitions remain in historical create migrations to permit upgrades from existing schemas. New migration adds readable columns and makes legacy guardian columns nullable without deleting data. ConvertReadableData contains legacy decrypt operations, and conversion tests deliberately generate ciphertext. Push migration/test retain token_ciphertext. ImportPipelineTest and console expiry cleanup now use source_rows. App/session/database encryption config references remain framework/transport settings. Mobile secure storage and draft wording remain intentional device protection.

## Conversion guarantees and limits

`school:convert-readable-data` requires maintenance mode outside testing. All web, queue, scheduler, import and external database writers MUST be stopped. It decrypts using the application's configured existing encrypter, validates CNIC normalization, mobile format, uniqueness, old lookup digest/suffix, destination conflicts and staged JSON shape, writes in one transaction, and reads back values exactly. Guardian/user/link IDs and relationship rows are never rewritten. It preserves legacy values until explicit finalization. Exceptions print a generic failure without decrypted identities, keys, payloads or SQL bindings. A failed conversion rolls back data writes; it never requests a key rotation.

`--check` validates whether conversion is possible without writing, including conflicting destinations. It does not claim the destination was populated. Normal conversion is repeatable while legacy values match. `--drop-legacy` repeats validation/conversion, makes cnic/mobile NOT NULL, then removes ciphertext/helper columns and encrypted_rows. MySQL DDL implicitly commits, so removal runs after the conversion transaction. An interrupted DDL sequence can be resumed with the same command while all writers remain stopped. Never resume the application before finalization: otherwise retained legacy values could become stale after a contact edit or import cleanup.

The additive migration is deliberately irreversible through migrate:rollback: silently dropping readable columns would lose new data. Rollback means a coordinated database backup restore plus the old release and matching keys. For large databases, rehearse transaction size, lock duration, memory and DDL time on a restored copy before scheduling downtime. The command keeps a CNIC uniqueness set in memory and a single conversion transaction.

## Exact future production procedure (operator only)

1. Rehearse these steps on a restored production backup using a securely provisioned copy of the **same current APP_KEY** and any existing CNIC_LOOKUP_KEY. Verify backup restoration and record guardian/user/link/import counts privately. Keep backups/access restricted because readable fields will now be present.
2. Schedule downtime. On the old release run `php artisan down`. Stop queue workers, Horizon if used, scheduler/cron, import consumers and external writers. Disable any maintenance bypass traffic. Take a consistent database backup and a secure matching configuration/key backup; verify restore before proceeding.
3. Install the reviewed new release while maintenance stays enabled. Preserve APP_KEY, APP_PREVIOUS_KEYS if configured, CNIC_LOOKUP_KEY, database credentials, secret storage and SMS configuration exactly. **Do not run key:generate, migrate:fresh or migrate:rollback.** Run `php artisan config:clear` so the intended existing key/configuration is loaded. Use the normal production dependency installation process without scripts that generate keys.
4. From `api`, run `php artisan migrate --force`. This adds readable columns; existing guardian ciphertext and links remain intact. Do not serve application traffic yet.
5. Run `php artisan school:convert-readable-data --check`. Require exit code 0. If it fails, keep downtime enabled and investigate original keys, corrupted values, duplicate identities or destination conflicts privately. Do not delete or replace ciphertext to bypass validation.
6. Run `php artisan school:convert-readable-data`. Require exit code 0. Re-run `php artisan school:convert-readable-data --check`. Privately verify non-null readable guardian values, normalized identity/mobile samples, equal guardian/user/link counts and unchanged relationship IDs. Check active staged imports. No identity values should be pasted into logs/support channels.
7. With all writers still stopped and backup verified, run `php artisan school:convert-readable-data --drop-legacy`. Require exit code 0. Run `php artisan school:convert-readable-data --check` again. Verify legacy columns are gone, cnic is unique/NOT NULL, mobile is NOT NULL, and counts/links remain unchanged. On any interruption, keep maintenance enabled; retry finalization after resolving the cause, or restore the coordinated backup and old release.
8. Rebuild configuration with `php artisan config:cache`. In a controlled maintenance smoke test using the school's configured real SMS provider, request OTP for an authorized test parent, verify the correct verified mobile receives it, complete login, verify linked children and API masking, then test a guardian contact update, import and report. Never log the OTP. Preserve provider credentials and approval/template settings.
9. Resume workers/scheduler on the new release and run `php artisan up` only after all checks pass. Monitor redacted errors and authentication/delivery failures. After traffic resumes, rollback requires a new coordinated data recovery decision because new writes will exist.

SMS limitation: this checkout contains only DisabledSmsProvider and LocalSmsProvider, and AppServiceProvider binds only those implementations. There is no Spext adapter or configured real gateway implementation in this repository. The provider contract and correct mobile destination are tested, but real Spext network delivery cannot be certified here. A real production SMS adapter must be supplied and tested before step 8 can pass; this change does not invent gateway credentials or endpoints.

## Validation evidence

- Targeted MariaDB 10.11.14 suite: **38 tests, 259 assertions, all passed**, including ReadableDataTest, ParentOtpTest, StudentManagementTest, ImportPipelineTest, MySqlCompatibilityTest and PushNotificationTest.
- Standalone isolated MariaDB DDL check: conversion, real legacy-column removal, NOT NULL changes, read-back and repeated finalization/check invocation **passed**. Script: `api/tests/Support/validate-readable-data-finalization.php`; guarded by assert-validation-database.php and testing environment. Run it only after the normal suite because it intentionally finalizes the disposable schema. The next suite run recreates its isolated schema through RefreshDatabase.
- New tests cover readable CNIC/mobile; leading-zero/formatted CNIC login; provider destination; password/OTP hashing; relationships; API permissions/masking; dry-run and repeat conversion; corrupt ciphertext, duplicate decrypted identities, destination conflict and invalid-import transaction rollback.
- Full MariaDB suite: **165 tests, 1,389 assertions, 2 failures, 2 skips**. Failures are FinanceWorkflowTest::test_payroll_cash_retries_authorization_and_compensation_upsert (expected 200, received 422) and RbacScopeTest::test_administrator_manages_operations_but_cannot_escalate_or_change_privileged_users (payroll request expected 403, received 422). These finance paths are outside this storage change; the full suite is not green.
- Full SQLite suite: 165 tests, 1,324 assertions, 6 failures, 13 skips. In addition to the two finance expectations above, three dashboard tests and the finalized journal database-immutability test fail on this driver. Targeted initial parent/student/import run passed 20 tests, 156 assertions.
- Broad dirty-file Pint check finds formatting issues across existing modified files; new command, migration and test files are formatted separately. No Flutter runtime changes beyond privacy copy; no new APK or device SMS test was performed.

Tests used only in-memory SQLite or the explicitly verified disposable loopback MariaDB database infotech_compatibility_test on port 53317. The normal application's .env and APP_KEY were not changed.

## Files changed for this request

- api/app/Console/Commands/ConvertReadableData.php (new)
- api/database/migrations/2026_10_28_000016_add_readable_profile_storage.php (new)
- api/app/Services/GuardianIdentity.php
- api/app/Http/Controllers/GuardianController.php
- api/app/Http/Controllers/ParentAuthController.php
- api/app/Http/Controllers/ImportController.php
- api/app/Models/User.php
- api/routes/console.php
- api/tests/Feature/ReadableDataTest.php (new)
- api/tests/Feature/StudentManagementTest.php
- api/tests/Feature/ImportPipelineTest.php
- api/tests/Support/validate-readable-data-finalization.php (new)
- mobile/lib/l10n/strings.dart
- docs/database-schema.md, docs/mobile-architecture.md, docs/imports-and-documents.md, docs/rbac-matrix.md, docs/security-review.md, docs/implementation-status.md
- This report and both encryption scan artifacts

## Continuation supersedes the earlier test/SMS limitations

The payroll/RBAC failures and missing local Spext adapter reported above were resolved in the subsequent continuation. The full current results, new provider configuration, narrower fee-configuration permission and exact continuation file inventory are in [payroll-spext-continuation.md](payroll-spext-continuation.md). Earlier counts and limitations above are retained as historical evidence. No deployment, key change or production conversion occurred.

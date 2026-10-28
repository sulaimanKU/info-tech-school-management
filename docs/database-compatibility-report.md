# Laravel MariaDB/MySQL compatibility audit

Completed 27 October 2026 against the existing repository. The target is Laravel 12, PHP 8.2, MariaDB 10.11, and `DB_CONNECTION=mysql`.

The complete Unit/Feature suite ran on **MariaDB 10.11.14 with PDO MySQL and no PDO PostgreSQL extension**: **155 tests, 1,285 assertions, 0 failures, 0 errors, 2 skipped**. This is 153 passing tests and two deliberately PostgreSQL-specific tests. All 17 migrations passed in both directions. A populated-schema forward upgrade also passed without changing visible records, UUIDs, or financial amounts.

No production deployment, credentials, application/identity keys, Flutter files, routes, permissions, authorization rules, or SMS contract were changed by this audit. Changes already present in the working tree were preserved. In particular, the pre-existing short names in migrations 000002, 000008, and 000011 were retained.

## Changed files and behavior preservation

Paths below are relative to the repository root. Every file changed or added by this audit is listed; other dirty files shown by Git predate this work.

| File | Previous incompatibility | Replacement and behavior/security implications |
|---|---|---|
| `api/app/Support/Domain.php` | PDO MySQL query-builder boolean columns serialize as numeric 0/1, unlike PostgreSQL boolean values expected by Flutter. | Restore JSON booleans for the eight known schema boolean field names at the existing response boundary. Pagination, nulls, numeric money/counts, envelope/status and user-defined JSON documents retain their shapes/types. Eloquent user casts and direct auth/settings responses were already correct. No Flutter change is required. |
| `api/app/Services/AcademicFilters.php` | PostgreSQL `ilike` in student search. | Bound `like` searches under the MySQL Unicode collation; the same search fields, wildcards, and access restrictions remain. |
| `api/app/Http/Controllers/AnnouncementController.php` | `ilike` title search. | Bound `like`; audience and management checks unchanged. |
| `api/app/Http/Controllers/AssessmentController.php` | `ilike` assessment-title search. | Bound `like`; publication, teacher, and parent scopes unchanged. |
| `api/app/Http/Controllers/DiaryController.php` | `ilike` topic/homework search. | Bound `like`; draft visibility and child/teacher scopes unchanged. |
| `api/app/Http/Controllers/GuardianController.php` | `ilike` guardian-name search. | Bound `like`; encrypted CNIC/mobile handling, verified links, and masking unchanged. |
| `api/app/Http/Controllers/StaffDirectoryController.php` | `ilike` name/email search. | Bound `like`; privileged-account protections and role checks unchanged. |
| `api/app/Http/Controllers/TicketController.php` | `ilike` subject/number/student/guardian search. | Bound `like` in the existing scoped queries; internal-message and ticket access unchanged. |
| `api/app/Http/Controllers/DashboardController.php` | `COUNT(*) FILTER (WHERE ...)`. | `COALESCE(SUM(CASE WHEN ... THEN 1 ELSE 0 END),0)` preserves active counts, including empty sets. Existing finance aggregates remain MariaDB-compatible. |
| `api/app/Services/ParentChildren.php` | PostgreSQL `DISTINCT ON(student_id)`. | Order published results by student/date/publication time, then keep the first per student. An assessment-ID tie breaker makes exact timestamp ties deterministic. The returned fields and verified-child scope are unchanged; draft results remain excluded. |
| `api/app/Http/Controllers/ImportController.php` | Biometric `to_char`, `AT TIME ZONE`, and filtered COUNT/MIN/MAX. | Bound UTC month range, followed by PHP Karachi-day grouping and equivalent counts/first/last calculations. Existing response keys, review rules, and evidence-only attendance behavior remain. No MariaDB timezone tables are needed. |
| `api/app/Services/ImportDispatcher.php` | Biometric duplicate lookup compared local input with UTC stored values. | Convert once to UTC for both duplicate lookup and insert. Validation, imports' atomicity, and mapping/student authorization remain. |
| `api/app/Services/Finance/FinancialLock.php` | Guarded PostgreSQL advisory lock was a no-op on MySQL. | Keep the driver-guarded advisory lock; other drivers use a unique hashed key in `transaction_locks`, followed by `FOR UPDATE`. A transaction is mandatory. First-use inserts serialize too, and commit/rollback releases the lock. No identity, token, or request payload is stored in the lock row. |
| `api/app/Services/Finance/JournalService.php` | Posting-key lookup had no explicit request serialization. | Acquire the same transaction-safe lock by posting key before idempotency lookup. Balanced-line validation, atomic writes, posting hashes, reversals, numbers, and audits remain. Concurrent duplicate posts cannot bypass the existing payload comparison. |
| `api/app/Http/Controllers/ParentAuthController.php` | Identity serialization existed only on PostgreSQL. | Use `FinancialLock` for both known and unknown identities before cooldown/invalidation/challenge creation. Encryption, hashing, attempts, expiry, generic responses, and `SmsProvider` calls remain unchanged. |
| `api/app/Http/Controllers/PushDeviceController.php` | Unguarded `pg_advisory_xact_lock(hashtextextended(...))`. | Use the transaction lock by token hash; keep row locking, encrypted tokens, ownership transfer, session binding, and audit logging. |
| `api/config/database.php` | MySQL relied on server defaults for engine, isolation, and session timezone. | Both MySQL/MariaDB configurations specify InnoDB, READ COMMITTED, and fixed `+05:00`. Existing `utf8mb4`, Unicode collation, strict mode, SSL options, and environment credentials remain. READ COMMITTED makes balance/idempotency reads see commits after waiting on locks, matching PostgreSQL's usual statement visibility. |
| `api/database/migrations/2026_10_27_000014_add_mysql_integrity.php` | MySQL skipped important PostgreSQL checks, partial indexes, and integrity triggers; legacy MariaDB could implicitly update the first TIMESTAMP. | Add the lock table and MariaDB/MySQL equivalents described below. Repair unintended automatic updates only on the application's known expiry/verification/read/punch columns. Internal conditional-index columns are invisible, so `SELECT *` responses gain no fields. No data is deleted or renumbered. |
| `api/bootstrap/app.php` | API exception mapping recognized only PostgreSQL integrity codes. | Also recognize MySQL/MariaDB duplicate 1062 as 409, FK 1451/1452 and check/trigger 3819/4025/1644 as 422. Existing generic messages, envelope, headers, and secret-safe reporting remain. |
| `api/app/Console/Commands/CreateSuperAdmin.php` | Concurrent duplicate handling recognized only PostgreSQL 23505. | Also recognize MySQL 23000/1062; preserve rollback, existing role selection, hidden password prompts, and generic database failure output. |
| `api/.env.example` | Suggested PostgreSQL connection/port. | Suggest MySQL/3306 and explicit `utf8mb4` settings. No real environment file or credentials changed. |
| `api/tests/Feature/IntegrityTest.php` | Academic-date database check ran only on PostgreSQL. | Run it on MySQL/MariaDB too, where the additive migration installs the same check. SQLite still skips the unsupported migration check. |
| `api/tests/Feature/SecurityReviewTest.php` | `SET CONSTRAINTS ALL IMMEDIATE` executed unconditionally. | Guard this genuinely PostgreSQL-only deferred-trigger test. MariaDB journal validation is exercised by finance workflow tests; database-level immutability is covered separately. |
| `api/tests/Feature/ImportPipelineTest.php` | No regression for local midnight crossing the UTC date/month boundary or duplicate UTC lookup. | Add boundary/duplicate coverage through the existing import and report APIs; assert that no attendance is fabricated. |
| `api/tests/Feature/MySqlCompatibilityTest.php` | No dedicated MySQL integrity/isolation/concurrency/encoding coverage. | Ten tests cover API boolean representations, Unicode round trip, session timezone/isolation, conditional uniqueness, invisible columns, safe 409/422 responses, immutability, two-connection contention/rollback release, hashed lock keys, identifier lengths, and InnoDB engines. |
| `api/tests/Support/assert-validation-database.php` | Safety check required a PostgreSQL test database and `current_database()`. | Support the exact isolated MySQL database at loopback port 53317 using `database()`. Retain the explicit legacy PostgreSQL branch and reject cached configuration. No live database is authorized. |
| `api/tests/Support/diagnose-database-driver.php` | Diagnostic checked only PDO PostgreSQL. | Check the configured PDO driver, mapping Laravel `mariadb` to PDO `mysql`; keep generic error output. |
| `api/tests/Support/diagnose-login-operation.php` | Diagnostic reported only PostgreSQL driver availability. | Report configured-driver availability. It remains local-only and records only operation/table metadata, never credentials or SQL bindings. This is a diagnostic output key, not an application API contract. |
| `deployment/serve-api.ps1` | Every development HTTP worker forcibly loaded PDO PostgreSQL. | Default worker uses the installed PDO MySQL extension; PostgreSQL extension loading requires explicit `-DatabaseDriver pgsql`. Loopback, router, GD, and caller-controlled bind/port behavior remain. |
| `deployment/test-backend-mysql.ps1` | Existing validation helper required a PostgreSQL cluster. | Add a MySQL runner with a fixed disposable database/loopback port, configuration safety check, explicit GD loading, and environment restoration. It does not install a service or touch normal data. |
| `.gitignore` | Isolated audit database binaries/data/logs would otherwise appear as untracked source. | Ignore only `/.local-db-audit/`; no application behavior changes. |
| `README.md` | Current setup instructions required PostgreSQL. | Document MariaDB/PDO MySQL, current audit, and the isolated MySQL test runner; retain historical validation evidence and pre-existing user edits. |
| `deployment/README.md` | Production/server instructions required PostgreSQL. | Document the MySQL runner/default HTTP worker and explicitly distinguish opt-in legacy PostgreSQL fixture tooling. |
| `docs/production-configuration.md` | Production database/OTP storage instructions named PostgreSQL as required. | Document MariaDB 10.11/PDO MySQL and link deployment/integrity verification. Existing security/provider requirements remain. |
| `docs/super-admin-bootstrap.md` | Bootstrap example forcibly loaded PDO PostgreSQL. | Use the existing command with GD and installed PDO MySQL. Account creation rules unchanged. |
| `docs/database-schema.md` | Described only PostgreSQL's additional database rules. | Document the additive MariaDB equivalents and deferred-balance limitation. |
| `docs/database-compatibility-scan.txt` | No repository-wide audit inventory. | Record required keyword search results, raw SQL/lock locations, and audited file scope. Documentation only. |
| `docs/database-compatibility-report.md` | No complete MariaDB audit/handoff. | This file records implementation, verification, limitations, and production checks. |

## Migrations and data types

All migrations were inspected and executed on MariaDB 10.11 with `DB_CONNECTION=mysql`. Full rollback and reapplication passed:

| Migration | Verification |
|---|---|
| `0001_01_01_000000_create_users_table.php` | UUID users, strings, booleans, timestamps, unique email. |
| `0001_01_01_000001_create_cache_table.php` | String keys, text cache payloads, integer expirations/locks. |
| `0001_01_01_000002_create_jobs_table.php` | Framework queue IDs/counters, text payloads, failed-job timestamp. Domain IDs are unaffected. |
| `2026_10_21_000001_create_foundation.php` | UUID/FK chains, roles, settings JSON, session expiry; checks restored by additive migration. |
| `2026_10_21_000002_create_student_management.php` | Existing short identifiers preserved; UUID/FK consistency, encrypted text fields, enrollment/link/teacher/OTP rules reviewed. |
| `2026_10_21_000003_create_private_files.php` | UUID FKs, file metadata, sizes, unique paths, photo relationship rollback. |
| `2026_10_21_000004_create_academic_workflows.php` | JSON snapshots, dates, marks/percentages, statuses, all indexes/FKs; checks restored. |
| `2026_10_21_000005_create_financial_ledger.php` | Integer minor-unit money, nullable unique references, sequence row table, UUIDs, immutable financial records; equivalents restored. |
| `2026_10_21_000006_guard_financial_transitions.php` | Existing PostgreSQL-only functions remain driver-guarded; additive MySQL triggers implement the transition rules. |
| `2026_10_21_000007_create_school_communication.php` | Message/event immutability, status checks, targets, indexes, notifications and timestamps. |
| `2026_10_21_000008_create_import_pipeline.php` | Existing short unique names preserved; JSON reports/mappings, encrypted rows, UTC punch evidence. |
| `2026_10_21_000009_create_exam_schedule.php` | Date/time fields, UUID FKs, composite uniqueness and rollback. |
| `2026_10_21_000010_create_push_delivery.php` | UUID/session relationships, encrypted tokens, delivery deduplication and lease timestamps. |
| `2026_10_22_000011_refine_staff_scopes.php` | Existing short names and permission revocation logic retained; assignment dates and conditional uniqueness restored. |
| `2026_10_23_000012_add_guardian_profile_photos.php` | Nullable UUID FKs and circular photo relationship rollback. |
| `2026_10_26_000013_add_staff_account_fields.php` | Pre-existing untracked migration preserved; UUID/photo FKs and rollback verified. |
| `2026_10_27_000014_add_mysql_integrity.php` | New lock table, checks, invisible conditional keys, transition/immutable triggers, legacy timestamp repair; up/down verified. |

The resulting schema has **82 InnoDB tables**, **37 CHECK constraints including MariaDB JSON validity checks**, and **31 triggers**. The longest index name and constraint name are each **60 characters**, below the 64-character limit. No additional identifier shortening was needed beyond existing fixes.

UUID domain IDs remain UUID strings/CHAR(36) through Laravel's MySQL grammar. Every related FK uses compatible types. Booleans use Laravel's MySQL representation internally; the existing response boundary restores boolean JSON types for query-builder columns, and Eloquent's user boolean cast remains. User-defined JSON documents retain their original scalar types. JSON uses Laravel/MariaDB's supported JSON storage and validation; no application code needs PostgreSQL binary JSON operators. Text/ciphertext remains text. Monetary amounts remain integer minor units, never floating point. Academic decimal columns retain their precision/scale. Unsigned counters/sizes use supported MySQL types. Application status validation remains, with equivalent database checks. Multiple NULL values in nullable unique columns remain permitted, including cash references and unassigned receipts/reversals.

### Integrity equivalents

The new forward migration reinstates the rules skipped by old PostgreSQL guards:

- Academic/session/date ordering; student gender/status/birth dates; enrollment dates; attendance statuses; grade/mark/progress ranges; coordinator/teacher assignment dates; ticket statuses.
- Financial nonnegative/positive amounts, discount basis-point ranges, invoice arithmetic/dates, payroll arithmetic, and one-sided positive journal lines.
- Active student enrollment; active section roll; active primary guardian; active class teacher; active subject teacher; active coordinator assignment; one original admission invoice.
- Immutable journal entries/lines, invoices/lines/adjustments, payment/refund allocations, refunds, staff compensation, payroll adjustments/items, support messages/events.
- Verified payment immutability and immutable payment payload fields; expense request-before-payment and prior approval above PKR 500; payroll approval-before-disbursement and final-total immutability.

Partial uniqueness uses nullable generated columns plus ordinary unique indexes. Inactive rows yield NULL, so history remains possible. The original subject-teacher NULL-subject semantics remain. Columns are **INVISIBLE** and never appear in normal `SELECT *` API results. `RTRIM` on CHAR-derived expressions avoids MariaDB's SQL-mode-dependent padding restriction; it does not alter canonical UUID values. Existing invalid/duplicate data causes migration failure rather than silent deletion or weakened constraints.

Legacy `explicit_defaults_for_timestamp=OFF` can add `ON UPDATE CURRENT_TIMESTAMP` without Laravel requesting it. This was reproduced on the installed MariaDB 10.4 runtime and could change expiry/evidence fields during unrelated updates. The forward migration removes that unintended update behavior on known application columns while retaining a valid explicit default. It does not rewrite stored timestamps. Rollback deliberately does not reinstate this unsafe implicit behavior.

## Services, controllers, raw SQL, and remaining PostgreSQL constructs

The scan covers first-party `app`, `bootstrap`, `config`, `database`, `routes`, `tests`, `resources`, public entry points and project tooling/configuration. Vendor/generated storage/cache, archived packages and existing database binary files are not application source and were not modified. No first-party SQL dump/script files were found. Models, middleware, jobs/queue configuration, providers, commands, seeders, report/import services, and access/query builders were included. There are no separate application repository classes or custom job classes containing additional SQL.

All controller files were searched, including accounting, academic settings, announcements, assessments, attendance, auth, behavior, catalog, coordinator, dashboard, diary, exam schedules, expenses, fee configuration/fees, guardians, imports, notifications, parent auth/children/home, payments, payroll, private files, push devices, RBAC, reports, staff accounts/directory, and students. Their existing access checks and routes remain.

All service groups were reviewed: academic calendar/filter/summary, SchoolAccess/TicketAccess/AnnouncementAccess, StudentService, GuardianIdentity, SessionIssuer, ParentChildren/ParentAcademics, SpreadsheetReader/ImportDispatcher, ReportData/ReportRenderer, notifications/push/SMS, profile photos, and every finance service. Both models and all three reference seeders use compatible Laravel query/schema operations. No PostgreSQL connection is explicitly selected by application queries.

Raw expressions were checked individually. The remaining unguarded SQL comprises standard/MariaDB-supported sums/counts/CASE/COALESCE/GREATEST, fully grouped aggregates, constant SELECT/false predicates, bound balance subqueries, CASE priority ordering, row locks, and `FOR UPDATE SKIP LOCKED`. The latter was executed successfully by push tests on MariaDB 10.11; it is also available on MySQL 8. PostgreSQL-specific DDL is never sent to MariaDB. The new MySQL DDL, information-schema queries, NULL-safe `<=>` trigger comparisons, SIGNAL statements, and generated indexes are all driver-guarded and were executed successfully.

| Construct found | Handling |
|---|---|
| `ILIKE` | All application occurrences replaced with bound `LIKE`. |
| SQL `FILTER (WHERE ...)` | Dashboard uses SUM/CASE; biometric grouping/counts/min/max use PHP. Remaining `filter` words are PHP collection methods, route/test names, comments or CSS. |
| `DISTINCT ON` | Published parent results use ordered first-per-student selection. |
| `to_char`, `AT TIME ZONE` | Removed from biometric reporting; explicit Carbon UTC/Karachi conversion replaces them. |
| `pg_advisory_xact_lock` | Only remains in `FinancialLock`, inside `DB::getDriverName() === 'pgsql'`. MySQL uses transaction-owned row locks. |
| `hashtextextended` | Removed from push registration. No remaining source use. |
| PostgreSQL casts / `::` | The SQL cast `OLD.approved_at::date` remains only in guarded migration 000006. Other `::` matches are PHP static methods/constants. MariaDB equivalent uses `DATE(OLD.approved_at)`. |
| `jsonb` / `to_jsonb` / PostgreSQL arrays | Only the guarded payment trigger's old/new comparison in migration 000006 remains; MySQL compares immutable fields individually with NULL-safe equality. |
| Partial indexes | Guarded historical DDL retained; additive nullable/invisible generated unique indexes enforce the same rules on MariaDB. |
| PostgreSQL CHECK blocks | Guarded historical blocks retained; standard CHECK equivalents added for MariaDB. |
| PL/pgSQL functions, immutable/constraint triggers, `DEFERRABLE`, `SET CONSTRAINTS ALL IMMEDIATE` | Historical migrations retain explicit PostgreSQL guards. The deferred-balance test now skips on other drivers. MariaDB equivalents and the deferred-balance limit are documented here. |
| PostgreSQL 23505/23503/23514 error codes | Harmless compatibility recognition retained alongside equivalent MySQL codes; these are not SQL statements or runtime PostgreSQL dependencies. |
| `array_agg`, `string_agg`, `date_trunc`, `generate_series`, `RETURNING`, `ON CONFLICT`, SQL `ANY`/`ALL`, `EXTRACT`, SQL intervals, JSONB path/containment operators, PostgreSQL sequence functions | No application SQL occurrences found. `RETURN NEW/NULL` and `ARRAY[...]` occur only inside guarded PL/pgSQL migration bodies. `now()` matches are PHP/Carbon calls or ordinary supported schema defaults. `document_sequences`/`registration_sequences` are portable application tables, not PostgreSQL server sequences. |

Optional PostgreSQL configuration remains in `config/database.php`. `phpunit.pgsql.xml` and the explicitly invoked legacy PostgreSQL deployment/fixture helpers remain available to preserve existing validation workflows; the MySQL runner and normal API do not invoke them. The validation safety helper calls `current_database()` only in its explicit PostgreSQL branch. PDO diagnostics now inspect the configured driver. Historical documents/reports still record PostgreSQL test evidence, clearly separate from this current audit.

The detailed search/SQL inventory is [database-compatibility-scan.txt](database-compatibility-scan.txt): 155 first-party source files and 223 raw SQL/explicit-lock source lines. Reproduce the key application search from the root with:

```powershell
rg -n -i 'ILIKE|FILTER|DISTINCT ON|AT TIME ZONE|to_char\(|::|pg_|hashtextextended|jsonb|array_agg|string_agg|date_trunc|generate_series' api/app api/bootstrap api/database api/config api/routes api/tests
rg -n 'DB::raw|Raw\(|DB::statement|DB::select|DB::unprepared|->lock\(' api/app api/bootstrap api/database api/routes api/tests
```

## Finance and parent authentication

`FeeService`, `ExpenseService`, `PaymentService`, `PayrollService`, `InvoiceBalance`, `FinancialLock`, `DocumentNumber`, and `JournalService` were reviewed. Existing transactions and `FOR UPDATE` locks are retained. Invoice locks are acquired in sorted order; student locks serialize invoice-period checks; payment/payroll/expense rows serialize finalization/refunds/disbursements. Number tables retain unique keys, insert-if-absent, locked reads and increments inside the existing business transactions. The new request-key rows restore serialization for idempotent finance requests and payroll periods. Journal posting now also serializes by its posting key.

READ COMMITTED avoids using a repeatable-read snapshot established before an invoice/request lock wait for subsequent balance/idempotency queries. Monetary validation, exact allocation totals, overpayment/refund limits, prior approvals, reversal entries, payload hashes, and audit events remain. Existing finance workflow tests cover these rules. The new real two-connection test demonstrates that first-use lock-key insertion blocks another transaction and that rollback releases ownership. It is not a production load/deadlock benchmark. Genuine cross-workflow deadlocks remain possible under contention and produce transaction rollback; no lock or transaction was removed to hide them.

`ParentAuthController`, guardian lookup, `GuardianIdentity`, provider binding, rate limiters, verification/session issuance, SchoolAccess and parent-child queries were reviewed. CNIC lookup is still normalized/HMAC-based; CNIC/mobile values remain encrypted; API profiles remain masked. Six-digit random OTPs are hashed; failed attempts commit, expiry/cooldown/replay checks remain, verification locks the challenge, and eligible-user checks remain. The request lock covers unknown identities as well, preserving generic eligibility responses while restoring concurrent cooldown enforcement on MariaDB.

The checked-in repository contains **LocalSmsProvider and DisabledSmsProvider only**. Production requests remain unavailable unless a real adapter is provided through the existing `SmsProvider` binding. This audit does not claim or enable real SMS delivery. Existing production provider configuration must be checked on the deployed instance. No OTP, full CNIC, decrypted mobile, gateway key, or secret was logged or added to source by this work.

## Encoding and timezone findings

The configured MySQL/MariaDB charset was already `utf8mb4` with `utf8mb4_unicode_ci`; those correct defaults were preserved and made explicit in the example environment. All locally created tables use InnoDB and the configured Unicode schema; a database round trip of middle dot, Urdu, and a supplementary-plane school emoji passed.

Byte/codepoint inspection found correct UTF-8 middle dots (`U+00B7`, bytes `C2 B7`) in `ReportRenderer`, and no suspected `U+00C2`/`U+00C3`/replacement-character mojibake in first-party backend source. Windows PowerShell's default legacy decoding reproduced `Â·` when reading those correct files without UTF-8 decoding. This is evidence of a decoding failure mode, **not proof of the deployed UI's root cause**. No output characters were blindly replaced.

`SpreadsheetReader` validates UTF-8 input values and removes a CSV BOM; it does not run lossy/manual encoding conversions. Report output uses explicit UTF-8 escaping, JSON Unicode handling, CSV UTF-8 content type/BOM, and Unicode-aware PDF/spreadsheet libraries. No `utf8_encode`, `utf8_decode`, `iconv`, or `mb_convert_encoding` transformations were found in application source. Valid UTF-8 containing already double-encoded text cannot be distinguished automatically from intentional characters; existing production data requires inspection before any data repair.

The application stays in `Asia/Karachi`. MySQL sessions use fixed `+05:00`, so named MariaDB timezone tables are unnecessary. General timestamps keep the application's Karachi wall-time representation. Biometric imports explicitly produce UTC wall values, and reporting converts those values to Karachi days in PHP; the new midnight/month-boundary test verifies this contract. Existing timestamp data is not shifted by a data migration. Check old production records against the previously used session timezone before switching cached connection configuration.

## Tests and exact outcomes

| Check | Result |
|---|---|
| Full suite before final API-boolean regression: `php -d extension=gd api/vendor/phpunit/phpunit/phpunit -c api/phpunit.xml --log-junit .local-db-audit/mariadb-tests.xml`, with isolated MySQL environment | **154 tests, 1,269 assertions, 0 errors, 0 failures, 2 skipped; 22.691 seconds; exit 0.** |
| New MySQL compatibility test class alone, before adding boolean-response coverage | **9 tests, 24 assertions; exit 0.** |
| Final full suite through `powershell -NoProfile -File deployment/test-backend-mysql.ps1`, including boolean-response coverage | **155 tests, 1,285 assertions, 0 errors, 0 failures, 2 skipped; 37.447 seconds; exit 0.** |
| Full `migrate:reset --force` then `migrate --force`, only after isolated-database guard | **17 down + 17 up operations passed; exit 0.** |
| Roll back only new migration, populate old schema with fictional users/enrollment/admission invoice/reference seeds, apply forward migration | **PASS; visible records, UUIDs and financial amounts byte-for-byte unchanged.** |
| PHP lint of first-party app/bootstrap/config/database/routes/tests | **150 files, 0 syntax errors; both subsequently changed boolean-response files also re-linted without errors.** |
| PowerShell parser for both changed/new server/test scripts | **2 files passed.** |
| `git diff --check` | **Exit 0; no whitespace errors.** Git emitted existing line-ending normalization notices. |
| Schema metadata | **82 InnoDB tables; 0 other engines; maximum constraint/index lengths 60/60; 37 CHECKs; 31 triggers.** |

Skipped tests are `CreateSuperAdminCommandTest::test_assignment_failure_rolls_back_user_creation` (PostgreSQL-only transactional test trigger) and `SecurityReviewTest::test_postgresql_deferred_balance_trigger_rejects_direct_unbalanced_insert` (PostgreSQL deferred constraint trigger). No other tests were skipped in the final MariaDB run.

Intermediate diagnostic runs were not claimed as passes: the first SQLite run had **129 passed, 12 failed, 3 skipped / 1,135 assertions**, including missing GD and SQLite's missing GREATEST; an initial MySQL invocation used an empty environment value that allowed the existing local password to load and had **143 failed, 1 passed**; a stopped MariaDB 10.4 run reached **94 tests / 782 assertions, 1 error, 2 skipped** because 10.4 lacks SKIP LOCKED. A temporary connection-init attempt was rejected by the older server's read-only timestamp setting and was removed. Early 10.11 migration attempts exposed generated-CHAR padding restrictions and were corrected with stable RTRIM expressions before final passing verification. SQLite support and MariaDB 10.4 support are not claimed by this audit.

The test runtime was an official MariaDB 10.11.14 Windows ZIP in ignored `/.local-db-audit/`, using a loopback-only instance on port 53317. PHP 8.2.12/PHPUnit 11.5.57 were used, with GD loaded per invocation. The isolated server was shut down after verification. No global PHP files, Windows service, normal development database, or production database was changed.

## Limitations and production verification

1. **Deferred journal constraint:** MariaDB cannot reproduce PostgreSQL's commit-deferred cross-row trigger. `JournalService` validates complete entries before atomically writing them; all application journal inserts use this service. MySQL CHECKs validate line shape and triggers prohibit modifying/deleting finalized rows. Privileged direct SQL inserts outside the service do not receive PostgreSQL's deferred whole-entry balance guarantee. Keep journal writes within the service and restrict direct database access. This limitation is explicit; no unsafe immediate per-line balance trigger was substituted.
2. **Schema privileges and existing data:** Back up and test a production clone first. The deployment principal needs ALTER/INDEX/CREATE TRIGGER privileges. Existing duplicate active links/enrollments/admission invoices or invalid date/amount records must be reconciled before migration; nothing is silently discarded. MariaDB DDL implicitly commits, so a partially failed migration requires inspecting completed DDL before retrying. Do not run `migrate:fresh`, reset, or test helpers against production.
3. **Existing table engines/charset:** New tables explicitly use InnoDB/utf8mb4. Verify every existing production table/column uses the intended engine/charset/collation; changing connection settings does not convert existing tables. Do not blindly transcode data already affected by an older import/connection.
4. **Connection configuration:** Set `DB_CONNECTION=mysql`, correct private cPanel connection details, `DB_CHARSET=utf8mb4`, `DB_COLLATION=utf8mb4_unicode_ci`; ensure DB_URL does not override these unexpectedly. Keep APP_KEY/CNIC_LOOKUP_KEY unchanged. Apply reviewed migrations, then rebuild Laravel's configuration cache/restart persistent workers so timezone/isolation settings take effect.
5. **Time/encoding smoke checks:** Check OTP expiry/cooldown, session expiry/logout, imported punch days around midnight/month ends, and existing report dates. Compare an affected UI string's stored bytes with response bytes: a correct dot is `C2B7`, while an already double-encoded `Â·` is `C382C2B7`. Check response/content-type decoding and database connection character sets before repairing any records. This repository audit did not access the deployed HTTP responses or database, so the production encoding cause is still unconfirmed.
6. **Version/platform differences:** MariaDB 10.11 was tested; an independent Oracle MySQL server was not available. A MySQL deployment needs modern MySQL 8 with enforced CHECKs, SKIP LOCKED and invisible generated columns (8.0.23+ for invisibility), not MySQL 5.7. Default Unicode collations have case/accent comparison differences from PostgreSQL; confirm non-ASCII search and catalog uniqueness expectations. MySQL TIMESTAMP's narrower historical/future range remains; domain dates still use DATE. No IDs or application timestamp values were migrated to a new representation.
7. **Concurrency and operations:** Perform multi-worker payment/allocation/refund/invoice/payroll and OTP-resend smoke tests on cPanel. Existing row/transaction locks remain active. Persistent hashed request-lock rows grow with distinct successful request/identity/token keys; monitor size and clean only during a quiescent maintenance window, never delete lock rows while transactions are using them. Production deadlock/load behavior and backup/restore drills remain operational verification.
8. **Providers and deployment:** Verify the existing real `SmsProvider` adapter/binding on the deployment, private gateway credentials, shared throttling/cache behavior, scheduler, HTTPS, private storage, and push configuration. This task did not send messages, install provider credentials, publish, deploy, or commit.

### Reproduce the isolated MySQL tests

Start a disposable MariaDB 10.11 server bound only to `127.0.0.1:53317`, with its own data directory and empty local root password, and create **only** `infotech_compatibility_test` using utf8mb4. The downloaded audit runtime can be reused locally; do not use these empty-password settings outside that disposable loopback instance. Run:

```powershell
powershell -NoProfile -File deployment/test-backend-mysql.ps1
```

The runner supplies the exact isolated connection, checks resolved database identity and rejects cached configuration before PHPUnit's migration refresh. It restores all environment variables afterward. It intentionally refuses ordinary development/production databases.

Design references used during review: MariaDB's [transaction isolation documentation](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/set-commands/set-transaction) and official [generated column reference](https://github.com/mariadb-corporation/mariadb-docs/blob/main/server/reference/sql-statements/data-definition/create/generated-columns.md). Local execution on the target major/minor version provides the reported verification evidence.

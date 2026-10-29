# Admission and fee lifecycle completion

Completed locally on 28 October 2026. No deployment, commit, push, production database connection, production .env edit or APP_KEY change. No bank/payment gateway integration. Existing finance/payroll functionality remains covered by the complete test suites.

## 1–3. Root causes, existing functionality and missing connections

The project uses UUID table relationships and query-builder services rather than a separate Eloquent Student model. The traced path is migrations → StudentService/enrollment history → FeeService/InvoiceBalance/PaymentService → existing controllers/routes/permissions → AuthController API calls → Flutter forms/screens.

Already implemented: admission/enrollment transitions, human admission-number generation, effective-dated class schedules and student assignments, approved concessions, immutable vouchers/lines/adjustments, manual payment allocations, receipts/refunds, opening balances, scoped student finance, payroll instalments, reports and validated guardian/import conversion.

Missing: deterministic month enumeration, a controlled catch-up generator, monthly unbilled history, admission finance orchestration/idempotency, explicit opening-balance month coverage and operational unbilled dashboard warnings. Student finance showed the accounting truth—zero posted receivables—but did not make all missing historical months visible. Reading a schedule never constituted a posted charge or a payment.

## 4. Admission lifecycle

Admission still creates student/enrollment/transition records through StudentService. StudentController can now apply optional initial finance setup in the same transaction. A stable request UUID links to the resulting student through admission_requests. An identical retry returns the same student; a changed payload or different actor reusing that UUID is rejected. A failed charge/payment rolls back the admission, request record, vouchers and journals.

Normal older admission calls without finance remain compatible. Finance-bearing admission calls require a request UUID; the new Flutter form supplies it. Existing import admission calls remain student/enrollment creation, with the normal import batch idempotency and no surprise fee posting.

Enrollment must fit an existing academic session. A March admission must use an appropriate March session/section; the system does not silently extend an April-start school session or invent missing enrollment history.

## 5. Monthly billing lifecycle and rules

MonthlyBilling enumerates applicable months from the later of admission and enrollment start through the selected cutoff, capped at the current school month. Each month must intersect a real enrollment and academic session. End dates, withdrawal, transfer, class changes and gaps are respected. Inactive records with an undated/open-ended historical enrollment are not guessed into new charges.

The monthly policy is **full-month tuition without daily proration**. The price/concession basis is the first applicable day in the month: the latest of month start, admission, enrollment start and session start. A later effective rate normally applies to the next billing month; a new mid-month enrollment uses its actual effective start. The latest applicable enrollment/class in a transition month determines one monthly charge. Existing posted vouchers keep their original amounts and win over a later configuration/class change. Management should approve this explicit monthly policy before production catch-up.

Rates, assignments and highest-applicable concessions reuse FeeService. Approved concessions remain applicable to historical periods before their dated revocation; a later revocation does not erase an earlier eligible concession. Unapproved revoked requests are not treated as approved concessions. Missing historical schedules produce a configuration-required state and prevent a posting batch from partially committing. Current rates are not silently copied into every past month.

Controlled generation supports one student, class/session, current month only, or all missing applicable months. API requests default to preview. Posting requires an explicit action. The CLI can be scheduled later, but no billing cron/scheduler task was installed. A batch is limited to 500 students; use class/session scopes and rehearse large histories.

Student row locks serialize all creation paths. Existing voucher uniqueness plus a cross-session tuition-period check prevents a second monthly charge. Repeating generation returns zero new vouchers. Existing paid/adjusted vouchers, payments and journal history are not rewritten. Historical catch-up vouchers are posted with the actual generation date; no historical payment or backdated accounting event is fabricated.

## 6. Exact March–October example

The automated example uses admission 1 March 2026, an eligible session/enrollment, tuition PKR 1,000 and no concession:

| Period | Net fee | Allocated payment | Remaining |
| --- | ---: | ---: | ---: |
| March | 1,000 | 1,000 | 0 |
| April | 1,000 | 1,000 | 0 |
| May | 1,000 | 0 | 1,000 |
| June | 1,000 | 0 | 1,000 |
| July | 1,000 | 0 | 1,000 |
| August | 1,000 | 0 | 1,000 |
| September | 1,000 | 0 | 1,000 |
| October | 1,000 | 0 | 1,000 |
| **Total** | **8,000** | **2,000** | **6,000** |

Before posting: zero posted outstanding plus PKR 8,000 configured/unbilled, eight missing months. After generation and before collection: PKR 8,000 billed/outstanding. After allocating PKR 2,000 to March–April: PKR 6,000 outstanding, PKR 5,000 previous-period arrears and PKR 1,000 current October due. A further PKR 500 May payment produces PKR 5,500 outstanding; full subsequent settlement produces zero. Tests exercise the complete flow rather than hard-coding these values in Flutter.

## 7–8. Outstanding, posted and unbilled

```text
Chargeable posted fees = voucher gross - concessions + approved adjustments
Net paid               = posted payment allocations - refunds
Posted outstanding     = chargeable posted fees - net paid
Previous arrears       = posted outstanding on earlier fee periods
Unbilled tuition       = known applicable monthly quotes without vouchers/approved opening coverage
```

Unbilled configuration is never added to ledger receivables on a GET. Months with unknown rates are counted and labelled configuration-required; their unknown price is not invented. Dashboard and finance overview expose missing-month/student counts, configured unbilled amount and affected-student navigation separately from posted outstanding.

## 9. Admission charges, payments and opening balances

The form places student/enrollment data first. Optional finance fields distinguish agreed tuition override, configured admission fee, first-month tuition voucher, a described additional one-time fee, explicit opening debt and actual money received. There is no “fee submitted” field that falsely marks configuration as payment.

Existing fee kinds are tuition, admission, promotion, additional and opening balance. Registration/annual/exam/transport/other non-refundable one-time charges can use existing described additional lines; no mandatory new school fee categories were invented. Recurring transport or refundable security-deposit liability accounting was not present and is not falsely represented as ordinary fee revenue. Such arrangements require their own approved school/accounting configuration.

AdmissionFinance orchestrates existing FeeService and PaymentService; it does not implement a second payment/journal engine. Initial allocations settle opening/admission/additional charges before tuition within the applicable period. Actual collection requires finance.write. Cash has no bank-account/reference/gateway requirement. Other manual labels remain unchanged. Existing management-review methods retain pending status until approval; pending money is not counted as posted paid.

Opening balances are **not inferred** to cover arbitrary months. The new immutable opening_balance_coverage metadata links an existing opening voucher to an explicitly approved contiguous historical tuition range. It adds no charge or payment and does not edit that voucher. finance.approve and student scope are required. Coverage cannot overlap existing tuition or another opening coverage. Exact retries are idempotent; changed approved ranges are rejected.

Catch-up posting stops when a nonzero opening balance has no reviewed coverage. Management can review the opening voucher and record which months it already includes, including a genuinely pre-admission range if appropriate. Covered months are skipped by both catch-up and individual tuition creation. Their history points to the aggregate opening voucher and does not invent per-month amounts, paid amounts or allocations. The opening balance itself counts once. Incorrect/overlapping historical data needs reconciliation, not automatic deletion or waivers.

The existing policy refusing new collections against inactive/withdrawn student records remains intact. Their known historical billing intervals and posted debts remain readable; any change to that collection policy or enrollment status needs the school's authorized process.

## 10. Student identifiers

Internal students.id and foreign keys remain normal UUID values. A UUID is a readable identifier, not ciphertext and not a password/token hash. Registration numbers retain the existing locked per-year sequence and unique human format, e.g. fictional INF-SG-26-0001. Admission responses, student screens, receipts and reports use that business identifier. No foreign keys were replaced. Monetary integers are minor units: 100000 means PKR 1,000, not encoded/encrypted currency.

## 11–13. Complete database/encryption audit

Evidence:

- [Source occurrence inventory](admission-encryption-audit.txt): active application code, every migration, config, routes and Flutter; encryption/decryption, cipher, hash/password/token, OpenSSL/base64 and accessor/mutator patterns.
- [Every-column storage inventory](admission-storage-inventory.md): **86 tables / 716 pre-finalization columns**, including framework tables and explicit classifications.
- [Finalized schema metadata](admission-storage-schema.json): **86 tables / 711 columns**, captured after guarded conversion/finalization. Metadata only; no row values, keys or credentials.

Inspected model casts/accessors, service helpers and schema. No encrypted Eloquent profile cast, encrypted email, encrypted UUID/primary key or additional ordinary-data encryption was found. Operational student/admission/contact/fee/payment/payroll data is readable. Exact columns are individually enumerated in the inventory; the following are the intentional exceptions/classifications:

| Field/location | Reason / action |
| --- | --- |
| users.password | Laravel one-way password hash; never decryptable/plaintext |
| authentication_sessions.token_hash | One-way bearer-token digest |
| otp_challenges.otp_hash | Secure one-way OTP hash; expiry/attempt/consumption controls preserved |
| otp_challenges.lookup_hash | Keyed authentication correlation/cooldown digest, not guardian profile storage |
| push_devices.token_ciphertext | Actual notification delivery credential; encryption retained |
| push_devices.token_hash | Credential lookup/deduplication digest |
| cache.value, local-otp entries | Short-lived encrypted OTP delivery secret; local/testing-only, one-use consumption |
| cache.value, FCM access-token entries | Encrypted OAuth access secret; retained |
| Firebase signing key / SPEXT_API_KEY / APP_KEY | True credentials in private configuration/files, not ordinary profile columns; untouched |
| admission_requests, attendance_registers, diary_entries, fee_invoices, journal_entries, payments, payment_refunds, payroll_payments, support_messages: payload_hash | Integrity/idempotency digests; source operational fields remain readable |
| private_files.sha256 | File integrity digest; not encrypted identity |
| import_batches.fingerprint | Import deduplication digest; readable source_rows retained |
| transaction_locks.key | Stable coordination digest; not encrypted profile data |
| cache/queue framework payloads | Serialization/encoding is not by itself ciphertext; do not blindly decrypt framework data or protected cache secrets |

Legacy ordinary-data fields still defined by historical migrations are parent_guardians.cnic_ciphertext, mobile_ciphertext and import_batches.encrypted_rows. Guardian cnic_lookup and cnic_last4 are obsolete lookup/masking helpers. Existing migration 000016 and school:convert-readable-data already safely handle them. New writes/read paths use readable cnic, mobile and source_rows. The guarded finalization test removed all five obsolete columns with read-back validation, preserved relationships and succeeded on repeated invocation. The **only remaining ciphertext-named column in the finalized schema is push_devices.token_ciphertext**, which is a true credential.

Names, registration numbers, UUID references, CNIC/mobile/email/address, descriptions, notes, payment/payroll references, amounts and normal profile fields remain readable; no new *_ciphertext fields were introduced. Private-file UUID/path names, SHA-256 digests, transaction request hashes, framework serialization and actual legacy ciphertext can all look opaque for different reasons. There is no reason to rotate APP_KEY or “decrypt” a UUID.

This is a source/schema audit plus isolated conversion evidence, not an inspection of unseen production rows. Production may still show legacy ciphertext because its conversion/finalization has not been run. Preserve the original APP_KEY and any CNIC_LOOKUP_KEY until validated conversion, and retain APP_KEY afterward for actual secrets. No unsafe second conversion system was introduced.

## 14–15. Migration and commands

New additive migration: `2026_10_28_000018_add_admission_requests.php`. It creates admission_requests and opening_balance_coverage, with UUID/FK/uniqueness controls and MariaDB/MySQL range/immutable-coverage protection. Existing finance records/tables are not replaced. Its down method refuses to discard history; use a coordinated backup recovery, never a destructive production rollback.

New Artisan command:

```sh
php artisan school:generate-monthly-fees --actor=AUTHORIZED_STAFF_UUID --student=STUDENT_UUID --through=2026-10 --dry-run
php artisan school:generate-monthly-fees --actor=AUTHORIZED_STAFF_UUID --student=STUDENT_UUID --through=2026-10
```

Optional scope: --session=SESSION_UUID, --class=CLASS_UUID; --current-only limits to the cutoff month. Omitted --through uses the current application month. Actor is mandatory, active and permission/scope checked. Dry-run performs no posting. Repeated posting does not duplicate tuition. No production invocation or automatic schedule was installed.

## 16. API changes

- POST /api/v1/finance/monthly-fees: through month, optional student/session/class, current_only and preview. Preview is the default; explicit preview:false posts through the same service. Returns month rows, missing amounts and opening balances requiring review.
- POST /api/v1/finance/invoices/{invoice}/opening-coverage: explicit management approval of covered_from/covered_through (YYYY-MM).
- POST /api/v1/students: optional request_id and admission_finance fields described above; normal admission remains compatible. Returns UUID plus human registration_number.
- Existing student-finance, dashboard-summary and finance-summary responses now include monthly history/unbilled warnings and separate posted totals. Class-scoped generation bills only applicable months in that class, including historical class changes.
- Existing report route supports fee_statement, fee_balances and unbilled_fees through ReportData/ReportRenderer. Statement includes monthly billing, one-time/opening charges, concessions, previous/current dues and payment/receipt history. Reports omit internal UUID columns; full report lists are not truncated to the UI's first 30 students.
- New fee receipts cannot mix students. Historical shared receipts remain readable and child-scoped. The legacy document fixture now seeds that historical scenario without bypassing the new creation rule; all original privacy assertions remain.

Route count: **140**.

## 17–18. Flutter and RBAC

Affected admission, student finance, invoice, monthly generation, dashboard and finance overview screens retain INFO-TECH surfaces, theme colors and light/dark behavior. Student finance shows admission/enrollment/session identity, configured versus posted amounts, missing months, monthly statuses and authorized actions. Unknown/consolidated amounts are shown as unknown/aggregate rather than fake zero monthly payments. Receipt/payment entry remains the existing workflow. Cancellation feedback has English and Urdu strings.

Permissions remain separated: finance.read, finance.configure, finance.write, finance.approve, payroll.read, payroll.write, payroll.approve, accounting.read. No new default grant or silent revocation was needed. School Administrator can admit/configure approved tuition assignments but cannot post admission charges/payments by default. Accountant/Clerk can transact/bill within scope but cannot admit a student unless deliberately granted students.manage. Management approval is required for opening coverage. Parents can only read their verified children; teachers have no unrestricted finance/payroll access. Super Admin retains the existing server-side bypass.

If an admission officer must collect cash, a Super Admin must explicitly approve a role with students.manage and finance.write; configuration access alone does not confer payment authority. Existing production custom assignments are not migrated away.

## 19–20. Accounting protections and final validation

FeeService, InvoiceBalance, PaymentService, JournalService, existing row locks, posting keys, immutable voucher/ledger/payroll history and manual review/reversal rules remain the accounting infrastructure. Admission and bulk generation are transactional. Approved coverage is metadata only. No GET creates receivables, no configuration is marked paid and no historical cash receipt is invented.

- **Complete Laravel/MariaDB suite: 211 tests, 2,123 assertions, zero failures/errors, two intentional PostgreSQL-only skips.** [Exact output](admission-fee-lifecycle-mariadb-tests.txt).
- Complete Flutter suite: **120 passed**, zero failures.
- Flutter analyze: **No issues found**.
- Pint and Dart formatting run on affected files.
- Guarded standalone readable-data conversion/finalization and repeat invocation: **passed** on MariaDB, with read-back validation.
- New backend lifecycle module covers the March–October calculation, no pre-enrollment tuition, class/session/end-date boundaries, assignments/rates/concessions including dated revocation, partial/full collection, admission receipt/idempotency/rollback, bulk atomicity, reviewed opening coverage, report privacy, permissions, CLI preview and cross-student rejection. Existing password/OTP/token/readable-data/payroll/journal tests remain in the full suite.
- New Flutter tests cover preview-before-post, explicit confirmation, parent versus staff actions, dark narrow history and admission configuration without collection authority.

The two skips are the existing PostgreSQL-specific assignment-failure and deferred journal trigger tests; MariaDB compatibility tests pass. Tests used only the guarded disposable loopback database. No real SMS or payment-provider request was made. No release APK or physical-device/production smoke test is claimed.

## 21–23. Future cPanel release, conversion and one-time commands

**Operator only, after review; none of these commands were run on production.** Replace the API directory and use the site's configured PHP CLI. Preserve production .env, uploads, current APP_KEY/CNIC_LOOKUP_KEY and provider credentials. Verify a consistent database/configuration backup and restoration before changing release files. Stop cron/scheduler/workers and all writers during migration/conversion.

```sh
cd /home/YOUR_CPANEL_USER/PATH_TO/api
php -v
php artisan down
```

Install reviewed code without overwriting secrets/storage. Then:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:clear
php artisan migrate:status
php artisan migrate --force
```

Migration status/backup/key availability are the preflight checks. If the earlier readable migration was not installed, actual source validation must follow the additive migration because the converter validates the replacement columns. With all writers still stopped:

```sh
php artisan school:convert-readable-data --check
php artisan school:convert-readable-data
php artisan school:convert-readable-data --check
```

Privately verify readable values, normalization/uniqueness, read-back, guardian/user/link counts and imported source data. Only after backup/validation, optionally finalize obsolete columns:

```sh
php artisan school:convert-readable-data --drop-legacy
php artisan school:convert-readable-data --check
```

Do not resume a readable-data release with stale legacy fields after new writes. Require success at every step and follow the existing [detailed conversion procedure](readable-data-storage.md). Never change keys to bypass a failed decryption/lookup check.

If previous finance defaults/permission setup was never applied, these existing idempotent commands insert missing defaults without replacing method settings or silently revoking grants:

```sh
php artisan school:seed-finance-defaults
php artisan db:seed --class='Database\Seeders\MobilePermissionSeeder' --force
```

Do not run the broad legacy FinanceConfigurationSeeder/school:seed-mobile merely to fix defaults; those also supply sample session/rate configuration. This lifecycle release needs no extra automatic permission grant.

Review the school's real historical sessions, enrollments, effective rates, approved concessions and opening coverage. Use the UI preview or scoped CLI --dry-run, then explicitly approve posting. Example command templates (identifiers are normal internal UUIDs, not secrets):

```sh
php artisan school:generate-monthly-fees --actor=AUTHORIZED_STAFF_UUID --session=SESSION_UUID --class=CLASS_UUID --through=2026-10 --dry-run
# Run only after reviewing the preview and resolving all opening/rate warnings:
php artisan school:generate-monthly-fees --actor=AUTHORIZED_STAFF_UUID --session=SESSION_UUID --class=CLASS_UUID --through=2026-10
```

Use the actual cutoff, not this historical example blindly. Check invoice counts, posted versus unbilled totals and balanced journals. There is no automatic production backfill on deployment and no scheduled billing task was enabled.

```sh
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:cache
php artisan route:list
php artisan migrate:status
```

Perform controlled authorized smoke checks using approved records: admission retry, fee setup without payment, first/catch-up voucher preview, cash partial receipt, updated dues, parent/teacher denial, opening coverage and a balanced trial balance. Distribute the reviewed matching Flutter build using the school's existing process. Resume existing workers/scheduler controls, then:

```sh
php artisan queue:restart
php artisan up
```

On failure keep maintenance enabled. Do not run migrate:fresh, destructive rollback, key:generate, direct journal edits or financial-record deletion. Recovery uses the matching verified backup and release; new writes require reconciliation before any restore.

## 24. Remaining risks/manual checks

Historical data that was never recorded cannot be reconstructed: review rates, session gaps, class transitions, concession policy revisions and legacy duplicate vouchers. Pricing is non-prorated and cutoff-aware as documented; management must confirm it. Preview and generate without concurrent configuration edits, and rehearse large class/history batches. No production timing benchmark is claimed.

Opening coverage is a reviewed immutable contiguous range, not an automatic debt write-off. Incorrect coverage, existing tuition/opening overlap, or missing withdrawal dates needs authorized reconciliation. Refundable security deposits or recurring non-tuition heads must not be disguised as ordinary fee revenue. Existing inactive-student collection restrictions remain. Newly mixed-student receipts are rejected while historical shared-document privacy is preserved.

Production contents/conversion state were intentionally not accessed. No unexpected test failure remains. The complete created/modified file list is [admission-fee-lifecycle-files.md](admission-fee-lifecycle-files.md).

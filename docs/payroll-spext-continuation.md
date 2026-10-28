# Payroll/RBAC fixes and local Spext restoration

Completed locally on 28 October 2026. No deployment or commit. No production connection, production .env edit, credential change, APP_KEY change, or production readable-data conversion. Pre-existing workspace edits were preserved.

## 1. Exact reproduced failures and root causes

The two original tests were rerun against the guarded, disposable MariaDB 10.11.14 database `infotech_compatibility_test` at `127.0.0.1:53317`:

```text
Tests: 2, Assertions: 28, Failures: 2.

FinanceWorkflowTest::test_payroll_cash_retries_authorization_and_compensation_upsert
Expected response status code [200] but received 422.
api/tests/Feature/FinanceWorkflowTest.php:182

RbacScopeTest::test_administrator_manages_operations_but_cannot_escalate_or_change_privileged_users
Expected response status code [403] but received 422.
api/tests/Feature/RbacScopeTest.php:55
```

The payroll test had already successfully generated, approved and paid payroll, retried payment, checked the payment details and verified only one disbursement journal. Its failure was the final `GET /finance/trial-balance` request: AccountingController required `from` and `to`, while this valid caller requested the complete ledger without dates. The request failed validation before any ledger aggregation. This was not a payroll payment, journal imbalance or MariaDB arithmetic failure.

The administrator test failed because MobilePermissionSeeder had added `finance.write` and `finance.approve` to the default School Administrator role, contradicting the RBAC matrix and test. The payroll request consequently passed authorization and reached business validation, returning 422 instead of 403. Merely changing a validation status would have concealed the privilege problem.

## 2. Implementation fixes

- Trial balance now aggregates all posted journal dates when both date parameters are omitted. Supplying a range still requires both valid dates in order. New tests verify nonzero totals across two years, correct range filtering, invalid/partial/reversed-range rejection, and unauthorized denial.
- Default administrators no longer receive finance transaction-write or approval grants. A separate `finance.configure` permission lets administrators configure fee schedules, fee assignments, discount policies and payment methods without authorizing payroll generation/payment, compensation, payroll adjustments, expenses, payment posting or financial approvals. Existing explicit `finance.approve` grants continue to authorize configuration for compatibility.
- The Flutter configuration screen follows the same split: configuration actions can use `finance.configure`; compensation and payroll adjustments remain visible only with `finance.approve`. Three new widget tests verify the configuration-only, approval and read-only profiles.
- New backend tests verify administrators' denied financial writes and approvals leave payroll status and journals unchanged, while accountants retain transaction-write access and cannot configure fees by default.

No existing valid FinanceWorkflowTest or RbacScopeTest assertion was weakened, removed or changed. JournalService, PayrollService, financial locking, idempotency keys, accounting lines, payroll immutability, database constraints, triggers and existing approval requirements were not modified in this continuation.

Seeder scope: the seeder adds default grants; it deliberately does not delete custom database assignments. Existing database grants remain authoritative. Before any future release, review administrators that may have received broad grants from the older seeder and explicitly reconcile those assignments through the established RBAC process. No production grants were inspected or modified here.

## 3. Spext audit and implementation

Audited SmsProvider, LocalSmsProvider, DisabledSmsProvider, AppServiceProvider, config/school.php and the parent OTP request/verification flow. Neither the checkout nor the two local API archives contained a SpextSmsProvider. A local adapter was implemented using the [official VeevoTech SMS request/response contract](https://www.veevotech.com/api-docs/sms), without retrieving or guessing production credentials.

`App\Services\Sms\SpextSmsProvider` implements the existing SmsProvider interface. AppServiceProvider selects it for `config('school.sms_driver') === 'spext'`. LocalSmsProvider still requires local/testing, and disabled/unknown drivers still resolve the disabled fallback.

Configuration comes exclusively through Laravel configuration backed by:

```dotenv
SMS_DRIVER=spext
SPEXT_API_URL=
SPEXT_API_KEY=
```

The example file retains `SMS_DRIVER=disabled` and blank Spext values. The operator supplies the already approved full HTTPS sending endpoint and private key externally. The provider does not append guessed paths, embed a real endpoint/key, read production configuration, or use a query-string credential.

The adapter sends a JSON POST with the documented `apikey`, `receivernum` and `textmessage` fields. It passes the readable guardian mobile unchanged, sends the existing application's generated OTP, and accepts only HTTP success with the documented gateway success status and no reported error code. It has bounded connection/request timeouts, disables redirects, and does not automatically retry a potentially accepted SMS. Missing/invalid configuration, gateway rejection, invalid JSON, HTTP errors and transport failures return a generic 503. Gateway response bodies and transport exception details are neither returned nor logged, and transport exceptions are not chained into the public error.

The existing parent architecture remains unchanged: normalized readable CNIC lookup, verified guardian mobile, active-account check, hashed OTP challenges, cooldown, expiry, attempt limits, one-use verification and session issuance. Delivery failure rolls back the new challenge and any attempted consumption of the previous challenge. Passwords retain Laravel hashing. The conversion-compatible test starts with encrypted legacy guardian values, converts them, checks the exact fake SMS destination, and completes parent login using the hashed challenge.

## 4. Final validation results

| Check | Result |
| --- | --- |
| Original two failing tests after fix | 2 tests, 29 assertions, passed |
| FinanceWorkflowTest alone | **15 tests, 123 assertions, passed** |
| FinanceWorkflowTest + RbacScopeTest + AccountingAccessTest | **37 tests, 301 assertions, passed** |
| Finance/RBAC/accounting + parent/Spext/readable-data subset | **70 tests, 704 assertions, passed** |
| SpextSmsTest alone | **19 tests, 297 assertions, passed** |
| ParentOtpTest + SpextSmsTest + ReadableDataTest | **33 tests, 403 assertions, passed** |
| ReadableDataTest alone | **5 tests, 45 assertions, passed** |
| Standalone readable-data conversion/finalization DDL check | Passed on isolated MariaDB, including repeated finalization and validation |
| Complete Laravel MariaDB suite after all backend changes | **188 tests, 1,724 assertions, zero failures/errors, 2 expected skips** |
| php artisan route:list | Passed; **135 routes**, also confirmed by --json |
| Flutter analyze --no-pub | **No issues found**, exit 0 |
| Relevant Flutter tests | **59 passed**, zero failures |
| Pint on all eight changed/new PHP files | Passed |

The [complete MariaDB output](payroll-spext-mariadb-tests.txt) records the final full-suite result. The two skips are explicitly PostgreSQL-only: CreateSuperAdminCommandTest's assignment-failure trigger test and SecurityReviewTest's deferred journal-balance trigger test. MariaDB compatibility, journal integrity and workflow tests passed.

Spext tests call `Http::preventStrayRequests()` and use only fake HTTP responses with an explicitly fictional credential and reserved `.invalid` endpoint. They cover production driver selection, local production prohibition, fallback selection, expected JSON shape, success, correct converted mobile, hashed OTP/login, hashed password, replay rejection, unknown/inactive parents, HTTP 401/429/500, redirects, gateway rejection despite HTTP 200, malformed response, transport failure, no retry, invalid configuration and failed-resend rollback. Log spies verify no application logging on tested success/failure paths; exact generic error assertions prevent gateway data from appearing in the API response.

**No real SMS or Spext sending API request occurred during any test.** No real key was supplied, printed, logged or written to source. Documentation browsing was limited to public vendor documentation, not the sending API. Real production delivery and account-specific sender/template behavior were not exercised.

Flutter command startup initially stalled in the restricted sandbox. The invocation was stopped, then analysis/tests completed successfully with SDK-cache access. There is no remaining Flutter analysis warning. Tests used:

```text
test/finance_configuration_permissions_test.dart
test/finance_mobile_test.dart
test/rbac_filters_test.dart
test/parent_experience_test.dart
test/media_otp_test.dart
test/staff_account_test.dart
```

The MariaDB server used for validation was a disposable loopback instance with its existing ignored audit data directory. It was shut down afterward. No normal application database was migrated or finalized.

## 5. Remaining limitations

Zero unexpected backend or selected Flutter failures remain. The two intentional PostgreSQL skips remain. The implementation matches the public Spext contract and fake gateway responses; testing the actual production endpoint, credentials, sender/template approval and delivery is intentionally outside this local, no-deployment task. Existing administrator grant assignments require operator review before any future release as described above.

The earlier readable-data report's missing-Spext and two-failure statements describe the previous pass and are superseded by this continuation. Its conversion production procedure still applies; no production conversion was performed.

## 6. Complete continuation source/document file inventory

Modified:

1. `api/.env.example` — blank Spext settings only; retained safe disabled driver.
2. `api/app/Http/Controllers/AccountingController.php` — complete-ledger fallback and optional validated date range.
3. `api/app/Http/Controllers/FeeConfigurationController.php` — narrow configuration authorization.
4. `api/app/Providers/AppServiceProvider.php` — Spext driver selection.
5. `api/config/school.php` — environment-backed Spext configuration.
6. `api/database/seeders/MobilePermissionSeeder.php` — corrected default administrator grants and new configuration grant.
7. `mobile/lib/features/finance/finance_configuration_screen.dart` — configuration/approval action separation.
8. `docs/rbac-matrix.md` — updated permission contract and existing-grant review note.
9. `docs/production-configuration.md` — documented implemented Spext adapter and private configuration.
10. `docs/readable-data-storage.md` — linked this superseding continuation report.

Created:

11. `api/app/Services/Sms/SpextSmsProvider.php`.
12. `api/tests/Feature/AccountingAccessTest.php`.
13. `api/tests/Feature/SpextSmsTest.php`.
14. `mobile/test/finance_configuration_permissions_test.dart`.
15. `docs/payroll-spext-continuation.md` — this report.
16. `docs/payroll-spext-mariadb-tests.txt` — final full-suite output.

Ignored validation outputs were also generated under `.local-db-audit/` (continuation server stdout/stderr logs and database runtime files), plus normal Flutter/PHPUnit build/test caches. Those are not application source changes. No other existing source changes were reverted or included as new continuation work.

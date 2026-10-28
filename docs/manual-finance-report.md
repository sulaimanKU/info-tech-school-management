# Manual finance completion — 28 October 2026

Completed locally for review. No deployment, commit, push, production database connection, production .env edit, APP_KEY change or payment-provider integration occurred. Existing workspace changes were preserved. The complete change inventory is in [manual-finance-files.md](manual-finance-files.md).

## Root causes

1. Payment-method choices came only from active database records. Missing seeding produced an empty API list; ManualPaymentMethodSeeder silently skipped defaults when their ledger accounts did not exist. No production database was accessed, so production's precise seed state is not claimed. The empty-list condition was reproduced and tested locally.
2. The old payment form created an amount field only for positive-balance vouchers. A student with configured tuition but no voucher therefore received a form containing only method/date/notes. It could not create a valid allocation.
3. A fee schedule is configuration, not a posted receivable. The student API summed posted vouchers correctly but gave the UI no explanation, no current tuition quote, and no direct student-specific voucher action. Configuration and accounting totals appeared inconsistent.
4. Bank transfer/cheque defaults required a verification flag. This was already an internal approval mechanism, not a bank API, but the wording implied external verification. Cash references were discarded even though the user can legitimately supply one.
5. Payroll recorded one whole-period payment, with no separate instalment or employee allocation history. Its paid-state trigger correctly made finalized periods immutable, so partial payments needed additive history rather than rewriting salary snapshots or weakening that trigger.
6. Payroll and ledger access shared broad finance grants. A fee-viewing administrator could read salary details and journals without a separate authorization decision.

## Payment methods and default data

`school:seed-finance-defaults` inserts missing ledger accounts and these six manual method labels: Cash, Bank Transfer, Cheque, Easypaisa, JazzCash and Other. Easypaisa and JazzCash remain separate labels. New defaults do not require a reference, bank account input, proof attachment, external verification or gateway. The existing ledger asset account is bookkeeping configuration, not a bank integration.

FinanceAccountSeeder extracts the existing chart-of-accounts defaults for reuse. FinanceConfigurationSeeder still uses those same accounts; a second accounting architecture was not introduced. ManualPaymentMethodSeeder now ensures accounts exist rather than silently doing nothing. Repeated seeding preserves existing UUIDs, custom names, ledger mappings, active flags, review flags, operational records and fee rates.

The new command deliberately does **not** seed example class rates, sessions, students, vouchers, payments, salaries or permissions. Do not use the legacy broad school:seed-mobile command as an indiscriminate production finance bootstrap.

The existing `requires_verification` database/API field remains for compatibility and genuine **manual management review**. New methods default false. Existing true flags and pending payments are preserved. The administration screen now explains the manual workflow and supports editing names, active state and review settings. Cash cannot be configured to require verification. Ledger/account mappings and method codes cannot be changed through this edit endpoint, preventing historical refunds/postings from silently moving accounts.

An operator can explicitly turn off manual review for an existing bank/cheque label if the school wants immediate staff posting. That affects future entries only; existing pending payments still need their existing management approval/cancellation action. No bulk SQL change or automatic approval is performed. Expense disbursements also no longer demand a verified bank reference.

## Fees, calculations and vouchers

The accounting source of truth remains immutable posted vouchers, adjustments, verified/manual-posted payment allocations and refunds:

```text
Posted chargeable fees = voucher gross fees - voucher concessions + approved adjustments
Net paid              = posted allocations - refunded allocations
Outstanding           = posted chargeable fees - net paid
Previous dues         = outstanding on vouchers before the selected comparison month
```

Configured fees are not added a second time to voucher totals. A new StudentFinance read model reuses InvoiceBalance and FeeService. FeeService now shares its schedule/assignment selection and highest-applicable-discount calculation with the current tuition quote, so Flutter does not invent fee numbers.

The student response separates configured gross/concession/net tuition, unbilled tuition, posted gross/concessions/net fees, current-period posted fee/due, previous-period outstanding, net paid and outstanding. Current tuition honors the student's effective assignment override. Current class/session and latest effective schedules are resolved from enrollment and dates. Default student totals retain previous-session dues; an explicit session filter narrows invoices and payment history to that session.

When PKR 1,000 is configured but no voucher exists, the screen explicitly says the tuition is **configured but not billed**, displays that amount and offers Generate voucher. Posted dues remain zero because no receivable has yet been posted. Record payment is disabled until a positive posted due exists. It never silently creates a voucher during a GET request. Configuration changes do not rewrite existing vouchers.

This domain allocates payments only to posted dues and rejects overpayments. No advance-deposit model existed, so an unsupported advance balance or fictitious credit account was not added. The response can represent an existing negative net balance as credit; ordinary payments cannot create one by overpaying. Opening-balance vouchers remain the existing supported way to enter historical dues, within a valid enrollment/session period.

## Manual payment and history workflow

The flow is configuration → student assignment/override where needed → voucher → manual payment → allocation → receipt/history → remaining balance.

Record payment now shows the student and admission context, outstanding before payment, an explicit Amount received field, active method, date, optional reference and optional notes. Amounts are parsed into integer minor units. The entered amount is allocated to the oldest due vouchers first and cannot exceed their total. A stable request UUID is created once per open form, and backend payload matching prevents changed retries from creating another transaction. Existing invoice locks, payment/reference locks, active-method/asset checks, student authorization, allocation validation and balanced JournalService posting remain in use.

Cash has no bank/reference/proof requirement and optional references are preserved. New manual bank transfer, cheque and wallet entries post without a network call. An intentionally configured management-review method retains the existing pending/approval workflow. Internal status `verified` means posted/accepted by authorized school staff, not verification by a bank.

Successful entry opens the existing payment detail/receipt screen and refreshes the student balance after returning. No-dues and no-active-method states explain the required next action instead of opening an incomplete or empty form. Shared form errors now display relevant server messages for allocation/configuration failures even when the error key is not a direct input field.

Payment lists include method, receipt/date/amount, optional reference/notes, recorder and status, ordered newest payment date first. Student history groups allocations belonging to the same payment and scopes the amount to the selected student/session; refunds remain visible through existing detail history and affect net balances. Parent views retain their scoped receipt access and do not receive internal staff notes/recorder identity from this student API.

## Payroll

Migration `2026_10_28_000017_add_payroll_payment_history.php` adds only:

- `payroll_payments`: period, manual method, recorder, date, positive amount, optional reference/notes, request UUID and payload digest.
- `payroll_payment_allocations`: immutable positive amounts linked to the existing payroll items.

Both tables have foreign keys, amount checks on MariaDB/MySQL and append-only update/delete protection. Existing periods, items, salary settings, amounts, journal entries and transition triggers are not rewritten or dropped. The migration's down operation refuses to discard payroll history; rollback requires a coordinated backup restore.

Salary setup and existing adjustments feed monthly generation. Management approval still accrues the full salary expense against payroll payable once. Authorized manual salary payments can settle one employee or the remaining period balance. Each instalment debits payroll payable and credits the chosen asset through the same JournalService. Period locking serializes concurrent payments; request UUID/payload checks make retries idempotent; cross-period employee allocations and excessive payments are rejected.

Partial payments leave the period's workflow status **approved** and expose a separate `payment_status=partially_paid`. The existing approved→paid transition happens only when the remaining total reaches zero. This preserves the original paid-state guard. Staff items and salary adjustments remain immutable. Whole-period calls from older clients remain compatible; new partial-payment calls require a request UUID. Existing posting keys are preserved for legacy full-period calls.

The payroll list/detail show net salary, paid and remaining amounts, employee base/adjustments/net/due, status and manual payment history with date/method/reference/notes/recorder. Staff self-service shows only their own approved/paid salary items, instalments and remaining amount. Salary slips use the existing report renderer; unpaid items cannot produce a paid slip. Historical paid periods lacking the new history rows still read as fully paid, without backfill, invented payment events or journal reposting.

## Finance overview and filters

The Finance overview screen uses a new server summary over the same InvoiceBalance data: chargeable fees, concessions, net collected, outstanding, previous dues, unpaid/partially paid student counts, student balances and recent payments. Approved-payroll totals/paid/outstanding appear only with payroll permission. Student balances link to the existing student finance workflow.

Session/class and voucher issue-date filters intersect SchoolAccess and invoice scope. The period field selects the cutoff for previous dues and, when supplied, the payroll month. Receipt/collection totals are allocated to the selected vouchers; the date filter is explicitly labelled as **voucher issue date**, not a cash-movement date report. Existing payment history separately supports payment-date filtering. Parent, unauthorized staff and out-of-scope student access remain restricted. The application represents one school database; no unsupported multi-tenant school identifier was invented.

## API and RBAC changes

Added routes:

| Route | Purpose |
| --- | --- |
| GET /api/v1/finance/payment-methods | Active manual labels for authorized finance readers/writers and payroll payers |
| PATCH /api/v1/finance/payment-methods/{method} | Explicit configuration-only settings update, without changing ledger mappings |
| GET /api/v1/finance/summary | Scoped posted fee balances, recent allocations and authorized payroll summary |

Existing student finance, payment history, payroll list/detail/payment, own-salary and salary-report endpoints were extended in place. No external payment endpoint, webhook or bank SDK was added. Route count is **138**.

| Permission | Default roles / boundary |
| --- | --- |
| finance.read | Super Admin, School Administrator, Accountant/Clerk; fee views |
| finance.configure | Super Admin, School Administrator; configuration only |
| finance.write | Super Admin, Accountant/Clerk; manual fee transactions |
| finance.approve | Super Admin; concession approvals, refunds and existing management approvals |
| payroll.read | Super Admin, Accountant/Clerk; payroll and salary records |
| payroll.write | Super Admin, Accountant/Clerk; generation and manual disbursements |
| payroll.approve | Super Admin; salary setup/adjustments and payroll approval |
| accounting.read | Super Admin, Accountant/Clerk; journals, trial balance and ledger exports |

Own salary access remains ownership-based. Seeders insert the new default permissions/grants; they do not silently revoke production assignments. Custom roles previously relying on finance.read/write/approve for payroll/ledger access need an explicit reviewed grant of the new permissions. Review any administrator grants left by older seeders through the established Super Admin RBAC screen. The school administrator receives no payroll or journal authority merely to make a test pass.

## Validation

- Complete Laravel suite on guarded disposable **MariaDB 10.11.14**, loopback port 53317: **199 tests, 1,958 assertions, zero failures/errors, two intentional PostgreSQL-only skips**. [Full output](manual-finance-mariadb-tests.txt).
- New ManualFinanceTest: 11 tests covering default data, all six manual methods/no network, partial/full fees, receipts/history, discounted assigned tuition, prior dues, default cross-session arrears, explicit session isolation, invalid/duplicate/overpaid/inactive payments, partial/full payroll, cross-period allocations, retry keys, legacy paid periods, own salary privacy, immutable history and method administration.
- Existing FinanceWorkflow, RBAC, accounting, dashboard, staff account, report, MySQL compatibility, parent OTP/Spext and readable-data tests remain in the full green suite. The two old tests specifically exercising pending verification now explicitly configure a **manual-review** method rather than assuming every bank transfer requires it; their accounting/authorization assertions remain intact.
- Flutter analyze: **No issues found**. Complete Flutter test suite: **116 passed**, zero failures. New UI tests exercise unbilled tuition, missing methods, amount/notes/allocation submission, receipt navigation and refreshed balance, salary instalments and a narrow dark finance overview. Permission tests cover configuration-only, payroll-approval and read-only action visibility.
- PHP files changed in this request were formatted with Pint. Flutter files were formatted with Dart format. No real payment/SMS gateway call occurred; external test behavior uses mocks/fakes. No physical-device or production smoke test is claimed, and no new release APK was built.

## Safe future cPanel release procedure — operator only, after review

These commands have **not** been run on production. Substitute the real API directory and use the cPanel site's configured PHP CLI (the project requires PHP 8.2 or later and its existing extensions). Do not copy a local .env to production or print any secrets. Keep the existing APP_KEY, SMS credentials and database credentials unchanged.

1. Rehearse on a restored backup. In cPanel take a consistent database backup plus a matching private configuration/key backup and verify restoration. Record aggregate invoice/payment/journal/payroll counts and balanced totals privately.
2. Stop scheduler/cron, queue workers and other writers through their existing cPanel/service controls. Enter maintenance before replacing the reviewed release files. Preserve production .env, private uploads, sessions/cache storage and keys.

```sh
cd /home/YOUR_CPANEL_USER/PATH_TO/api
php -v
php artisan down
```

3. Install the reviewed release files, including its migrations/seeders, without overwriting production secrets or storage. Then run:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:clear
php artisan migrate:status
php artisan migrate --force
php artisan school:seed-finance-defaults
php artisan db:seed --class='Database\Seeders\MobilePermissionSeeder' --force
```

The finance-default command is idempotent and is the one-time production data step for missing methods/accounts. The permission seeder adds the reviewed new grants only. Do **not** run FinanceConfigurationSeeder or school:seed-mobile merely to fix a method dropdown; those legacy commands also provide class/session fee defaults.

4. If the earlier readable-data release has not yet been finalized, follow its [conversion procedure](readable-data-storage.md) with all writers stopped and the original key. After the additive schema migration, the relevant commands are:

```sh
php artisan school:convert-readable-data --check
php artisan school:convert-readable-data
php artisan school:convert-readable-data --check
php artisan school:convert-readable-data --drop-legacy
php artisan school:convert-readable-data --check
```

Require success at every step. Do not resume an application using readable values while stale legacy columns remain. These commands are not a key rotation and must not be used to bypass failed source validation.

5. Review roles explicitly: add payroll.read/write/approve or accounting.read to custom roles only where authorized. Remove any unintended old finance write/approval grants through the existing RBAC administration workflow after review; no migration deletes grants. For existing bank/cheque methods, use Payment method settings to disable **manual management review** if that is the approved school policy. Do not auto-approve outstanding pending entries or bulk-update financial history.
6. Rebuild caches and verify route registration:

```sh
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:cache
php artisan route:list
php artisan migrate:status
```

7. With controlled operator access, verify active method listing, correct class/session configuration, unbilled/voucher messaging, a legitimate manual payment and receipt, a partial salary payment, updated balances and balanced journals. Use approved operational/test records; never create fictional production accounting entries simply to run the automated suite. Confirm read-only/administrator/parent restrictions. Distribute the reviewed matching Flutter build through the school's normal process; no build or distribution was performed here.
8. Resume workers and scheduler using their existing cPanel/service controls, then reopen:

```sh
php artisan queue:restart
php artisan up
```

If any migration, conversion or smoke check fails, keep maintenance enabled and investigate using redacted errors. Do not run migrate:fresh, migrate:rollback, key:generate, delete finance history or directly edit journals. Recovery uses the coordinated verified backup and matching code/configuration; restoring after new writes requires a separate reconciliation decision.

# Monthly billing, readable identifiers and school installation audit

Validated locally on 29 October 2026 (Asia/Karachi), PHP 8.2.12 and MariaDB 10.11.14. No deployment, commit, push, production database access, real SMS, production conversion, credential changes or APP_KEY changes occurred. No vendor files or dependency constraints changed.

## Root causes and fixes

1. The monthly preview already received all eligible periods, but Flutter discarded every row with an invoice. An all-billed period appeared as an unexplained zero, and its Generate button remained enabled. The new preview shows all rows, vouchers, amounts, net paid and outstanding balances; counts billed, missing, ready and unknown periods separately; hides Generate when nothing is ready; and blocks an incomplete batch. The student screen offers **Review monthly fees** when nothing is missing. Completed posting returns to the existing immediate reload flow.
2. A section filter selected students but did not restrict each student's billable months to that section. Both preview and posting now apply the section at the month/enrollment level, alongside class/session filters. These filters continue to intersect SchoolAccess; they grant no permissions.
3. FeeService required a class schedule before considering an explicit dated student tuition override. A valid override now supplies tuition independently. Without either source, quote amounts remain null, the API says configuration is required, and generation rejects the batch. The snapshot no longer presents absent configuration as zero tuition. An explicitly configured zero remains a valid zero.
4. Account creation, CLI account creation and own-password change required 12 characters. Staff editing ignored password inputs entirely. Login also did not recheck its verified password snapshot after acquiring the user lock, permitting a reset/login race. These flows now accept 4-1024 characters. Staff edits can reset a password with confirmation, using the existing privileged-target rules; they revoke authentication sessions and push devices. The hashed model cast still owns password hashing. Login now rejects a changed password snapshot under the same user lock used for reset, and rechecks active/role eligibility before issuing a session.
5. Staff accounts without linked employee profiles lacked a human code; admission request UUIDs were only technical identifiers; payroll references depended on browsing UUID relationships. Added stable account/admission codes and readable payroll/slip references while retaining UUID keys and all foreign keys.
6. Plain `migrate:fresh --seed` intentionally supplied minimal base references; separate setup commands were needed to install permissions and finance defaults. Added an explicit SchoolInstallationSeeder and an interactive **school:bootstrap** installer. The base seeder retains its existing contract. No default administrator password is seeded.

## Monthly fee behavior before and after

| Case | Before this continuation | Final behavior |
| --- | --- | --- |
| March admission, through October | Backend could plan eight months | Eight eligible months, March through October; full-month policy preserved |
| No invoices | Eight missing, only missing rows shown | Eligible 8, billed 0, missing/ready 8; PKR 8,000 to generate |
| March/April billed | Six missing; billed details hidden | Billed 2, ready 6; PKR 6,000 to generate, existing vouchers shown |
| All eight billed | Zero missing with enabled Generate | Billed 8, missing 0; explanation and Close, no Generate button |
| May already billed, exact May | Unexplained zero preview | One eligible row showing May voucher and its payment/balance; missing 0 |
| May absent, exact May | Backend already selected cutoff month | Only May is evaluated and generated; no March/April posting |
| Through May, exact-month switch off | Catch-up plan | All eligible March-May periods evaluated; only missing periods posted |
| Paid or partially paid invoice | Still existed, details omitted | Still billed; never missing; Paid / Partially paid with exact net amounts |
| No schedule or override | Quote unknown; snapshot could show zero | Fee configuration required; null tuition, blocked posting |
| Student override without class schedule | Incorrectly unknown | Explicit dated override is valid tuition configuration |
| Repeated generation | Backend skipped existing monthly invoices | Same idempotency, row locking and journal path; no duplicate voucher |
| Section changes | Selection could include other-section months | Each month must belong to selected section |

The `missing_months` API field retains its existing meaning: all eligible months without a voucher/approved coverage, including unknown rates. `ready_to_generate` is the configured subset; `needs_configuration` is the unknown subset. `unbilled_minor` sums configured missing tuition only, never unknown estimates. `eligible_months`, `already_billed`, `covered_months` and student name are additional preview fields. Posting responses retain the evaluated pre-post plan plus `generated_ids`; clients refresh the snapshot after success.

Existing accounting policy is unchanged: use the intersection of admission, dated enrollment and academic session; select the latest applicable enrollment in a transition month; apply dated overrides/schedules/concessions; stop at ended enrollment boundaries and the current month. Future requests are rejected by the endpoint. An inactive student with an undated open enrollment does not accrue invented missing months; already posted invoices remain readable. Opening balance coverage is separately approved, cannot overlap posted tuition and is not charged a second time. Unreviewed opening balances block catch-up posting. All generation uses FeeService and the existing double-entry journal, transaction, allocation and receipt services.

The eight-month example is a test fixture, not a claim about the current production student. Production was not queried or modified.

## Identifiers and migration

New migration: `2026_10_29_000019_add_readable_business_codes.php`.

| Entity | Readable identifier | Implementation |
| --- | --- | --- |
| Student | `INF-SG-26-0001` | Existing unique `students.registration_number`, unchanged |
| Account, including staff | `ACC-000001` | New unique `users.account_code`; independent of roles so promotions do not renumber accounts |
| Staff employment | `STF-0001` or school's existing code | Existing unique `staff_profiles.employee_number` retained; never overwrite existing employee codes |
| Tracked admission request | `ADM-000001` | New unique `admission_requests.admission_number`; idempotent retries return the same code |
| Legacy/non-request admission | Student registration number | Existing registration remains the readable admission identity |
| Invoice | `INF-SG-FV-2026-000001` | Existing unique voucher number unchanged |
| Verified payment | `INF-SG-RC-2026-000001` | Existing receipt numbering unchanged; pending payments do not fabricate a receipt |
| Payroll period | `INF-SG-PR-2026-10` | Derived from the existing unique, immutable payroll period |
| Salary slip | `INF-SG-SL-2026-10-ACC-000001` | Period plus stable account code; employee-code edits do not renumber old slips |
| Journal, refund, support ticket | Existing document number | Existing human references retained |

Codes use the existing document_sequences table with row locks and transactions. Migration adds nullable unique columns, backfills missing codes with locks, and resumes without renumbering filled rows. Nullable DDL supports MariaDB interrupted-migration recovery and legacy direct SQL writers; normal Eloquent account creation always assigns a code. Do not bypass the application's creation path or manually change codes/counters. No UUID, PK, FK, payment allocation, ledger amount, voucher or receipt is replaced. The migration's down path refuses to discard business identifiers; use a reviewed backup/forward correction instead of destructive rollback.

Payroll/slip references are derived API/report fields; inspect `payroll_periods.period` and join `users.account_code` in SQL. They do not require updates to immutable historical financial rows. Dropdowns/list titles show code and name, staff profiles expose account code, payroll and salary views show references, and salary reports include the slip reference. Existing student/fee/receipt reports already use registration/voucher/receipt numbers. UUIDs continue to travel as API relationship keys and internal navigation parameters.

Every column and all UUID primary/foreign keys are documented in [database-readable-fields-audit.md](database-readable-fields-audit.md), with metadata in [database-readable-schema.json](database-readable-schema.json). Final disposable schema: **86 tables, 713 columns**, after guarded legacy-field cleanup. No hardcoded UUID literal exists in Laravel application/config/routes/seeders or Flutter runtime source. The repository scan found seven illustrative literals in the CSV import templates; those are examples, not runtime dependencies. No replacement production ID was introduced.

## Password and security behavior

- Create staff, CLI Super Admin creation, local development creation, staff reset/edit and own-password change accept `1234`. Length under four fails. Existing confirmation rules remain; admin reset requires matching confirmation. Blank optional Flutter edit fields are omitted so saving other fields keeps the password.
- Own-password change still verifies the current password, requires a different new value, and revokes all sessions/devices. Managed reset also revokes all sessions/devices. The old password fails, and the new password succeeds. A simulated reset between the initial login read and row lock cannot issue a session from the old password. Password/hash fields are never returned; audit records contain field names, not values. Parent accounts are outside staff reset targeting.
- No public forgot-password flow or password-setting import was found. Staff imports create employment profiles linked to selected existing accounts; they do not import login passwords. The existing account-management/hidden CLI path remains the way to issue credentials.
- Passwords remain Laravel one-way hashes. OTP hashes/lookup protection, token digests, login throttling, ownership checks, RBAC, last-Super-Admin protection, encrypted push credentials, provider configuration and Flutter secure storage remain intact. No new permission grants are applied by migrations.
- Spext still resolves through the existing contract/configuration and receives readable guardian mobile. All provider tests use HTTP fakes; the validation environment uses SMS_DRIVER=disabled. No real SMS/API/gateway request was made.

## Installation and reset

New command: `php artisan school:bootstrap`.

Run only against a deliberately chosen empty **local/testing** database with its development APP_KEY already configured. It runs ordinary migrations and SchoolInstallationSeeder, then asks for administrator name/email and a hidden password/confirmation. It has no reset option, no force override and no default credentials. It refuses production, noninteractive execution and any existing user account. If password entry fails, fix the terminal/input and retry; reference setup is retained, existing accounts are never overwritten.

The installation seeder composes the existing base roles/settings/classes, permission matrix and known school finance/academic defaults. Review the existing `2026-27` academic session dates and school fee schedules before admitting real students. Sections and teacher assignments require real school choices and are not fabricated. Existing methods, accounts and finance customizations remain governed by the existing insert-missing seeders.

For an intentionally disposable development reset, after verifying APP_ENV and database identity:

```powershell
cd E:\info-tech-school-management\api
php artisan migrate:fresh --seed
php artisan school:bootstrap
```

The first command destroys that selected development database and creates minimal base references; bootstrap completes school defaults and the first administrator. On a new empty database, use bootstrap alone. Never execute these reset/bootstrap instructions against production. Laravel's production confirmation/`--force` safeguards for its built-in destructive commands remain unchanged; refusal preserves existing rows when confirmation is declined. The new installer refuses production even with an interactive terminal.

The guarded standalone test `tests/Support/validate-school-bootstrap.php` deliberately exercises fresh/reset against only the fixed disposable loopback database. Windows native hidden-terminal input is stubbed at that terminal boundary in this test; actual password validation, hashing, migration, seeding and account insertion execute. PHPUnit additionally tests CLI prompts, confirmation, hashing, API login and production refusal.

## Validation actually run

| Check | Result |
| --- | --- |
| Complete Laravel suite on MariaDB 10.11.14 | **224 tests, 2,247 assertions; zero failures/errors; 2 expected skips** |
| FinanceWorkflowTest and MySqlCompatibilityTest | Passed, including payroll approval/disbursement/privacy, journal immutability/balance, constraints and row locking |
| Monthly/admission, account security, identifiers, RBAC, Spext, conversion | Included in complete passing suite |
| Fresh migration/base seed, empty bootstrap, safe repeat | Passed in isolated MariaDB |
| Readable-data conversion, readback, cleanup DDL, repeated finalization | Passed in isolated MariaDB |
| Complete Flutter suite | **125 passed** |
| Flutter analyzer | **No issues found** |
| Route list | **140 routes**, including POST `api/v1/finance/monthly-fees` |
| Formatting / whitespace | Changed PHP formatted with Pint; Dart formatted; git diff --check passed |

The two expected skips are PostgreSQL-only assignment-failure and deferred-journal-trigger tests. MariaDB-specific integrity tests execute rather than skip. Evidence files: `monthly-school-audit-mariadb-tests.txt`, `monthly-school-audit-flutter-tests.txt`, `monthly-school-audit-flutter-analyze.txt`, `monthly-school-audit-bootstrap-tests.txt`, `monthly-school-audit-conversion-tests.txt`.

No known unexpected test failures remain. Flutter dependency resolution reported 21 newer versions outside current constraints; dependencies were not upgraded. No physical Android/device, APK installation or cPanel smoke test was performed. Large historical batches retain the 500-student limit and can require narrower scopes. No automatic scheduler/backfill was installed. Existing history/configuration still needs operator review; no production gap, enrollment date or opening balance was guessed.

## cPanel deployment instructions only

These commands were **not run against production**. Replace the directory in the first command with the existing cPanel API directory. Use the host's PHP 8.2 executable if plain `php` points elsewhere. First take and verify a backup of the current database and application. Preserve production `.env`, APP_KEY, private storage/provider files and existing vendor. Pause scheduled writes during the maintenance window.

```bash
cd /home/YOUR_CPANEL_USER/PATH_TO/api
php artisan down
```

Upload the reviewed changed `api/app`, `api/database`, `api/routes` files from the manifest, preserving existing vendor, `.env` and storage. Do not upload test databases, `.local-db-audit`, local environment files or Flutter test assets to the API. No dependency changes require Composer here.

Then, in this order:

```bash
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
php artisan route:list | grep -i "monthly"
php artisan route:list --path=api/v1/finance/monthly-fees
php artisan up
```

Do not resume traffic if migration or route verification fails. The expected route is **POST api/v1/finance/monthly-fees -> FeeController@monthly**. Verify all required migrations are Ran, including `000019`. No `migrate:fresh`, `db:wipe`, `school:bootstrap`, production seed reset, readable-data conversion or APP_KEY rotation belongs in this deployment. This migration adds business codes only; the earlier ordinary-data conversion remains a separate reviewed production task.

Route caching is optional. If the operator chooses it after verifying the new routes, run and immediately recheck:

```bash
php artisan route:cache
php artisan route:list | grep -i "monthly"
```

If the route disappears or is stale, `php artisan route:clear` and verify again. This deliberately avoids broad `optimize`, config-cache rebuild, `artisan about`, shell_exec/proc_open diagnostics and Composer on cPanel. No credentials or config dump are printed. These cache/migration commands do not require the application's disabled subprocess features.

After deployment, use an authorized finance account and preview only: historical May with exact-month enabled should show one existing voucher if already billed; through October should show all eight eligible periods for a March admission within the same session/enrollment. Paid/partial amounts must match the receipt history. Generate only genuinely missing configured months after reviewing the preview; repeating must generate zero more vouchers. Confirm account-code display, staff password change on a designated test account, old-session rejection and trial-balance consistency. Deploy the tested Flutter build through the school's normal release process separately; no APK was built in this continuation.

## Files and Git commands

The complete created/modified file inventory and exact scoped Git commands are in [monthly-school-audit-files.md](monthly-school-audit-files.md). They are instructions for a later authorized commit/push, not actions taken here. Existing unrelated untracked scratch content was left untouched.

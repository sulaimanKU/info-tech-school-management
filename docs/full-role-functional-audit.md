# Full role functional audit

Local implementation completed from the existing working tree on 29 October 2026. No reset, commit, push, deployment, production connection, production conversion, environment credential change or real SMS/provider call was performed. The earlier finance, readable-data, Spext and authentication changes remain in place.

## Root causes and resulting changes

1. Settings navigation led mainly to catalogs, while school information lacked a validated write path and arbitrary settings could be returned. Added a real settings hub, school profile editor, safe authenticated information endpoint and allowlisted settings service. Saved school identity now brands report output. Integration configuration exposes metadata only.
2. Academic settings used separate weekday/position and grading writes, permitting partial success. One validated transaction now saves the intended configuration. Shared grading validation retains published result snapshots. Weekday changes snapshot earlier session calendars rather than changing past attendance denominators. School-day edits cannot change submitted attendance's day type.
3. Populated catalog edits treated unchanged dates/relationships as attempted changes. Renames now compare actual before/after values; genuine unsafe changes to populated context remain blocked.
4. Teachers lacked a direct assignment-driven workspace. Added current classes, deduplicated rosters, subjects, attendance state, academic actions and exam/assessment/diary summaries. Active profile, role, assignment dates, section and subject checks remain server-side. Closed sessions and future assignments cannot create dead daily attendance actions.
5. Coordinators could not manage teachers without broader administration access. Added `assignments.manage`, a minimal teacher picker and paginated scoped management. Both original and destination section are checked. No coordinator account, payroll, RBAC or self-scope expansion grant was added.
6. Future homework asked the future-forbidden attendance endpoint for a roster. Added an independent academic roster using valid dated enrollments. Future attendance remains rejected.
7. Managed assessment creation could not safely select an eligible teacher. Added date/section/subject-filtered assignment selection and server validation. Teacher edit and reviewer controls now follow ownership/state metadata. Marks bounds, actual roster, versioning and no self-approval remain enforced.
8. Shared forms transformed passwords/boolean values and optional choices could not be cleared. Exact password text and real boolean values now survive submission; optional dropdowns support clearing and historical options do not crash rendering.
9. Missing operational reports and incomplete dashboard facts obscured workflow results. Added six permission-scoped report types and actual monthly admissions, net issued vouchers and all approved outstanding payroll facts. Billing previews/opening warnings now batch read facts; authoritative posting still rechecks live state under existing locks.
10. Full Flutter verification caught missing Urdu labels and old module-count assertions. Added proper translations and updated module-count expectations for the new teacher workspace; retained responsive layout, logout and translation assertions.

The earlier two payroll/RBAC failures mentioned in an older request were already resolved in the supplied baseline. This continuation reproduced a passing baseline of 224 tests / 2,247 assertions, rather than inventing new root causes for failures no longer present. Payroll approval/disbursement and RBAC regression tests remain part of the passing final suite.

## Role matrix

Default grants are described here. Explicit custom permissions remain supported; active account, hierarchy and record scope are still enforced independently.

| Role | Working modules and actions | Scope / exclusions |
|---|---|---|
| Super Admin | School/academic settings, catalogs, accounts, RBAC, coordinator/teacher assignment, students/guardians, attendance, homework/review, assessments/publication, exams, communication, imports, finance, accounting, payroll approval, exports | Existing protected-account, approval, journal, idempotency and private-file rules remain |
| School Administrator | School/academic profile and catalogs, staff, assignments, students/guardians, attendance corrections, academic review/publication, communications/imports, finance read/configuration and appropriate reports | No default RBAC management, payroll access/approval, accounting access or unrestricted money-posting grant |
| Coordinator | Authorized section students, academic work/review, attendance corrections, teacher assignment create/edit/deactivate, follow-ups, observations, scoped communication and academic reports | Only current authorized sections and valid record dates; cannot manage staff accounts, school settings, roles, payroll, finance or coordinator scope |
| Accountant / Clerk | Authorized student finance lookup, fee/payment/manual receipt workflow, balances, accounting/trial balance, expenses and payroll preparation/disbursement as granted, finance/payroll reports | Management approval remains required where applicable; no default global academic write/review, school setup, RBAC or payroll approval |
| Teacher | My classes, assigned sections/subjects/students, scoped profiles, attendance draft/submit, homework/diary draft/submit, assigned assessments and marks, published exam schedules, authorized observations and communication | No assignment management, global student list, settings mutation, money/payroll/security modules, self-approval or submitted attendance overwrite |
| Parent / Guardian | Existing verified CNIC/mobile OTP login, child switcher, own-child profile, attendance, published homework/results/behavior/exams, fee balances/receipts, announcements, account/school information | Only active verified guardian links; no class roster, unpublished records, staff data, settings writes or general reports |

Modules and actions are filtered by `ModuleAccess`/permission checks. APIs reauthorize direct requests. There is no role-based shortcut granting finance access to make a test pass.

## Screens and endpoints

All new routes use existing authenticated middleware, response envelopes and error handling.

| Screen / feature | Endpoint added | Authorization / effect |
|---|---|---|
| Account → School information | GET `/api/v1/school-information` | All authenticated roles; safe readonly allowlist |
| Settings → School information editor | PUT `/api/v1/school-settings` | `school.manage`; validated profile round trip, audit field names only |
| My classes | GET `/api/v1/my-teaching` | Actual Teacher role plus academic permission and own active dated assignments |
| My class students | GET `/api/v1/my-teaching/sections/{section}/students` | Own section; search and pagination, 30 rows |
| Homework roster | GET `/api/v1/academic-roster` | Existing section/current/date scope; session bounds; no parent class access |
| Teaching assignments management | GET `/api/v1/teaching-assignments` | `school.manage` or `assignments.manage`; scoped pagination/filter/search |
| Teacher picker | GET `/api/v1/teaching-assignments/teachers` | Same narrow management gate; safe minimal staff fields |

Existing routes extended: GET `school-settings` now returns safe values and integration metadata; catalog teacher assignment POST/PATCH/deactivate accepts the narrow scoped manager permission; academic-settings PUT saves grading atomically; assessment create validates eligible teacher and detail provides action metadata; attendance/calendar/diary enforce session/calendar rules. Existing generic report routes now support `enrollment`, `teacher_assignments`, `class_attendance`, `class_results`, `payment_register`, `payroll_summary` in the established export formats. No separate money-posting endpoint or payment gateway was introduced.

## Data and migration safety

`2026_10_29_000020_add_scoped_assignment_permission.php` is a data-only permission migration. Fresh installs get the same default grant through the permission seeder. Populated installs receive only the new assignment permission for the default administrator/coordinator roles; existing custom grants and application rows are retained. If the permission already exists, migration replay does not undo a later deliberate revocation. Tests exercise existing students, staff, password hashes and permissions through the upgrade. No UUID relationship or readable business code is replaced.

`BillingSnapshot` is request-local, bounded read data for previews. It is not an authorization source or posting cache. Existing finance services retain integer amounts, double-entry balance checks, idempotent posting and live quote/locking paths. Snapshot/live equivalence includes paid/partial invoices, effective overrides and concessions. A 25-student/200-month fixture verifies bounded SQL query count.

Passwords remain hashed (four-character minimum managed passwords supported), login reset races/session revocation protections remain, and OTP challenges remain hashed. Guardian readable CNIC/mobile, import conversion rollback/finalization checks, Spext environment-driven credentials and disabled/local provider guards remain tested. Spext source/configuration needed no further change in this continuation.

## Verification

| Check | Final local result |
|---|---|
| Reproduced baseline | 224 tests, 2,247 assertions; zero failures, 2 expected PostgreSQL-only skips |
| Targeted finance/RBAC/parent/Spext/readable/settings/workspace/reports/admission run | 141 tests, 1,565 assertions, all passed |
| Complete Laravel suite, MariaDB 10.11.14 / PHP 8.2.12 | **249 tests, 2,534 assertions; zero failures/errors; 2 expected PostgreSQL-only skips** |
| FinanceWorkflowTest | All 15 tests passed within targeted and complete runs |
| RBAC scope / filter regressions | All 29 tests passed within targeted and complete runs |
| SpextSmsTest | All 19 cases passed; fake credentials and HTTP fakes / stray-request prevention |
| ReadableDataTest | All 5 tests passed, plus standalone finalization DDL / repeat check |
| Complete Flutter tests | **137 passed** |
| Flutter analyzer | **No issues found** |
| Fresh migrate/seed and empty-database bootstrap | Passed; no default password; four-character credential hashed; safe repeat |
| Populated permission upgrade | Passed within complete suite; preserves existing data/custom grants/revocation |
| Readable conversion and finalization DDL / repeat | Passed on guarded disposable database |
| Laravel routes | 147 registered routes; new endpoints resolved |
| Formatting / static review | Changed PHP passes Pint; Dart formatted; diff whitespace check clean; no runtime UUID literals or empty callback/TODO markers found in app scan |

Evidence: `full-role-baseline-tests.txt`, `full-role-mariadb-tests.txt`, `full-role-flutter-tests.txt`, `full-role-flutter-analyze.txt`, `full-role-bootstrap-tests.txt`, `full-role-conversion-tests.txt`, `full-role-migration-status.txt`, `full-role-routes.json`. Tests use HTTP mocks/fakes; no real SMS, Spext request or production API call occurred. Database checks were guarded to `127.0.0.1:53317/infotech_compatibility_test`, never a production schema.

Added tests: `SettingsFunctionalTest` (7), `TeacherWorkspaceTest` (8), `RoleFunctionalReportsTest` (8), two meaningful admission/billing regressions, and `full_role_functional_test.dart` (12). Existing Flutter attendance test now scrolls to the mark-all control after the new counts. Existing responsive/module assertions now include My classes. Valid security/domain assertions were retained.

## Remaining practical limits

- A daily lesson timetable is not configured; the UI explicitly distinguishes assignments from a timetable. No fictitious schedule was added.
- Logo is bundled, currency/time zone are fixed, and integration credentials are server-managed. These are shown as readonly policy, without fake editable settings.
- Actual school session dates, grading bands, enrollments, fee schedules and assignments require operator review. Grading needs a 0% floor. No automatic historical fee backfill or new cron schedule was enabled.
- Existing roster write limit is 200; reference catalog responses cap at 1,000; operational exports reject more than 5,000 rows. Use filters/pagination for large datasets. Batch preview uses bounded reads; generation retains its existing limits.
- Verification is local API/widget/database testing, not a physical-device, APK, live provider or production smoke test. PostgreSQL-specific constraint checks are intentionally skipped on MariaDB; equivalent MariaDB workflows pass.
- Flutter dependency resolution reported 21 newer package versions outside current constraints. No package or lockfile was upgraded; this is an informational dependency notice, not an analyzer or test failure.

See `settings-functional-audit.md` for each setting, `teacher-workflow.md` for operational steps and `full-role-functional-files.md` for the complete file manifest and review/deployment instructions. No Git staging, commit, push or deployment command was executed.

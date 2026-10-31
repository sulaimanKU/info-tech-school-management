# Final teacher assignment fix

Completed locally on **31 October 2026** from the preserved partial working tree. No commit, push, deployment, production access, APP_KEY/credential change, real SMS/provider call or production conversion was performed. Existing Staff Profile, finance, payroll, authentication, settings, themes and TreadExellence startup remain in place.

## Root causes

Both empty teacher controls depended on employment rows rather than eligible accounts:

1. Staff account creation writes `users` and its Teacher role. A separate `staff_profiles` employment row is not required to create a legitimate staff account.
2. `TeachingAssignmentController::teachers()` previously started from `staff_profiles` with an inner join to users. An active Teacher account without that row disappeared from the selector. Contact/photo fields and academic session were not the cause; the endpoint, role predicate and Laravel paginator parsing were otherwise compatible.
3. School Setup's redundant assignment form populated its internal “Staff profile” dropdown from `catalog/staff`, which likewise contains only existing employment rows. The same account/profile gap left it empty, and a second assignment editor made the workflow confusing.
4. Required creation fields reused `ScopedChoice`'s filter-oriented blank/“Any” option. Required creation choices now have explicit prompts and dependent enable/reset behavior; ordinary filters retain “Any”.

The missing-profile failure is reproduced through the **real staff-account creation API**, not only seeded `teacher()` fixtures. Production records were not queried, so this report does not claim to have inspected the live database. The optional aggregate-only CLI diagnostic below can confirm the deployed data distribution without exposing private records.

## Eligibility and API contract

The selector now starts from active `users` with the **Teacher role**, left-joining their optional employment link. An explicitly inactive employment row remains excluded. Missing photo/contact/optional employment details do not hide an otherwise eligible teacher. No GET request creates employment rows, guesses dates, changes status, roles or credentials.

GET `/api/v1/teaching-assignments/teachers` keeps the existing response envelope and paginator (`data.data`, `current_page`, `last_page`, `total` inside the API envelope). It supports server search and 30-row pagination. Each row contains:

| Field | Meaning |
|---|---|
| `user_id` | Stable account identifier for selection; internal only |
| `id`, `staff_profile_id` | Existing employment identifier, nullable when not linked; legacy `id` remains compatible for linked teachers |
| `name`, `account_code`, `employee_number` | Human-readable identity; employee code may be null |
| `role`, `is_active` | Teacher role and active account status |
| `staff_linked`, `profile_link_required` | Explicit linkage state for safe creation UX |

No email, address, password, salary, credential or private profile record is returned by this picker. The UI displays readable identity/role/status and never raw UUIDs.

POST `/api/v1/teaching-assignments` accepts `teacher_user_id` for account selection and retains legacy `staff_profile_id`. If both are supplied they must identify the same account. GET assignment listing also accepts `teacher_user_id`, so an account with no assignments returns a valid empty paginator rather than requiring a fabricated profile identifier.

Existing employment rows are reused without overwriting staff code, designation, joining date or status. For the **first assignment of an unlinked account**, the current normalized assignment schema requires an employment foreign key. An administrator with `staff.manage` confirms the real joining date and the displayed staff code/designation inline in the assignment form. Suggested code comes from the existing readable account code; suggested designation is Teacher. These values are editable and explicitly submitted, not hidden fake records. No joining date is preselected or inferred from account creation/current date.

Optional payload for that authorized first link:

```json
{
  "teacher_user_id": "<selected account ID>",
  "academic_session_id": "<selected session ID>",
  "employment": {
    "joined_on": "<actual confirmed YYYY-MM-DD>",
    "employee_number": "<confirmed readable staff code>",
    "designation": "<confirmed designation>"
  },
  "assignments": [
    {"section_id": "<section A>", "subject_id": "<mathematics>", "is_class_teacher": false},
    {"section_id": "<section B>", "subject_id": "<mathematics>", "is_class_teacher": false}
  ]
}
```

The employment link and 1–50 assignment rows save in the existing transaction. Any invalid section/subject/session, duplicate, invalid employment fact or authorization failure rolls everything back. Existing user, role, password hash and academic records stay intact. Stale employment input cannot overwrite a profile that was linked by another request. The saved profile identity is returned and updates the selected teacher in the client, avoiding a second employment setup prompt when adding another assignment.

Coordinators can select these accounts but cannot create employment or gain `staff.manage`; an actionable explanation directs an authorized administrator to confirm the missing employment facts. Assignment authorization still checks every section, and old/destination sections for edits. Teachers, accountants and parents cannot retrieve the picker or manage assignments under their default grants. Protected administrator hierarchy remains enforced for employment linking.

## One canonical workflow

Use **Teacher Assignments** from the existing management/academic navigation:

1. Select/change a teacher using server search and pagination.
2. See the readable teacher identity and existing assignments. Academic filters remain available for session/class/section/subject/status.
3. Choose **Add another assignment**, select one academic session, then add class → section → subject rows. Complete any required first-time employment confirmation in the same workflow.
4. Save the rows together; the existing list reloads. Edit or safely deactivate an existing row through the same management workflow.

Same teacher/subject across different sections, classes and legitimate subject combinations remain supported by the existing normalized table. Exact enabled duplicates are rejected under existing database rules. “Class teacher” retains its existing meaning: one enabled class-teacher row per section (session is defined by the section). Conflicts now explain that the existing class-teacher assignment must be edited/deactivated; no teacher is silently replaced.

School Setup's **Teacher assignments** menu item and its “Staff profile” assignment form were removed. Genuine session/class/section/subject and staff configuration remains. Existing catalog endpoints were retained for compatibility and internal single-row editing. No new assignment table or competing service was introduced.

The actual Staff Profile remains functional, including private photo handling, readable codes, contact/employment details, safe self/admin editing and teaching summaries. This change does not remove that feature or broaden its permissions.

## Empty/error and creation behavior

- No eligible teachers: **No teachers available**, with guidance to create/activate a teacher staff account.
- Search with no match: **No teachers match your search.**
- Request/permission failure: actual safe error state with Retry where appropriate, never represented as an empty teacher database.
- No assignments: **No teaching assignments yet**, with the add action available.
- Required creation fields: **Select academic session / class / section / subject**; no filter-style “Any”. Dependent fields remain disabled until their parent selection exists. Changing class clears incompatible section/subject values; changing section clears the subject. Incomplete rows cannot save, except the existing valid class-teacher-only case.

## Validation results

| Check | Result |
|---|---|
| Targeted assignment, RBAC, Staff Profile/photo tests | **77 tests, 732 assertions passed** |
| Exact new backend compatibility class | **8 tests** included in targeted/full runs |
| Targeted calendar/parent date regressions | **15 tests, 245 assertions passed** |
| Complete Laravel suite, MariaDB 10.11.14 / PHP 8.2.12 | **275 tests, 2,783 assertions; zero failures/errors; 2 expected PostgreSQL-only skips** |
| Complete Flutter tests | **168 passed**, including 10 new selector/creation/linkage tests |
| `flutter analyze` | **No issues found** |
| Fresh bootstrap / hashed four-character credential / safe repeat | Passed on guarded disposable local database |
| Readable-data conversion/finalization and repeat | Passed on guarded disposable local database |
| Routes | **148**, no new public debug endpoint |
| Migration | **None needed**; existing migrations/indexes preserved |
| Formatting | Changed PHP passes Pint, changed Dart formatted, diff whitespace check clean |
| Emulator-compatible APK | Debug build succeeded with approved API define; built only, not installed/launched/distributed |

The complete suite exposed two pre-existing October-31-sensitive test fixtures. Parent invoice fixtures used month subtraction from the 31st, overflowing into a duplicate invoice period; the fixture now starts at the month's first day before subtracting. The calendar test treated October 31 as future without fixing its clock; it now runs at its intended October 29 date. All accounting constraints and calendar assertions remain. No application finance/calendar behavior was changed to make these tests pass.

New tests cover actual account creation without employment, existing/incomplete optional profile data, inactive/nonteacher exclusions, search/pagination/empty contract, unauthorized roles, coordinator scope, atomic first-link rollback, preservation of credentials/profile history, read-only diagnostic privacy, nullable profile selection, request retry, required prompts and dependent resets, canonical navigation and continued Staff Profile summaries. Existing multi-assignment, RBAC, finance/payroll, photo, authentication, readable identifier, parent and SMS regressions remain in the complete suite. All SMS/provider tests use fakes; no real SMS/provider call occurred.

Evidence files are listed in the inventory below. Database checks used guarded loopback `127.0.0.1:53317/infotech_compatibility_test`, never production. The local diagnostic evidence describes the bootstrap fixture database, not production.

### APK validation

```powershell
Set-Location 'E:\info-tech-school-management\mobile'
flutter build apk --debug --no-pub --target-platform android-x64 --dart-define=API_BASE_URL=https://api.tread.com.pk/api/v1
```

Artifact: `mobile/build/app/outputs/flutter-apk/app-debug.apk`, **109,017,400 bytes**.

SHA-256: `1a716e80be4dd2522c1d0f7834a5cc0f0bbcf9dc42609743bda56d90b14c2f44`.

Offline APK inspection confirms package `com.infoteach.school` and x86_64 native support. The URL is a build-time define only. No live API request or emulator launch was performed. Existing Java native-access / Firebase-Kotlin future-compatibility notices are nonfatal; dependencies and signing configuration were not changed. This is a local debug validation artifact, not a production client distribution.

## Production-compatible diagnosis and one-time actions

**No migration, reseed, blanket backfill or one-time repair command is required.** Existing linked teachers need no data changes. Accounts lacking employment appear immediately after the updated API/client are used; an authorized administrator confirms actual employment facts once when their first assignment is saved. Existing payroll compensation checks still apply after a real employment link is created, as explained in the UI.

Optional read-only operator diagnostic after a separately authorized deployment:

```sh
cd /home/treadcom/info-tech-school-api
php artisan school:diagnose-teacher-selection --json
```

This reports only aggregate counts: active staff, Teacher-role accounts, eligible teachers, linked/missing profiles and exclusions for inactive account/profile or missing Teacher role. It emits no names, emails, IDs, credentials or employment details and performs no writes. There is no diagnostic HTTP route. It was tested only on local fixtures here.

Do not run `migrate:fresh`, `db:wipe`, bootstrap/reset seeders, production readable conversion or credential rotation for this fix. Production and its `.env`/APP_KEY were not touched. If a teacher's employment is intentionally inactive, review it through the existing authorized Staff Profile controls; this fix does not silently reactivate it.

Remaining practical limitation: required employment facts cannot safely be invented from a legacy account alone. Coordinators must obtain administrator confirmation for that first link. Live production data distribution and physical/emulator runtime behavior were not inspected; the build and complete local API/widget suites are the validation performed.

## Complete changed-file inventory

### Modified

- `api/app/Http/Controllers/TeachingAssignmentController.php`
- `api/app/Services/TeachingAssignments.php`
- `api/tests/Feature/ParentExperienceTest.php`
- `api/tests/Feature/RoleFunctionalReportsTest.php`
- `mobile/lib/features/administration/catalog_screen.dart`
- `mobile/lib/features/administration/staff_assignment_screen.dart`
- `mobile/lib/features/administration/teacher_assignment_batch_screen.dart`
- `mobile/lib/features/administration/teaching_assignments_screen.dart`
- `mobile/lib/l10n/strings.dart`
- `mobile/lib/shared/widgets/academic_filters.dart`
- `mobile/lib/shared/widgets/records_screen.dart`

### Created

- `api/app/Console/Commands/DiagnoseTeacherSelection.php`
- `api/tests/Feature/TeacherSelectionCompatibilityTest.php`
- `docs/final-teacher-assignment-analyze.txt`
- `docs/final-teacher-assignment-apk-build.txt`
- `docs/final-teacher-assignment-bootstrap.txt`
- `docs/final-teacher-assignment-conversion.txt`
- `docs/final-teacher-assignment-date-regressions.txt`
- `docs/final-teacher-assignment-fix.md`
- `docs/final-teacher-assignment-flutter-tests.txt`
- `docs/final-teacher-assignment-local-diagnostic.json`
- `docs/final-teacher-assignment-mariadb-tests.txt`
- `docs/final-teacher-assignment-migrations.txt`
- `docs/final-teacher-assignment-routes.json`
- `docs/final-teacher-assignment-targeted-tests.txt`
- `mobile/test/teacher_selection_fix_test.dart`

Ignored runtime/build outputs (APK, disposable database files and tool caches) are not source files to stage. No migration, dependency lockfile, production credential or school/company branding asset was changed.

## Exact manual Git commands

These are instructions only; staging, commit and push were **not executed**. Review all new files as well as the normal Git diff. The checked branch was `main`.

```powershell
Set-Location 'E:\info-tech-school-management'
git status --short
git diff --check
git diff --stat
git diff
git ls-files --others --exclude-standard
```

Only after your explicit authorization/review, stage these exact files and inspect the staged diff:

```powershell
git add -- "api/app/Console/Commands/DiagnoseTeacherSelection.php"
git add -- "api/app/Http/Controllers/TeachingAssignmentController.php"
git add -- "api/app/Services/TeachingAssignments.php"
git add -- "api/tests/Feature/ParentExperienceTest.php"
git add -- "api/tests/Feature/RoleFunctionalReportsTest.php"
git add -- "api/tests/Feature/TeacherSelectionCompatibilityTest.php"
git add -- "docs/final-teacher-assignment-analyze.txt"
git add -- "docs/final-teacher-assignment-apk-build.txt"
git add -- "docs/final-teacher-assignment-bootstrap.txt"
git add -- "docs/final-teacher-assignment-conversion.txt"
git add -- "docs/final-teacher-assignment-date-regressions.txt"
git add -- "docs/final-teacher-assignment-fix.md"
git add -- "docs/final-teacher-assignment-flutter-tests.txt"
git add -- "docs/final-teacher-assignment-local-diagnostic.json"
git add -- "docs/final-teacher-assignment-mariadb-tests.txt"
git add -- "docs/final-teacher-assignment-migrations.txt"
git add -- "docs/final-teacher-assignment-routes.json"
git add -- "docs/final-teacher-assignment-targeted-tests.txt"
git add -- "mobile/lib/features/administration/catalog_screen.dart"
git add -- "mobile/lib/features/administration/staff_assignment_screen.dart"
git add -- "mobile/lib/features/administration/teacher_assignment_batch_screen.dart"
git add -- "mobile/lib/features/administration/teaching_assignments_screen.dart"
git add -- "mobile/lib/l10n/strings.dart"
git add -- "mobile/lib/shared/widgets/academic_filters.dart"
git add -- "mobile/lib/shared/widgets/records_screen.dart"
git add -- "mobile/test/teacher_selection_fix_test.dart"
git diff --cached --check
git diff --cached --stat
git diff --cached
git commit -m "Fix teacher account selection and consolidate assignment management"
git push origin main
```

Review remote/branch policy and configured deployment hooks before a later push: the repository's existing `.cpanel.yml` can participate in hosting deployment. No `git add .`, reset, credential change or automatic deployment is needed for local review. Backend runtime changes are confined to the teacher assignment controller/service and the optional diagnostic command; update the Flutter client along with the reviewed API contract when separately deploying. Retain existing catalog routes and clear stale route/config caches through the established hosting process. No new migration is part of this release.

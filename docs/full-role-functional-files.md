# Changed files and operator instructions

This manifest covers the entire local role/settings continuation, including work present when the usage limit interrupted it. It was checked against the captured starting hashes and Git's working-tree inventory. No existing valid change was discarded. Files below are unstaged; none were committed, pushed or deployed.

## Created files

- `api/app/Http/Controllers/SchoolSettingsController.php`
- `api/app/Http/Controllers/TeacherWorkspaceController.php`
- `api/app/Http/Controllers/TeachingAssignmentController.php`
- `api/app/Services/Finance/BillingSnapshot.php`
- `api/app/Services/GradingRules.php`
- `api/app/Services/OperationalReports.php`
- `api/app/Services/SchoolSettings.php`
- `api/app/Services/TeachingAssignments.php`
- `api/database/migrations/2026_10_29_000020_add_scoped_assignment_permission.php`
- `api/tests/Feature/RoleFunctionalReportsTest.php`
- `api/tests/Feature/SettingsFunctionalTest.php`
- `api/tests/Feature/TeacherWorkspaceTest.php`
- `docs/full-role-baseline-tests.txt`
- `docs/full-role-bootstrap-tests.txt`
- `docs/full-role-conversion-tests.txt`
- `docs/full-role-flutter-analyze.txt`
- `docs/full-role-flutter-tests.txt`
- `docs/full-role-functional-audit.md`
- `docs/full-role-functional-files.md`
- `docs/full-role-mariadb-tests.txt`
- `docs/full-role-migration-status.txt`
- `docs/full-role-routes.json`
- `docs/settings-functional-audit.md`
- `docs/teacher-workflow.md`
- `mobile/lib/features/academics/assessment_form.dart`
- `mobile/lib/features/academics/teacher_workspace_screen.dart`
- `mobile/lib/features/administration/school_profile_screen.dart`
- `mobile/lib/features/administration/settings_hub_screen.dart`
- `mobile/lib/features/administration/teaching_assignments_screen.dart`
- `mobile/test/full_role_functional_test.dart`

## Modified files

- `api/app/Http/Controllers/AcademicSettingsController.php`
- `api/app/Http/Controllers/AssessmentController.php`
- `api/app/Http/Controllers/AttendanceController.php`
- `api/app/Http/Controllers/CatalogController.php`
- `api/app/Http/Controllers/DashboardController.php`
- `api/app/Http/Controllers/DiaryController.php`
- `api/app/Http/Controllers/FeeController.php`
- `api/app/Services/AcademicCalendar.php`
- `api/app/Services/Finance/FeeService.php`
- `api/app/Services/Finance/MonthlyBilling.php`
- `api/app/Services/ReportData.php`
- `api/app/Services/ReportRenderer.php`
- `api/app/Services/SchoolAccess.php`
- `api/database/seeders/MobilePermissionSeeder.php`
- `api/routes/academics.php`
- `api/routes/api.php`
- `api/tests/Feature/AdmissionFeeLifecycleTest.php`
- `mobile/lib/app/module_access.dart`
- `mobile/lib/features/academics/academic_hub_screen.dart`
- `mobile/lib/features/academics/academic_settings_screen.dart`
- `mobile/lib/features/academics/assessment_screen.dart`
- `mobile/lib/features/academics/attendance_screen.dart`
- `mobile/lib/features/academics/diary_editor.dart`
- `mobile/lib/features/academics/diary_screen.dart`
- `mobile/lib/features/administration/staff_assignment_screen.dart`
- `mobile/lib/features/auth/auth_controller.dart`
- `mobile/lib/features/home/widgets/account_sheet.dart`
- `mobile/lib/features/home/widgets/dashboard_overview.dart`
- `mobile/lib/features/home/widgets/module_card.dart`
- `mobile/lib/features/home/widgets/quick_actions.dart`
- `mobile/lib/features/reports/report_screen.dart`
- `mobile/lib/features/students/student_form.dart`
- `mobile/lib/l10n/strings.dart`
- `mobile/lib/shared/forms/domain_form.dart`
- `mobile/lib/shared/widgets/academic_filters.dart`
- `mobile/test/final_ui_polish_test.dart`
- `mobile/test/ui_redesign_test.dart`

## Migration and test scope

The only new migration is `api/database/migrations/2026_10_29_000020_add_scoped_assignment_permission.php`. It adds a narrow permission/grant and retains application data. Existing migrations, dependency lockfiles, `.env.example`, production credentials, vendor files and APP_KEY were not changed.

New PHP test classes cover safe settings round trips / validation / transaction rollback, scoped teacher and coordinator workflows, attendance/calendar/history, assignment migration preservation, report permissions/refunds/payroll totals and published parent visibility. Admission tests add billing snapshot equivalence and bounded query-count checks. New Flutter tests cover actual workspace, settings saves, attendance readonly state, future homework roster source, eligible teacher choice, coordinator assignment edits, exact password/boolean values and parent readonly info. Existing Flutter tests retain their assertions and account for the added teacher module / counts layout.

Complete results: MariaDB **249 tests, 2,534 assertions, zero failures/errors, 2 expected PostgreSQL-only skips**; Flutter **137 passed**; analyzer **no issues**; **147 routes**. Fresh/bootstrap, populated permission migration and readable conversion/finalization checks passed. See the accompanying audit and evidence files for details.

## Exact Git review / staging / commit / push instructions

These are instructions only. Do not execute staging, commit, push or deployment until explicitly authorized. Review existing remote/branch deployment hooks first: this repository contains `.cpanel.yml`, and a configured cPanel workflow could deploy after a later push. Pushing is not required for local review.

From PowerShell:

```powershell
Set-Location 'E:\info-tech-school-management'
git status --short
git diff --check
git diff --stat
git diff
git ls-files --others --exclude-standard
```

Git diff does not include untracked file contents; review every created file in the manifest too. The following explicitly stages only this reviewed continuation, not `.env`, local database files, SDK caches or vendor:

```powershell
git add -- "api/app/Http/Controllers/AcademicSettingsController.php"
git add -- "api/app/Http/Controllers/AssessmentController.php"
git add -- "api/app/Http/Controllers/AttendanceController.php"
git add -- "api/app/Http/Controllers/CatalogController.php"
git add -- "api/app/Http/Controllers/DashboardController.php"
git add -- "api/app/Http/Controllers/DiaryController.php"
git add -- "api/app/Http/Controllers/FeeController.php"
git add -- "api/app/Http/Controllers/SchoolSettingsController.php"
git add -- "api/app/Http/Controllers/TeacherWorkspaceController.php"
git add -- "api/app/Http/Controllers/TeachingAssignmentController.php"
git add -- "api/app/Services/AcademicCalendar.php"
git add -- "api/app/Services/Finance/BillingSnapshot.php"
git add -- "api/app/Services/Finance/FeeService.php"
git add -- "api/app/Services/Finance/MonthlyBilling.php"
git add -- "api/app/Services/GradingRules.php"
git add -- "api/app/Services/OperationalReports.php"
git add -- "api/app/Services/ReportData.php"
git add -- "api/app/Services/ReportRenderer.php"
git add -- "api/app/Services/SchoolAccess.php"
git add -- "api/app/Services/SchoolSettings.php"
git add -- "api/app/Services/TeachingAssignments.php"
git add -- "api/database/migrations/2026_10_29_000020_add_scoped_assignment_permission.php"
git add -- "api/database/seeders/MobilePermissionSeeder.php"
git add -- "api/routes/academics.php"
git add -- "api/routes/api.php"
git add -- "api/tests/Feature/AdmissionFeeLifecycleTest.php"
git add -- "api/tests/Feature/RoleFunctionalReportsTest.php"
git add -- "api/tests/Feature/SettingsFunctionalTest.php"
git add -- "api/tests/Feature/TeacherWorkspaceTest.php"
git add -- "docs/full-role-baseline-tests.txt"
git add -- "docs/full-role-bootstrap-tests.txt"
git add -- "docs/full-role-conversion-tests.txt"
git add -- "docs/full-role-flutter-analyze.txt"
git add -- "docs/full-role-flutter-tests.txt"
git add -- "docs/full-role-functional-audit.md"
git add -- "docs/full-role-functional-files.md"
git add -- "docs/full-role-mariadb-tests.txt"
git add -- "docs/full-role-migration-status.txt"
git add -- "docs/full-role-routes.json"
git add -- "docs/settings-functional-audit.md"
git add -- "docs/teacher-workflow.md"
git add -- "mobile/lib/app/module_access.dart"
git add -- "mobile/lib/features/academics/academic_hub_screen.dart"
git add -- "mobile/lib/features/academics/academic_settings_screen.dart"
git add -- "mobile/lib/features/academics/assessment_form.dart"
git add -- "mobile/lib/features/academics/assessment_screen.dart"
git add -- "mobile/lib/features/academics/attendance_screen.dart"
git add -- "mobile/lib/features/academics/diary_editor.dart"
git add -- "mobile/lib/features/academics/diary_screen.dart"
git add -- "mobile/lib/features/academics/teacher_workspace_screen.dart"
git add -- "mobile/lib/features/administration/school_profile_screen.dart"
git add -- "mobile/lib/features/administration/settings_hub_screen.dart"
git add -- "mobile/lib/features/administration/staff_assignment_screen.dart"
git add -- "mobile/lib/features/administration/teaching_assignments_screen.dart"
git add -- "mobile/lib/features/auth/auth_controller.dart"
git add -- "mobile/lib/features/home/widgets/account_sheet.dart"
git add -- "mobile/lib/features/home/widgets/dashboard_overview.dart"
git add -- "mobile/lib/features/home/widgets/module_card.dart"
git add -- "mobile/lib/features/home/widgets/quick_actions.dart"
git add -- "mobile/lib/features/reports/report_screen.dart"
git add -- "mobile/lib/features/students/student_form.dart"
git add -- "mobile/lib/l10n/strings.dart"
git add -- "mobile/lib/shared/forms/domain_form.dart"
git add -- "mobile/lib/shared/widgets/academic_filters.dart"
git add -- "mobile/test/final_ui_polish_test.dart"
git add -- "mobile/test/full_role_functional_test.dart"
git add -- "mobile/test/ui_redesign_test.dart"
git diff --cached --check
git diff --cached --stat
git diff --cached
git commit -m "Complete scoped school settings and teacher academic workflows"
git push origin main
```

The branch was `main` when this manifest was prepared. Confirm remote/branch policy before executing the final command. No `git add .` or destructive reset/checkout command is needed.

## Safe ordered cPanel instructions (not executed)

Use the actual PHP 8.2 CLI executable if `php` is not the PHP 8.2 CLI. Existing production API directory from repository deployment configuration is `/home/treadcom/info-tech-school-api`; confirm it is the intended instance before running commands. First take a database and application backup in the hosting tools and retain a release copy. Pause scheduled application writes / queue workers using the hosting controls. Do not print or replace `.env`, APP_KEY, Spext configuration or other credentials.

1. Enter maintenance before uploading the reviewed backend release:

```sh
cd /home/treadcom/info-tech-school-api
php artisan down
```

2. Upload the reviewed backend files from the manifest to their corresponding paths under this API directory. Include the new migration and services/controllers/routes. Preserve `.env`, `storage`, private uploads, existing `vendor`, APP_KEY and production credentials. No dependency changes are part of this continuation, so no Composer/subprocess install step is required. Flutter changes are a separately built client release, not PHP files to copy into the API directory. Do not invoke automatic Git deployment as an unreviewed substitute for this upload step.

3. After upload, clear stale caches, inspect migrations, apply pending migrations, then verify the actual routes:

```sh
cd /home/treadcom/info-tech-school-api
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
php artisan route:list --path=api/v1/school-settings
php artisan route:list --path=api/v1/school-information
php artisan route:list --path=api/v1/my-teaching
php artisan route:list --path=api/v1/teaching-assignments
php artisan route:list --path=api/v1/academic-roster
php artisan route:list | grep -i monthly
```

Stop and investigate any failure before proceeding. Migration status should include migration `000020`. Older pending migrations must be reviewed against the release and backup before the `migrate --force` step; do not assume a production database matches this local installation. New permission migration changes grants only, not guardian/student/finance data.

4. Bring the app back only after the checks pass, then resume scheduled writes/workers in hosting controls:

```sh
php artisan up
```

5. Manually smoke-test authorized production reads and ordinary settings/academic workflows using real roles: profile save/reload, scoped teacher class list, coordinator assignment scope, published parent results, existing monthly billing preview and finance read totals. Do not send test SMS or generate historical fees/payroll merely as a deployment check. Build/test/release the Flutter client through the established client process after backend availability.

`proc_open` and `shell_exec` are disabled on the stated host. These steps avoid `artisan about`, aggregate `optimize`, shell-dependent deployment helpers and dependency installation. Cache rebuilding is optional; leaving route/config caches cleared is valid. If an operator elects to run `route:cache` later, recheck every new route and use `route:clear` if stale routes appear.

Never run `migrate:fresh`, `db:wipe`, bootstrap/reset seeders or `school:convert-readable-data` on production as part of this deployment. Those destructive/conversion checks were restricted to the disposable local database. No production conversion or credential change is required by this continuation. Do not roll back the database automatically to recover a UI issue; preserve financial/academic writes and restore reviewed application files under maintenance as appropriate.

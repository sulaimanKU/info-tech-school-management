# Final teacher, profile and UI polish files

This inventory covers this continuation only, against the captured starting tracked-file hashes. The starting tree had one untracked file, `info-teach.apk`; it remains untouched and is excluded from staging instructions. No files were staged, committed, pushed or deployed.

## Created files

- `api/app/Services/StaffProfileData.php`
- `api/tests/Feature/MultipleTeachingAssignmentTest.php`
- `api/tests/Feature/StaffProfilePolishTest.php`
- `docs/final-teacher-profile-ui-analyze.txt`
- `docs/final-teacher-profile-ui-apk.txt`
- `docs/final-teacher-profile-ui-bootstrap.txt`
- `docs/final-teacher-profile-ui-conversion.txt`
- `docs/final-teacher-profile-ui-files.md`
- `docs/final-teacher-profile-ui-flutter.txt`
- `docs/final-teacher-profile-ui-mariadb.txt`
- `docs/final-teacher-profile-ui-migrations.txt`
- `docs/final-teacher-profile-ui-report.md`
- `docs/final-teacher-profile-ui-routes.json`
- `docs/final-teacher-profile-ui-targeted-api.txt`
- `mobile/android/app/src/main/res/drawable-night-v21/launch_background.xml`
- `mobile/android/app/src/main/res/drawable-night/launch_background.xml`
- `mobile/android/app/src/main/res/drawable/treadexellence_mark.xml`
- `mobile/assets/branding/README.md`
- `mobile/lib/features/administration/teacher_assignment_batch_screen.dart`
- `mobile/lib/shared/widgets/branded_startup.dart`
- `mobile/lib/shared/widgets/staff_assignment_summary.dart`
- `mobile/test/staff_profile_polish_test.dart`
- `mobile/test/startup_visual_polish_test.dart`
- `mobile/test/teacher_assignment_batch_test.dart`

## Modified files

- `api/app/Http/Controllers/CatalogController.php`
- `api/app/Http/Controllers/StaffAccountController.php`
- `api/app/Http/Controllers/StaffDirectoryController.php`
- `api/app/Http/Controllers/TeachingAssignmentController.php`
- `api/app/Services/TeachingAssignments.php`
- `api/routes/academics.php`
- `mobile/android/app/src/main/res/drawable-v21/launch_background.xml`
- `mobile/android/app/src/main/res/drawable/launch_background.xml`
- `mobile/android/app/src/main/res/values-night-v31/styles.xml`
- `mobile/android/app/src/main/res/values-night/styles.xml`
- `mobile/android/app/src/main/res/values-v31/styles.xml`
- `mobile/android/app/src/main/res/values/styles.xml`
- `mobile/lib/app/school_app.dart`
- `mobile/lib/design/app_theme.dart`
- `mobile/lib/design/semantic_tokens.dart`
- `mobile/lib/features/account/staff_account_screen.dart`
- `mobile/lib/features/account/staff_profile_editor.dart`
- `mobile/lib/features/administration/staff_assignment_screen.dart`
- `mobile/lib/features/administration/staff_screen.dart`
- `mobile/lib/features/administration/teaching_assignments_screen.dart`
- `mobile/lib/features/home/widgets/dashboard_overview.dart`
- `mobile/lib/l10n/strings.dart`
- `mobile/lib/main.dart`
- `mobile/lib/shared/widgets/academic_filters.dart`
- `mobile/lib/shared/widgets/data_state.dart`
- `mobile/lib/shared/widgets/records_screen.dart`
- `mobile/lib/shared/widgets/summary_card.dart`
- `mobile/lib/shared/widgets/surface_card.dart`

## Migration, assets and generated files

**No migration was needed.** Existing assignment/profile tables, indexes, migrations, readable identifiers, password policy and dependency lockfiles remain. Backend changes reuse the normalized assignment rows and existing profile endpoints.

New native assets are lightweight XML launch resources listed above. `mobile/assets/branding/README.md` documents the temporary TE typography mark and future approved logo path; no company logo was downloaded/generated. Existing school image assets are unchanged.

Generated local APK: `mobile/build/app/outputs/flutter-apk/app-release.apk` (unsigned validation only, deliberately invalid API define; not for distribution). It is an ignored build output, not a source file to stage. Disposable database logs/data and tool caches under ignored local/runtime paths are also not source changes. Full test/build/route evidence is included in the reviewed documentation inventory.

## Exact Git commands (instructions only)

Review first in PowerShell. Git's normal diff omits new file contents, so inspect every created source/test/asset/document above as well.

```powershell
Set-Location 'E:\info-tech-school-management'
git status --short
git diff --check
git diff --stat
git diff
git ls-files --others --exclude-standard
```

Only after explicit authorization to commit, the following commands stage precisely this task's files. They deliberately exclude `info-teach.apk`, release APKs, private configuration/keys, database directories and vendor caches. Review `.cpanel.yml` and remote deployment hooks before any later push because hosting integration may deploy automatically.

```powershell
git add -- "api/app/Http/Controllers/CatalogController.php"
git add -- "api/app/Http/Controllers/StaffAccountController.php"
git add -- "api/app/Http/Controllers/StaffDirectoryController.php"
git add -- "api/app/Http/Controllers/TeachingAssignmentController.php"
git add -- "api/app/Services/StaffProfileData.php"
git add -- "api/app/Services/TeachingAssignments.php"
git add -- "api/routes/academics.php"
git add -- "api/tests/Feature/MultipleTeachingAssignmentTest.php"
git add -- "api/tests/Feature/StaffProfilePolishTest.php"
git add -- "docs/final-teacher-profile-ui-analyze.txt"
git add -- "docs/final-teacher-profile-ui-apk.txt"
git add -- "docs/final-teacher-profile-ui-bootstrap.txt"
git add -- "docs/final-teacher-profile-ui-conversion.txt"
git add -- "docs/final-teacher-profile-ui-files.md"
git add -- "docs/final-teacher-profile-ui-flutter.txt"
git add -- "docs/final-teacher-profile-ui-mariadb.txt"
git add -- "docs/final-teacher-profile-ui-migrations.txt"
git add -- "docs/final-teacher-profile-ui-report.md"
git add -- "docs/final-teacher-profile-ui-routes.json"
git add -- "docs/final-teacher-profile-ui-targeted-api.txt"
git add -- "mobile/android/app/src/main/res/drawable-night-v21/launch_background.xml"
git add -- "mobile/android/app/src/main/res/drawable-night/launch_background.xml"
git add -- "mobile/android/app/src/main/res/drawable-v21/launch_background.xml"
git add -- "mobile/android/app/src/main/res/drawable/launch_background.xml"
git add -- "mobile/android/app/src/main/res/drawable/treadexellence_mark.xml"
git add -- "mobile/android/app/src/main/res/values-night-v31/styles.xml"
git add -- "mobile/android/app/src/main/res/values-night/styles.xml"
git add -- "mobile/android/app/src/main/res/values-v31/styles.xml"
git add -- "mobile/android/app/src/main/res/values/styles.xml"
git add -- "mobile/assets/branding/README.md"
git add -- "mobile/lib/app/school_app.dart"
git add -- "mobile/lib/design/app_theme.dart"
git add -- "mobile/lib/design/semantic_tokens.dart"
git add -- "mobile/lib/features/account/staff_account_screen.dart"
git add -- "mobile/lib/features/account/staff_profile_editor.dart"
git add -- "mobile/lib/features/administration/staff_assignment_screen.dart"
git add -- "mobile/lib/features/administration/staff_screen.dart"
git add -- "mobile/lib/features/administration/teacher_assignment_batch_screen.dart"
git add -- "mobile/lib/features/administration/teaching_assignments_screen.dart"
git add -- "mobile/lib/features/home/widgets/dashboard_overview.dart"
git add -- "mobile/lib/l10n/strings.dart"
git add -- "mobile/lib/main.dart"
git add -- "mobile/lib/shared/widgets/academic_filters.dart"
git add -- "mobile/lib/shared/widgets/branded_startup.dart"
git add -- "mobile/lib/shared/widgets/data_state.dart"
git add -- "mobile/lib/shared/widgets/records_screen.dart"
git add -- "mobile/lib/shared/widgets/staff_assignment_summary.dart"
git add -- "mobile/lib/shared/widgets/summary_card.dart"
git add -- "mobile/lib/shared/widgets/surface_card.dart"
git add -- "mobile/test/staff_profile_polish_test.dart"
git add -- "mobile/test/startup_visual_polish_test.dart"
git add -- "mobile/test/teacher_assignment_batch_test.dart"
git diff --cached --check
git diff --cached --stat
git diff --cached
git commit -m "Polish multi-assignment teaching, staff profiles and startup UI"
git push origin main
```

Branch was `main` when checked. Verify the intended remote/branch policy before executing push. These commands were not run. Do not use `git add .` to sweep in the unrelated APK.

## Safe ordered cPanel steps (instructions only)

No production access or deployment was performed. First back up the production database/application through hosting controls and retain the previous release. Confirm the configured API directory `/home/treadcom/info-tech-school-api` and PHP 8.2 CLI are correct for the intended instance. Pause scheduled application writes/queue workers through hosting controls before maintenance.

1. Enter maintenance:

```sh
cd /home/treadcom/info-tech-school-api
php artisan down
```

2. Upload only the reviewed backend runtime files:

```text
api/app/Http/Controllers/CatalogController.php -> app/Http/Controllers/CatalogController.php
api/app/Http/Controllers/StaffAccountController.php -> app/Http/Controllers/StaffAccountController.php
api/app/Http/Controllers/StaffDirectoryController.php -> app/Http/Controllers/StaffDirectoryController.php
api/app/Http/Controllers/TeachingAssignmentController.php -> app/Http/Controllers/TeachingAssignmentController.php
api/app/Services/TeachingAssignments.php -> app/Services/TeachingAssignments.php
api/app/Services/StaffProfileData.php -> app/Services/StaffProfileData.php
api/routes/academics.php -> routes/academics.php
```

Preserve production `.env`, APP_KEY, all existing credentials, `vendor`, storage and private files. No Composer/package install, new migration or readable-data conversion is part of this release. Flutter/native files belong to a separately built, signed client release, not the PHP API directory.

3. Clear stale caches and inspect configuration-independent routes/migration status:

```sh
cd /home/treadcom/info-tech-school-api
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan migrate:status
php artisan route:list --path=api/v1/teaching-assignments
php artisan route:list --path=api/v1/account/profile
php artisan route:list --path=api/v1/staff-accounts
php artisan route:list --path=api/v1/my-teaching
```

Verify both GET and POST teaching-assignments routes and their existing authenticated middleware. There is no new migration to apply. If status reveals older pending migrations, stop and review those separately against backups/release history rather than automatically applying unrelated changes.

4. After successful checks, restore service and resume workers/scheduled writes through hosting controls:

```sh
php artisan up
```

5. Use authorized accounts to smoke-test ordinary assignment/profile reads and safe intended edits: admin batch creation, coordinator own-section restrictions, teacher multi-section workspace and self profile save/photo reload. Confirm existing finance/billing reads remain available. Do not send real test SMS, fabricate production school data or generate historical bills/payroll as a deployment check. Client distribution requires the approved production API define and private signing configuration; the local unsigned validation APK must not be distributed.

These steps work within the stated hosting restrictions where `proc_open`/`shell_exec` are disabled; they avoid `artisan about`, aggregate `optimize`, shell-dependent deployment helpers and dependency installation. Leaving route/config caches cleared is valid. Never run `migrate:fresh`, `db:wipe`, bootstrap/reset seeders, production readable conversion or credential/key rotation for this polish release. On an application issue, retain financial/academic data and restore reviewed runtime files under maintenance; no automatic database rollback is warranted.

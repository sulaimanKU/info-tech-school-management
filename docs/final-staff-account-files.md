# Staff account files

Existing project only. Existing files are compared with the clean request-entry working tree. No Parent Experience screen, OTP/auth controller or .env was edited.

## Changed existing files

| File | Purpose |
|---|---|
| [api/app/Http/Controllers/PayrollController.php](../api/app/Http/Controllers/PayrollController.php) | Preserves my-staff-profile URL; delegates owner-only paid slips and handles missing employment links without 404. |
| [api/app/Http/Controllers/PrivateFileController.php](../api/app/Http/Controllers/PrivateFileController.php) | Adds current, owner-only staff photo reads; existing student/guardian checks remain. |
| [api/app/Models/User.php](../api/app/Models/User.php) | Central isStaff role check using existing role assignments. |
| [api/app/Services/ProfilePhotoService.php](../api/app/Services/ProfilePhotoService.php) | Reuses validated/re-encoded private image pipeline for staff-owned user photos. |
| [api/routes/api.php](../api/routes/api.php) | Registers authenticated own-account/profile/password/photo and owner-only salary document endpoints. |
| [api/tests/Support/MobileValidationSeeder.php](../api/tests/Support/MobileValidationSeeder.php) | Completes fictional employment links for all five staff roles and calls guarded salary fixtures. |
| [mobile/lib/features/files/private_file_screen.dart](../mobile/lib/features/files/private_file_screen.dart) | Optional feature error builder; existing viewers retain their behavior. |
| [mobile/lib/features/finance/payroll_screen.dart](../mobile/lib/features/finance/payroll_screen.dart) | Keeps MyStaffProfileScreen compatibility entry point and routes it to Profile & Account. |
| [mobile/lib/features/home/widgets/account_sheet.dart](../mobile/lib/features/home/widgets/account_sheet.dart) | Navigates staff to the new own-account screen; parent navigation stays unchanged. |
| [mobile/lib/l10n/strings.dart](../mobile/lib/l10n/strings.dart) | Localized account, password, salary, phone/date and actionable error labels in English/Urdu. |
| [mobile/lib/shared/forms/profile_photo_picker.dart](../mobile/lib/shared/forms/profile_photo_picker.dart) | Reuses preview/change/remove and temporary-file cleanup for staff photo endpoint. |
| [README.md](../README.md) | Links staff account completion and linking instructions. |

## Created files

| File | Purpose |
|---|---|
| [api/app/Http/Controllers/StaffAccountController.php](../api/app/Http/Controllers/StaffAccountController.php) | Own identity allowlist, nullable employment, safe edits, current-password validation/session revocation, private photos and salary ownership. |
| [api/database/migrations/2026_10_26_000013_add_staff_account_fields.php](../api/database/migrations/2026_10_26_000013_add_staff_account_fields.php) | Nullable users.mobile/address/photo reference and private_files.owner_user_id foreign key; no synthetic employment data. |
| [api/tests/Feature/StaffAccountTest.php](../api/tests/Feature/StaffAccountTest.php) | Eight PostgreSQL profile, role, edit, password, session, photo and paid-document regressions. |
| [api/tests/Support/StaffAccountValidationSeeder.php](../api/tests/Support/StaffAccountValidationSeeder.php) | Local test-database-only fictional paid salary records for emulator checks. |
| [mobile/lib/features/account/staff_account_screen.dart](../mobile/lib/features/account/staff_account_screen.dart) | Own profile/header, official details, salary/security sections, resource/error states and logout/expiry route cleanup. |
| [mobile/lib/features/account/staff_profile_editor.dart](../mobile/lib/features/account/staff_profile_editor.dart) | Safe personal details and existing photo-picker integration with validation and disabled/loading save. |
| [mobile/lib/features/account/change_password_screen.dart](../mobile/lib/features/account/change_password_screen.dart) | Hidden current/new/confirmation fields, validation, safe API submission and sign-out/re-login feedback. |
| [mobile/test/staff_account_test.dart](../mobile/test/staff_account_test.dart) | Sixteen additional tests covering five roles, edits, password validation, empty/unlinked/error states, localization and responsive Urdu dark layouts. |
| [mobile/integration_test/staff_workflows_test.dart](../mobile/integration_test/staff_workflows_test.dart) | Real five-role Android account navigation, teacher edit/photo, salary IDOR/PDF, password and RTL workflow. |
| [mobile/test_driver/staff_driver.dart](../mobile/test_driver/staff_driver.dart) | Stores named fictional-data account screenshots. |
| [docs/final-staff-account-report.md](../docs/final-staff-account-report.md) | This complete twenty-part implementation/validation report. |
| [docs/final-staff-account-files.md](../docs/final-staff-account-files.md) | Complete changed/created source/document manifest. |

## Generated artifacts

- Ten fictional-data Android screenshots: `docs/screenshots/final-staff-account/`.
- Normal phone APK: `mobile/build/app/outputs/flutter-apk/app-debug.apk` (build artifact).
- Development migration applied once; no default account/employee was seeded into development.

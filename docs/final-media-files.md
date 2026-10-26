# Files changed/created in the OTP/photos/visual pass

Request-entry SHA-256 inventory comparison (repository source was already untracked; these are not Git-commit diff statuses). api/serve-local.ps1 explicitly tracked as modified existing root script. Build/vendor/cache files, private keys, runtime logs/test private uploads and .env are excluded; no .env secret was changed. Every changed/created source/document file follows.

| File | Status | Reason |
| --- | --- | --- |
| `api/app/Http/Controllers/GuardianController.php` | Changed | Serialize authorized photo reference and support optional create photo in existing guardian transaction. |
| `api/app/Http/Controllers/PrivateFileController.php` | Changed | Reuse files upload for both profile owners, current-photo authorization and scoped removal endpoints. |
| `api/app/Http/Controllers/StudentController.php` | Changed | Return list photo references and accept optional validated multipart creation photo. |
| `api/app/Models/User.php` | Changed | Include guardian photo reference in existing authenticated profile serialization. |
| `api/app/Services/ProfilePhotoService.php` | Created | Shared scoped image validation, normalization, UUID private storage, replacement/current-reference removal. |
| `api/app/Services/Sms/LocalSmsProvider.php` | Changed | Reject failed local cache delivery, use configured expiry and retain encrypted local-only simulator. |
| `api/database/migrations/2026_10_23_000012_add_guardian_profile_photos.php` | Created | Add two nullable UUID foreign references using existing private-files schema. |
| `api/routes/console.php` | Changed | Allow optional latest eligible local OTP retrieval while retaining explicit local/provider guards. |
| `api/routes/mobile.php` | Changed | Register authenticated student/guardian photo removal routes in the existing API group. |
| `api/serve-local.ps1` | Changed | Explicit local simulator process settings and extension-correct direct server startup; no .env edit. |
| `api/tests/Feature/ParentOtpTest.php` | Changed | Regression coverage for inactive/normalized/resend/logout/production/failing delivery/local CLI cases. |
| `api/tests/Feature/PrivateFileTest.php` | Changed | Use decode-valid success fixture while retaining existing authorization checks; corrupt original has rejection coverage. |
| `api/tests/Feature/ProfilePhotoTest.php` | Created | Cover optional create, replacement/removal, private retrieval/IDOR, normalization and unsafe/corrupt uploads. |
| `deployment/README.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/api-route-inventory.json` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/api.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/database-schema.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/final-auth-dashboard-report.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/final-media-files.md` | Created | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/final-media-report.md` | Created | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/final-rbac-report.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/implementation-status.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/local-parent-otp.md` | Created | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/mobile-architecture.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/security-review.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `docs/validation.md` | Changed | Update current contracts, route inventory, validation evidence or final handover documentation. |
| `mobile/integration_test/media_profile_workflows_test.dart` | Created | Fictional real-device private photo/OTP integration with asynchronous render waits. |
| `mobile/lib/core/api_client.dart` | Changed | Bounded private-file transport recovery; preserve total timeout and no write/auth replay. |
| `mobile/lib/design/app_theme.dart` | Changed | Central royal-blue/teal/indigo identity, cool canvas and intentional dark equivalents. |
| `mobile/lib/design/semantic_tokens.dart` | Changed | Semantic education/attendance/finance/communication accents shared by modules and actions. |
| `mobile/lib/features/auth/login_screen.dart` | Changed | Staff/Parent icon selector and isolation of stale auth feedback on flow switch. |
| `mobile/lib/features/auth/widgets/parent_login_form.dart` | Changed | Resend validation fix, expiry/correct-identity flow and localized feedback/card treatment. |
| `mobile/lib/features/auth/widgets/staff_login_form.dart` | Changed | Brand-aligned tinted staff authentication card; preserve working auth controls. |
| `mobile/lib/features/home/widgets/module_card.dart` | Changed | Apply centralized function accents to existing permission-driven modules. |
| `mobile/lib/features/home/widgets/quick_actions.dart` | Changed | Tinted semantic action tiles and permission-checked guardian creation shortcut. |
| `mobile/lib/features/home/widgets/welcome_panel.dart` | Changed | Consistent branded dashboard welcome surface. |
| `mobile/lib/features/parents/parent_home_screen.dart` | Changed | Render authorized guardian photo alongside existing child cards. |
| `mobile/lib/features/students/guardian_directory_screen.dart` | Changed | Optional create/edit photo, directory/detail avatars and saved-ID retry behavior. |
| `mobile/lib/features/students/student_detail_screen.dart` | Changed | Render current profile photo/fallback and open preview-based later photo editor. |
| `mobile/lib/features/students/student_form.dart` | Changed | Profile/contact/academic sections, optional photo save and saved-ID reuse on retry. |
| `mobile/lib/features/students/student_list_screen.dart` | Changed | Render authorized current photo/initials in student rows. |
| `mobile/lib/l10n/strings.dart` | Changed | English/Urdu parity for new profile/photo/OTP/brand labels. |
| `mobile/lib/shared/forms/domain_form.dart` | Changed | Reusable optional photo field and section headings over existing form behavior. |
| `mobile/lib/shared/forms/profile_photo_picker.dart` | Created | Optional compressed picker preview/change/remove, temporary-file cleanup and shared photo save helper. |
| `mobile/lib/shared/widgets/authorized_photo.dart` | Changed | Authenticated avatar loading, fallback/initials/retry and stale-request guard with display-size decoding. |
| `mobile/lib/shared/widgets/records_screen.dart` | Changed | Optional reusable per-record leading avatar without changing pagination/search filters. |
| `mobile/lib/shared/widgets/school_brand.dart` | Changed | Original logo/campus hero with controlled overlay and localized campus slogan. |
| `mobile/lib/shared/widgets/summary_card.dart` | Changed | Apply semantic accents to existing real-data KPI cards. |
| `mobile/lib/shared/widgets/surface_card.dart` | Changed | Reusable restrained accent-tinted surface and border without per-screen color literals. |
| `mobile/test/media_otp_test.dart` | Created | Widget/unit regressions for optional photos, private avatars, parent resend/expiry/errors/restore and RTL. |
| `mobile/test/media_transport_test.dart` | Created | Prove bounded truncated-read recovery and no authorization/server/write retries. |
| `mobile/test_driver/media_driver.dart` | Created | Save controlled-name native media screenshots as validation evidence. |

## Generated/refreshed evidence

- `docs/screenshots/final-media/guardian-private-photo.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/parent-color-login.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/parent-otp-step.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/parent-private-photo-home.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/staff-color-login.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/student-create-optional-photo.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/student-private-photo.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/super-admin-color-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-media/urdu-dark-auth.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/accountant-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/accountant-invoices.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/accountant-reports.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/administrator-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/administrator-staff.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/attendance-confirmation.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/attendance.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/child-switch-beta.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/child-workspace.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/communication-internal-note.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/communication.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/coordinator-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/coordinator-scoped-overview.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/dark-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/diary-editor.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/error-state.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/fee-voucher.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/finance.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/guardian-directory.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/imports-empty.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/light-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/loading-state.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/login.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/parent-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/parent-finance.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/parent-homework.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/parent-login.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/permission-matrix.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/reports.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/restored-session.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/staff-directory.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/student-filter-sheet.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/super-admin-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/teacher-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/urdu-dark-dashboard.png` — native fictional-data screenshot.
- `docs/screenshots/final-dashboard/urdu-dark-login.png` — native fictional-data screenshot.

**Build succeeded.** Physical-phone debug APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk`. API_BASE_URL: `http://192.168.100.107:8000/api/v1`; Wi-Fi address checked immediately before build. Size 228,120,402 bytes; SHA-256 `03ee846531b95b637e9de4154c5f0f47c315a9687df6978beaf17ce50fad4bc7`. Kernel inspection confirms the requested URL and normal main entry rather than an integration-test entry. Separate normal-entry emulator artifact: `mobile/build/app/outputs/flutter-apk/app-debug-emulator.apk`. No physical handset was connected or tested.

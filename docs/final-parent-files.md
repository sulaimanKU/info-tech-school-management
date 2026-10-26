# Parent Experience files

2026-10-26. This manifest covers the interrupted Parent Experience work plus its continuation, compared with the request-entry SHA-256 inventory. The repository was already entirely untracked; no commit or unrelated rollback was made. Existing import templates/logs are not attributed to this pass.

## Changed existing files

| File | Purpose |
|---|---|
| [api/app/Http/Controllers/AssessmentController.php](../api/app/Http/Controllers/AssessmentController.php) | Preserves published/scoped results; supplies parent assessment metadata and selector response. |
| [api/app/Http/Controllers/DiaryController.php](../api/app/Http/Controllers/DiaryController.php) | Shared selected-child query for lists, overview and optional child-scoped detail reads. |
| [api/app/Http/Controllers/ParentHomeController.php](../api/app/Http/Controllers/ParentHomeController.php) | Delegates batched child summaries; private/no-store response. |
| [api/app/Services/AcademicSummary.php](../api/app/Services/AcademicSummary.php) | Batches monthly attendance, enrollment/calendar reads; counts, holidays and compatible record objects. |
| [api/app/Services/ReportData.php](../api/app/Services/ReportData.php) | Existing academic PDF/CSV gains selected assessment, session/class, grade, publication and teacher metadata. |
| [api/app/Services/Finance/InvoiceBalance.php](../api/app/Services/Finance/InvoiceBalance.php) | Shared calculation with batched adjustments, verified payments and refunds. |
| [api/routes/mobile.php](../api/routes/mobile.php) | Authenticated UUID-constrained selected-child overview route. |
| [api/tests/Support/MobileValidationSeeder.php](../api/tests/Support/MobileValidationSeeder.php) | Calls guarded parent fixtures after existing fictional mobile fixtures. |
| [mobile/lib/features/academics/diary_screen.dart](../mobile/lib/features/academics/diary_screen.dart) | Selected-child subject choices/detail context and friendly subject-filter errors. |
| [mobile/lib/features/finance/student_fee_screen.dart](../mobile/lib/features/finance/student_fee_screen.dart) | Shows existing voucher due-date and balance metadata before document navigation. |
| [mobile/lib/features/parents/child_workspace_screen.dart](../mobile/lib/features/parents/child_workspace_screen.dart) | Persistent child selector/profile; compact five destinations; keyed module state reset. |
| [mobile/lib/features/parents/parent_home_screen.dart](../mobile/lib/features/parents/parent_home_screen.dart) | Keeps real linked-child cards; removes duplicate identity panel; adds roll and clears sensitive data on authorization errors. |
| [mobile/lib/l10n/strings.dart](../mobile/lib/l10n/strings.dart) | English/Urdu parent labels using existing localization. |
| [README.md](../README.md) | Links this parent completion report and file manifest. |

## Created files

| File | Purpose |
|---|---|
| [api/app/Http/Controllers/ParentChildController.php](../api/app/Http/Controllers/ParentChildController.php) | Small authorized overview composing existing diary/announcement queries. |
| [api/app/Services/ParentAcademics.php](../api/app/Services/ParentAcademics.php) | Published assessment grouping, weighted totals, session grades and conservative comparable trends. |
| [api/app/Services/ParentChildren.php](../api/app/Services/ParentChildren.php) | Batched linked-child summaries with real enrollment, attendance, fees and latest published subject result. |
| [api/tests/Feature/ParentExperienceTest.php](../api/tests/Feature/ParentExperienceTest.php) | Seven additional PostgreSQL parent, publication, finance/report, IDOR, shared-guardian and query regressions. |
| [api/tests/Support/ParentValidationSeeder.php](../api/tests/Support/ParentValidationSeeder.php) | Different fictional results, attendance, balances, homework and behavior for two children; guarded test database only. |
| [mobile/lib/features/parents/child_profile_card.dart](../mobile/lib/features/parents/child_profile_card.dart) | Reusable private-photo/fallback profile with registration and enrollment. |
| [mobile/lib/features/parents/parent_resource.dart](../mobile/lib/features/parents/parent_resource.dart) | Read-only loading/error/retry/refresh; clears context and rejects late responses from previous requests. |
| [mobile/lib/features/parents/parent_overview.dart](../mobile/lib/features/parents/parent_overview.dart) | Actual child snapshot, recent homework, announcements and quick actions. |
| [mobile/lib/features/parents/parent_academics_screen.dart](../mobile/lib/features/parents/parent_academics_screen.dart) | Published selector, weighted summary, subject cards/remarks, protected report and actual trend bars. |
| [mobile/lib/features/parents/parent_attendance_screen.dart](../mobile/lib/features/parents/parent_attendance_screen.dart) | Month navigation, attendance counts, provisional summary, daily records/holidays and authorized report. |
| [mobile/test/parent_experience_test.dart](../mobile/test/parent_experience_test.dart) | Fifteen additional widget tests: context switching, stale responses, retry, selector, months, one child, empty home and responsive Urdu layouts. |
| [mobile/integration_test/parent_workflows_test.dart](../mobile/integration_test/parent_workflows_test.dart) | Real Android parent OTP, both children/modules, coordinator follow-up, IDOR, preferences, session restoration and logout. |
| [mobile/test_driver/parent_driver.dart](../mobile/test_driver/parent_driver.dart) | Writes only named parent workflow screenshots. |
| [docs/final-parent-report.md](../docs/final-parent-report.md) | Complete continuation report, evidence, limitations and production checklist. |
| [docs/final-parent-files.md](../docs/final-parent-files.md) | This complete source/document manifest. |

## Generated artifacts

- `docs/screenshots/final-parent/`: 16 Android workflow screenshots using fictional data.
- `mobile/build/app/outputs/flutter-apk/app-debug.apk`: normal debug application for current laptop Wi-Fi API; build output, not a source file.

Official assets, OTP/authentication/session services, existing RBAC, migrations, `.env` and external credentials were not changed.

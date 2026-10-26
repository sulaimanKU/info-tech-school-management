# Cumulative RBAC file inventory

Compared with request-entry SHA-256 source inventory `rbac-before`. Repository files were initially untracked; status here means relative to that inspected baseline, not a Git commit. Deployment and driver files are explicitly included because those directories were outside snapshot coverage. Build caches, vendor files, private validation key, .env and generated images/APKs are excluded. Unchanged authentication/session files are not listed merely because inspected.

| File | Status | Reason |
| --- | --- | --- |
| `api/app/Http/Controllers/AnnouncementController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/AssessmentController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/AttendanceController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/BehaviorController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/CatalogController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/CoordinatorController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/DashboardController.php` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `api/app/Http/Controllers/DiaryController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/ExamScheduleController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/FeeController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/GuardianController.php` | Changed | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `api/app/Http/Controllers/ImportController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/PaymentController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/PrivateFileController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/RbacController.php` | Created | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `api/app/Http/Controllers/StaffDirectoryController.php` | Changed | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `api/app/Http/Controllers/StudentController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Http/Controllers/TicketController.php` | Changed | Intersect existing domain reads/writes, filters and direct-object access with current role scope. |
| `api/app/Models/User.php` | Changed | Reuse existing role relationships for active Super bypass and current permissions. |
| `api/app/Services/AcademicFilters.php` | Created | Central reusable server-scoped filtering/access and safe paginated presentation. |
| `api/app/Services/AcademicSummary.php` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `api/app/Services/AnnouncementAccess.php` | Changed | Central reusable server-scoped filtering/access and safe paginated presentation. |
| `api/app/Services/NotificationService.php` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `api/app/Services/ReportData.php` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `api/app/Services/SchoolAccess.php` | Changed | Central reusable server-scoped filtering/access and safe paginated presentation. |
| `api/app/Services/TicketAccess.php` | Changed | Central reusable server-scoped filtering/access and safe paginated presentation. |
| `api/database/migrations/2026_10_22_000011_refine_staff_scopes.php` | Created | Extend existing assignments/verified links/targeting and least-privilege grants without replacing RBAC. |
| `api/database/seeders/MobilePermissionSeeder.php` | Changed | Align existing default role permission grants with least-privilege scopes. |
| `api/routes/mobile.php` | Changed | Register authorized management/filter/dashboard endpoints within existing API architecture. |
| `api/tests/Feature/AcademicWorkflowTest.php` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `api/tests/Feature/AuthTest.php` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `api/tests/Feature/CommunicationTest.php` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `api/tests/Feature/DashboardTest.php` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `api/tests/Feature/RbacFilterRegressionTest.php` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `api/tests/Feature/RbacScopeTest.php` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `api/tests/Feature/ReportsTest.php` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `api/tests/Support/MobileTestCase.php` | Changed | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `api/tests/Support/MobileValidationSeeder.php` | Changed | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `api/tests/Support/diagnose-database-driver.php` | Created | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `api/tests/Support/diagnose-login-operation.php` | Created | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `api/tests/Support/rbac-development-state.php` | Created | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `api/tests/Support/validate-local-auth.php` | Created | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `deployment/README.md` | Changed | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/inject-validation-otp.ps1` | Changed | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/serve-api.ps1` | Created | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/start-mobile-validation.ps1` | Changed | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/test-backend.ps1` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `deployment/validate-mobile-dashboard.ps1` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `deployment/validation-key.ps1` | Created | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `docs/final-auth-dashboard-report.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `docs/final-rbac-report.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `docs/final-ui-backend-tests.log` | Created | Update implementation contract, verification evidence or handover documentation. |
| `docs/rbac-before-audit.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `docs/rbac-matrix.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `mobile/integration_test/final_dashboard_workflows_test.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/integration_test/final_rbac_workflows_test.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/integration_test/session_restart_test.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/lib/app/module_access.dart` | Created | Central reusable server-scoped filtering/access and safe paginated presentation. |
| `mobile/lib/app/school_app.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/design/semantic_tokens.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/features/academics/assessment_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/academics/attendance_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/academics/diary_editor.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/academics/diary_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/academics/exam_schedule_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/academics/student_academic_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/administration/catalog_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/administration/rbac_screen.dart` | Created | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `mobile/lib/features/administration/staff_assignment_screen.dart` | Created | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `mobile/lib/features/administration/staff_screen.dart` | Created | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `mobile/lib/features/auth/auth_controller.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/auth/widgets/staff_login_form.dart` | Changed | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `mobile/lib/features/communication/announcement_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/communication/ticket_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/finance/student_fee_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/home/home_screen.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/features/home/widgets/account_sheet.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/home/widgets/dashboard_overview.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/lib/features/home/widgets/module_card.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/home/widgets/quick_actions.dart` | Created | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/features/home/widgets/welcome_panel.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/features/parents/child_workspace_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/reports/report_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/students/guardian_directory_screen.dart` | Created | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `mobile/lib/features/students/guardian_screen.dart` | Changed | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `mobile/lib/features/students/student_detail_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/features/students/student_list_screen.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/l10n/strings.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/shared/forms/domain_form.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/shared/widgets/academic_filters.dart` | Created | Central reusable server-scoped filtering/access and safe paginated presentation. |
| `mobile/lib/shared/widgets/records_screen.dart` | Changed | Central reusable server-scoped filtering/access and safe paginated presentation. |
| `mobile/lib/shared/widgets/school_brand.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/shared/widgets/summary_card.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/test/dashboard_test.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/test/finance_mobile_test.dart` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/form_polish_test.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/rbac_filters_test.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/support/auth_fixtures.dart` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/ui_redesign_test.dart` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/widget_test.dart` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test_driver/final_dashboard_driver.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/test_driver/final_rbac_driver.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `docs/api-route-inventory.json` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/api.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/database-schema.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/final-auth-dashboard-files.md` | Created | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/final-rbac-files.md` | Created | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/final-validation-evidence.md` | Created | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/implementation-status.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/mobile-architecture.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/validation.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |

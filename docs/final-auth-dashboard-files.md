# Focused authentication/dashboard file inventory

Compared with request-entry SHA-256 source inventory `login-before`. Repository files were initially untracked; status here means relative to that inspected baseline, not a Git commit. Deployment and driver files are explicitly included because those directories were outside snapshot coverage. Build caches, vendor files, private validation key, .env and generated images/APKs are excluded. Unchanged authentication/session files are not listed merely because inspected.

| File | Status | Reason |
| --- | --- | --- |
| `api/app/Http/Controllers/DashboardController.php` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `api/routes/mobile.php` | Changed | Register authorized management/filter/dashboard endpoints within existing API architecture. |
| `api/tests/Feature/AuthTest.php` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `api/tests/Feature/DashboardTest.php` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `api/tests/Support/diagnose-database-driver.php` | Created | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `api/tests/Support/diagnose-login-operation.php` | Created | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `api/tests/Support/validate-local-auth.php` | Created | Guarded local diagnostic or fictional isolated validation support; no production credentials. |
| `deployment/README.md` | Changed | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/inject-validation-otp.ps1` | Changed | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/serve-api.ps1` | Created | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/start-mobile-validation.ps1` | Changed | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `deployment/test-backend.ps1` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `deployment/validate-mobile-dashboard.ps1` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `deployment/validation-key.ps1` | Created | Run extension-correct API or safely isolated validation with caller environment restoration. |
| `docs/final-auth-dashboard-report.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `docs/final-rbac-files.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `docs/final-rbac-report.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `docs/rbac-matrix.md` | Created | Update implementation contract, verification evidence or handover documentation. |
| `mobile/integration_test/final_dashboard_workflows_test.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/integration_test/session_restart_test.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/lib/design/semantic_tokens.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/features/auth/widgets/staff_login_form.dart` | Changed | Existing staff/guardian/assignment administration with escalation protection and scoped UI/API. |
| `mobile/lib/features/home/home_screen.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/features/home/widgets/dashboard_overview.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/lib/features/home/widgets/quick_actions.dart` | Created | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/features/home/widgets/welcome_panel.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/l10n/strings.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/shared/forms/domain_form.dart` | Changed | Focused centralized UI polish, role actions, compact branding or field-level feedback. |
| `mobile/lib/shared/widgets/school_brand.dart` | Changed | Integrate current permission/scope profile and reusable management/filter UI without replacing existing workflows. |
| `mobile/lib/shared/widgets/summary_card.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/test/dashboard_test.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/test/finance_mobile_test.dart` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/form_polish_test.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/ui_redesign_test.dart` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test/widget_test.dart` | Changed | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `mobile/test_driver/final_dashboard_driver.dart` | Created | Implement/verify permission-scoped real-data dashboard and responsive summary presentation. |
| `mobile/test_driver/final_rbac_driver.dart` | Created | Add/update authorization, filter, authentication, UI or native workflow regression validation. |
| `docs/api-route-inventory.json` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/api.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/database-schema.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/final-auth-dashboard-files.md` | Created | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/final-validation-evidence.md` | Created | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/implementation-status.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/mobile-architecture.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |
| `docs/validation.md` | Changed | Current authorization contract, route inventory, verification evidence or final handover documentation. |

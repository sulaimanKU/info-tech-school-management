# Admission fee lifecycle file inventory

Compared with content hashes captured at the start of this request. Existing earlier work is excluded. No commit or push. Ignored database runtime/test caches are excluded.

## Created (12)

- `api/app/Console/Commands/GenerateMonthlyFees.php`
- `api/app/Services/Finance/AdmissionFinance.php`
- `api/app/Services/Finance/MonthlyBilling.php`
- `api/database/migrations/2026_10_28_000018_add_admission_requests.php`
- `api/tests/Feature/AdmissionFeeLifecycleTest.php`
- `docs/admission-encryption-audit.txt`
- `docs/admission-fee-lifecycle-files.md`
- `docs/admission-fee-lifecycle-mariadb-tests.txt`
- `docs/admission-fee-lifecycle-report.md`
- `docs/admission-storage-inventory.md`
- `docs/admission-storage-schema.json`
- `mobile/test/admission_fee_lifecycle_test.dart`

## Modified (19)

- `api/app/Http/Controllers/DashboardController.php`
- `api/app/Http/Controllers/FeeController.php`
- `api/app/Http/Controllers/StudentController.php`
- `api/app/Services/Finance/FeeService.php`
- `api/app/Services/Finance/PaymentService.php`
- `api/app/Services/Finance/StudentFinance.php`
- `api/app/Services/ReportData.php`
- `api/routes/finance.php`
- `api/tests/Feature/FinalDocumentValidationTest.php`
- `docs/database-schema.md`
- `docs/production-configuration.md`
- `docs/rbac-matrix.md`
- `mobile/lib/features/finance/finance_summary_screen.dart`
- `mobile/lib/features/finance/financial_forms.dart`
- `mobile/lib/features/finance/invoice_screen.dart`
- `mobile/lib/features/finance/student_fee_screen.dart`
- `mobile/lib/features/home/widgets/dashboard_overview.dart`
- `mobile/lib/features/students/student_form.dart`
- `mobile/lib/l10n/strings.dart`

Ignored local validation helpers/logs under .local-db-audit and normal Flutter/PHPUnit caches were also generated. They contain no production data and are not application source.

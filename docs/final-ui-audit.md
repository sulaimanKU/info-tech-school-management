# Final UI source audit

Inspected existing screen/component structure, shared design tokens, official asset originals, authentication/session preferences, role filters and EN/Urdu direction before design edits. Reused existing controllers and request contracts. Common repetition: card/list rows, record status badges, error/empty states, financial label/value pairs, catalog edit forms and hub links.

All existing feature families consume the shared application theme. Purpose-built refinements supplement that foundation for login/home, attendance, parents, vouchers, conversations, reports and imports. No replacement of existing RBAC or business workflows.

## Screen inventory
- `mobile/lib/features/academics/academic_hub_screen.dart`
- `mobile/lib/features/academics/academic_settings_screen.dart`
- `mobile/lib/features/academics/assessment_screen.dart`
- `mobile/lib/features/academics/attendance_screen.dart`
- `mobile/lib/features/academics/coordinator_screen.dart`
- `mobile/lib/features/academics/diary_screen.dart`
- `mobile/lib/features/academics/exam_schedule_screen.dart`
- `mobile/lib/features/academics/student_academic_screen.dart`
- `mobile/lib/features/administration/catalog_screen.dart`
- `mobile/lib/features/auth/login_screen.dart`
- `mobile/lib/features/communication/announcement_screen.dart`
- `mobile/lib/features/communication/communication_hub_screen.dart`
- `mobile/lib/features/communication/notification_screen.dart`
- `mobile/lib/features/communication/ticket_screen.dart`
- `mobile/lib/features/files/private_file_screen.dart`
- `mobile/lib/features/finance/discount_screen.dart`
- `mobile/lib/features/finance/expense_screen.dart`
- `mobile/lib/features/finance/finance_configuration_screen.dart`
- `mobile/lib/features/finance/finance_hub_screen.dart`
- `mobile/lib/features/finance/invoice_screen.dart`
- `mobile/lib/features/finance/payment_screen.dart`
- `mobile/lib/features/finance/payroll_screen.dart`
- `mobile/lib/features/finance/student_fee_screen.dart`
- `mobile/lib/features/finance/trial_balance_screen.dart`
- `mobile/lib/features/home/home_screen.dart`
- `mobile/lib/features/imports/biometric_screen.dart`
- `mobile/lib/features/imports/import_screen.dart`
- `mobile/lib/features/offline/draft_queue_screen.dart`
- `mobile/lib/features/parents/child_workspace_screen.dart`
- `mobile/lib/features/parents/parent_home_screen.dart`
- `mobile/lib/features/reports/report_screen.dart`
- `mobile/lib/features/students/guardian_screen.dart`
- `mobile/lib/features/students/student_detail_screen.dart`
- `mobile/lib/features/students/student_list_screen.dart`
- `mobile/lib/features/support/support_screen.dart`

## Preservation checks
- User/session controller, session store, queue sync rules and money arithmetic were not replaced.
- Schema, routes and permissions unchanged; conversation detail adds only sender_kind for presentation.
- Guardian access and internal-note isolation remain server-enforced and pass regression tests.
- Reference/permission fixtures were used only by the existing isolated validation database guard.
- Original branding bytes and asset registrations unchanged.
- Shared list filtering explicitly searches only loaded records; existing API pagination remains available.

Detailed changes, limits, evidence and screenshot links: [final-ui-report.md](final-ui-report.md).

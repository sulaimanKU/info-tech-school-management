# Subject codes and bulk teacher assignments

31 October 2026. Focused local change; no commit, push, deployment, production access, credential change, real SMS or APK rebuild.

Subject codes already exist in `subjects.code` and are required/unique. The academic-choice response omitted them, and display labels used only names. Catalog cards, class-subject labels and assignment dropdowns/cards now show `CODE — Name`, with a name-only fallback for older response fixtures.

The existing multi-row screen and atomic batch endpoint are retained: one teacher, one session, multiple class/section/subject rows and one Save Assignments action. Required choices retain Select… prompts and dependent resets.

Class Teacher / Form Master now has one selection per distinct class/section below the subject rows. It saves once as an existing `teacher_assignments` row with `subject_id = null` and `is_class_teacher = true`. Subject rows keep that flag false. Removing/changing the last row for a section clears its pending designation. Existing combined subject/class-teacher records remain readable and editable. The existing per-section unique constraint and conflict message protect the current Form Master; nobody is silently replaced. Saved lists group readable subjects by teacher, class/section and session.

**No migration:** existing fields, batch transaction, permission checks and coordinator scope are unchanged. Teacher selection without employment profiles and Staff Profile are preserved. The duplicate School Setup assignment editor stays absent.

Validation:

- Focused Laravel: 21 tests, 225 assertions passed.
- Focused Flutter: 33 tests passed.
- Complete MariaDB suite, run once: **277 tests, 2,817 assertions, zero failures/errors, 2 expected PostgreSQL-only skips**.
- Complete Flutter suite, run once: **172 passed**.
- Flutter analyzer, run once: **No issues found**.
- Changed PHP passes Pint; Dart formatted; diff whitespace check clean.

## Files

- `api/app/Http/Controllers/CatalogController.php`
- `api/app/Http/Controllers/TeachingAssignmentController.php`
- `api/tests/Feature/SubjectBulkAssignmentTest.php`
- `docs/teacher-bulk-assignment-fix.md`
- `mobile/lib/features/administration/catalog_screen.dart`
- `mobile/lib/features/administration/staff_assignment_screen.dart`
- `mobile/lib/features/administration/teacher_assignment_batch_screen.dart`
- `mobile/lib/features/administration/teaching_assignments_screen.dart`
- `mobile/lib/l10n/strings.dart`
- `mobile/lib/shared/widgets/academic_filters.dart`
- `mobile/lib/shared/widgets/records_screen.dart`
- `mobile/test/full_role_functional_test.dart`
- `mobile/test/subject_bulk_assignment_test.dart`

## Manual Git commands

Instructions only; nothing was staged or committed. Review new files as well as the diff. Check any hosting deployment hooks before pushing.

```powershell
Set-Location 'E:\info-tech-school-management'
git status --short
git diff --check
git diff --stat
git diff
git add -- "api/app/Http/Controllers/CatalogController.php"
git add -- "api/app/Http/Controllers/TeachingAssignmentController.php"
git add -- "api/tests/Feature/SubjectBulkAssignmentTest.php"
git add -- "docs/teacher-bulk-assignment-fix.md"
git add -- "mobile/lib/features/administration/catalog_screen.dart"
git add -- "mobile/lib/features/administration/staff_assignment_screen.dart"
git add -- "mobile/lib/features/administration/teacher_assignment_batch_screen.dart"
git add -- "mobile/lib/features/administration/teaching_assignments_screen.dart"
git add -- "mobile/lib/l10n/strings.dart"
git add -- "mobile/lib/shared/widgets/academic_filters.dart"
git add -- "mobile/lib/shared/widgets/records_screen.dart"
git add -- "mobile/test/full_role_functional_test.dart"
git add -- "mobile/test/subject_bulk_assignment_test.dart"
git diff --cached --check
git diff --cached
git commit -m "Show subject codes and separate section Form Master assignments"
git push origin main
```

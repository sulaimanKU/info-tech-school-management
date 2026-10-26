# Final RBAC, staff, guardian and filters report

## 1. Final role matrix

See the shared [role and permission matrix](rbac-matrix.md), the authoritative reusable contract for future clients.

## 2. Final permission matrix

Nineteen existing/new permission codes and all six default role grants are documented in rbac-matrix.md. Runtime grants, not defaults, control non-Super access.

## 3. Super Admin access

Active Super Admin receives server-side full applicable access, including RBAC and all valid module actions, even without explicit grants.

## 4. Administrator access

School-wide operations and staff/guardian management; no Super assignment, RBAC management, financial write or approval by default.

## 5. Coordinator access

Assigned class/section scope; academics/review/corrections, parent communication, scoped announcements/reports. Removed default global-student permission.

## 6. Accountant access

Finance-focused module/actions and necessary student identity. No unrelated academics, staff/RBAC or private academic files.

## 7. Teacher access

Only current dated assigned section/subject records, intersected with record date. Attendance, diary and own assessment workflow; no review/global scope.

## 8. Parent access

Separate CNIC/OTP login, active verified own child links; published/parent-visible academics, own financial documents and public communication. No staff dashboard.

## 9. Staff management

Paginated server search and role/status filters, assignment summaries, details/edit, existing password hashing. Restricted role changes and session revocation; last active Super protected with role-row lock.

## 10. Guardian management

Searchable masked directory, exact keyed CNIC search, encrypted identity/contact, details/status/contact edits, verified child links and soft unlink. Sensitive changes revoke sessions/challenges/push registration.

## 11. Teacher assignment

Existing assignments extended with starts_on/ends_on. Authorized assignment management and current+record-date section/subject access.

## 12. Coordinator assignment

New coordinator_assignments using existing users/classes/sections. No new RBAC model; assignment management uses existing staff/role authorization.

## 13. Parent multiple-child behavior

Both linked children validated natively; selection refreshes child-scoped content. Unrelated child denied. Server authorization applies independently of selected Flutter state.

## 14. Navigation visibility

Central ModuleAccess uses current permission profile and separate parent home; profile refresh on resume. Super nine, Admin eight, Teacher five and Accountant four modules verified in native role workflow.

## 15. Action-level rules

Create/edit/review/approve/export/assignment actions follow existing grants. Every operation repeats server authorization; UI hiding is only UX.

## 16. Class/section/subject filtering

Reusable cascading scoped options: academic session → class → section → subject. Parent selections clear descendants; server intersects requested filters and assigned scope.

## 17. Student filters

Debounced paginated search, academic and status filtering over authorized students; stale response guard prevents old searches overwriting newer results.

## 18. Attendance filters

Session/class/section/date/status where supported; submitted roster and corrections retain record-level authorization.

## 19. Homework filters

Academic/date/status filtering over authorized diary records; subject and record-date assignments apply to lists and direct reads.

## 20. Assessment/result filters

Academic/subject/date/publication filters; writers/reviewers separated. Parent unpublished results denied.

## 21. Finance filters

Student/class/section/status/period/type where supported. Invoice status includes overdue/waived. Payments, approvals and receipts require all allocations in authorized scope.

## 22. Communication filters

Scoped ticket status/category/assignee and authorized student scope. Parent cannot read internal notes; coordinator reply/internal-note workflow verified.

## 23. Report filters

Scoped academic/date/type parameters delegated to existing report endpoints; imports remain own-batch for non-Super and available to Super under existing authorization.

## 24. Announcement targeting

School/class/section/role/individual audience supported. Scoped publisher cannot broaden to unauthorized sections via class_id. Selected parent child audience intersects verified access.

## 25. Server-side protection

SchoolAccess, AcademicFilters, TicketAccess and AnnouncementAccess plus existing controller/middleware rules. Filters never expand scope; finance and private documents retain object checks.

## 26. IDOR/security tests

Cross-parent students/results/receipt/private-file access; unpublished results/internal notes; Teacher/Coordinator unassigned objects; malicious filters; scope revocation; privilege escalation; dashboard retargeting and no-grant leakage covered in PostgreSQL suite.

## 27. Audit logging

Existing audit logger retained. Sensitive management actions record structural IDs/change metadata without passwords, CNIC, mobile, OTP or bearer values.

## 28. Files changed

Every cumulative source change is listed with status/reason in [final-rbac-files.md](final-rbac-files.md); latest focused pass in final-auth-dashboard-files.md.

## 29. Migration

2026_10_22_000011_refine_staff_scopes.php applied to development DB. Added coordinator assignments, dated teacher assignments, verified links, announcement targeting and new grants. Migration count 13→14; roles remain six, permissions16→19, classes11 and fee schedules22 preserved. No development DB reset; final teacher/coordinator assignments both zero.

## 30. Laravel/PostgreSQL result

118 tests, 864 assertions, PASS. Isolated local PostgreSQL port55433, infotech_school_test. Test wrapper restores caller environment. No tests run against real school data.

## 31. Flutter result

64 unit/widget tests PASS; final format check: 98 files, 0 changed, exit 0. Native integration cases reported separately.

## 32. Flutter analyze

No issues found.

## 33. APK build

Android debug APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk`

API_BASE_URL: `http://192.168.100.107:8000/api/v1` (current Wi-Fi address checked before build; development only).

Size: 228,106,858 bytes. SHA-256: `5f022aa4e168a29ef4716b9b9e24be909e9466f58b0bfd3d17f341eaba415c1a`.

Normal main-entry build verified by inspecting APK kernel assets: requested phone URL present; integration-test entry absent. Separate normal-entry emulator artifact: `mobile/build/app/outputs/flutter-apk/app-debug-emulator.apk`, using `http://10.0.2.2:8000/api/v1`. No physical handset was connected/tested.

## 34. Emulator role workflows

Medium Phone Android API 37, `emulator-5554` (1080×2400). Fictional fixtures in the guarded isolated PostgreSQL database only. Verified Super Admin, Administrator, Coordinator, Accountant, Teacher and Parent role navigation; scoped student lists and server-driven filters; staff/guardian/RBAC screens; attendance submission; diary publication; coordinator public reply/internal note; parent CNIC/OTP and both child cards/switching; unrelated child denial; parent homework/finance UI; finance/report/import navigation and empty states. Verified light, dark, system, English/Urdu RTL, preference persistence, logout, Remember Me and restoration after an actual Android force-stop. Production session storage was unchanged. The test driver initially uninstalled the app between runs; retaining the installed test app fixed that harness issue, and both restart stages then passed. Six-role and restart cases passed in separate runs; the combined wrapper was not rerun after its final keep-app-running correction.

Screenshots: `docs/screenshots/final-dashboard` (36), `docs/screenshots/final-rbac` (35). Normal app entry also launched with emulator API URL after fixture validation. No claim of a physical-phone workflow test.

## 35. Limitations/manual checks

The existing local `.env` APP_KEY currently has invalid AES-256 key shape. It was not changed or rotated: replacing it could make existing encrypted guardian/contact data unreadable. Restore the original valid key from a trusted local backup without posting it. Isolated tests use a separate gitignored validation key and pass; those results do not certify live encrypted guardian operations with the current invalid development key. PostgreSQL credentials were left unchanged and live staff authentication works.

Physical phone networking, real printer output, biometric hardware and production external credentials remain manual. External push/SMS delivery was not certified. See final-auth-dashboard-report.md and production-configuration.md. No web portal started.


Follow-up 2026-10-23: current encryption-key and existing guardian integrity checks pass. For the latest OTP/photos/theme implementation and test counts, see [final-media-report.md](final-media-report.md). Earlier invalid-key warnings are historical, not a current unresolved blocker.

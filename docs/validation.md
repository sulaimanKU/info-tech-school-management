# Final mobile validation — 22 October 2026

See [complete final report](final-mobile-report.md) for scope, evidence and limits. Previous Phase 1-only results are superseded by this validation.

| Check | Verified result |
|---|---|
| Full Laravel Unit/Feature suites on isolated PostgreSQL 18 | **64 tests, 402 assertions passed** |
| Clean isolated migration/seed | All 13 migrations succeeded |
| Development database inspection | 13 migrations applied; permission/reference configuration present |
| Dart format check | 74 files, 0 changes |
| Flutter analysis | No issues found |
| Complete Flutter unit/widget suite | 26 tests passed |
| Native emulator integration | 1 case passed, 22 October; real local API/PostgreSQL/OTP simulator |
| Main-entry debug APK | Built, installed, launched on emulator-5554 |
| Native API-unavailable state | Connection error visible; no fake login success |
| Documents | ID card visually inspected; one 86 × 54 mm page; voucher/receipt/annual fee/salary PDF and access tests passed |
| Pint | Passed |
| Composer audit | No security advisories found |

APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk` (227,984,397 bytes).
SHA-256: `9bdd1f8baad3b240f6c6f4c4def876215c781cd4a533a02f08b47516edde7fdd`.

Test coverage includes the requested auth/OTP, suspension/expiry/logout, parent/teacher IDOR, student lifecycle, published academic content, fees/discounts/allocations/refunds/approvals/payroll/ledger, conversations/announcements/notifications, private files, imports/biometrics, offline retries, localization/themes/preferences and report privacy. Parent/cross-parent/finance/import checks have backend coverage; they were not all manually replayed on Android.

No production SMS/FCM/hardware/signing/hosting integration, physical printing, school legal-policy approval, load test or penetration test is claimed. Release/AAB signing remains unverified. All operational test data was fictional and isolated. Normal development data was not reset.


## Final RBAC/authentication/dashboard validation — 2026-10-23

Current results supersede earlier historical counts: PostgreSQL **118 tests / 864 assertions PASS**, Flutter **64 tests PASS**, analyze clean, Pint passed, three functional native cases passed across runs, emulator and phone normal-entry debug APKs built. See [final-auth-dashboard-report.md](final-auth-dashboard-report.md), [35-item RBAC report](final-rbac-report.md), [shared matrix](rbac-matrix.md) and their file inventories.


Local configuration caveat: invalid existing APP_KEY must be restored from original backup; not rotated. Separate isolated test key is not a replacement for the development key. No real data or secrets in reports.


## OTP/private-photo/visual follow-up — 2026-10-23

Current pass: PostgreSQL 129 tests/960 assertions PASS; Flutter 76 PASS; analyze/Pint/format clean; four functional native cases passed across runs; physical-phone debug APK built. See [final-media-report.md](final-media-report.md), [local OTP commands](local-parent-otp.md) and [file inventory](final-media-files.md). Current key/decrypt/lookup integrity checks pass; earlier APP_KEY-invalid notes describe the earlier session only.

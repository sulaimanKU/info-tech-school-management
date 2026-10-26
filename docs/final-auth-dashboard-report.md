# Final authentication and dashboard report

Validation completed 2026-10-22/23 Asia/Karachi. No web portal, automatic commit, auth replacement or dependency upgrade.

## 1. Login root cause and exact fix

The Wi-Fi PHP HTTP worker did not load `pdo_pgsql`. PHP launched with `-d extension=pdo_pgsql -d extension=gd artisan serve` spawned an HTTP child without forwarding the parent's extension flags. Direct reproduction without flags returned HTTP 500 at the first `SELECT` on `users`; available PDO drivers were mysql/sqlite. With flags the PostgreSQL driver was available. The working CLI database lookup and a differently started localhost worker therefore did not prove the Wi-Fi worker was configured correctly.

`deployment/serve-api.ps1` runs the HTTP PHP worker itself with both extensions and Laravel's existing development router. Default bind is loopback; the phone listener uses the exact authorized Wi-Fi interface. No database credentials, `.env`, password checks or auth payload architecture were changed. Flutter already sent normalized `email`; no blind login-key replacement was made. The regression test asserts email/password and rejects a login key.

## 2. Files

Every changed/created implementation file with reason is listed in [final-auth-dashboard-files.md](final-auth-dashboard-files.md); cumulative RBAC inventory is [final-rbac-files.md](final-rbac-files.md). Inventories compare request-entry file hashes because this existing repository was untracked, not a clean committed baseline. Generated APKs/screenshots are evidence, not source changes.

## 3. Authentication acceptance

Live exact Wi-Fi endpoint, temporary fictional account removed afterward: login 200; bearer/user/roles/permissions shape and hashed database session verified; auth/me 200; logout 200; revoked bearer 401; invalid password 401; missing email 422; inactive staff 401; parent-only staff login 401. Secrets were not printed. Parent OTP code path remains separate; isolated backend and native parent OTP validation passed. Secure session/token storage and Remember Me architecture remain intact; native force-stop restoration and logout clearing passed. Plaintext passwords are not persisted.

## 4. UI/UX

Existing centralized navy/blue/teal tokens now use restrained warm gold accent. Compact school hero/login and greeting preserve the official logo and campus image, visibility toggle, Remember Me, validation/loading, and separate parent entry. Reusable summary cards, responsive spacing and status/error states serve the existing screen architecture. Domain forms show sanitized field-level API errors and appropriate email/phone/password inputs. Home uses a selected two-destination phone bar and wide navigation rail, account/preferences/logout, real profile name/campus, summary before quick actions. Light/dark/system, English and Urdu RTL are preserved and tested. Widget layouts cover widths 320/390/768/1440 and 1.6 text scale. Native screenshots reviewed; no wholesale workflow rewrite.

## 5. Dashboard and data sources

New authenticated staff-only `GET /api/v1/dashboard-summary`, implemented by `DashboardController`, aggregates existing tables with existing access services. No fabricated trends/statistics and no huge record downloads. Permission-revoked categories are omitted server-side and guarded client-side. Parent continues the separate child dashboard and receives 403 at the staff summary.

| Section | Existing source / definition |
| --- | --- |
| Students | Scoped students total and active status |
| Staff / Teachers | Active users with relevant staff/teacher roles; staff.manage only |
| Guardians | Active verified guardian/mobile and verified in-scope child link; guardians.manage only |
| School | Authorized classes/sections/subjects and current academic session names |
| Attendance | Today's submitted records and expected current enrollment roster, school calendar overrides; present/absent/late/excused/not marked and recorded/expected coverage |
| Academics | Published diary today; submitted assessments for reviewers, own drafts for writers |
| Finance | Existing invoices/adjustments minus verified allocations plus refunds; outstanding/overdue/debtor counts; current-month gross verified collections before refunds |
| Communication | Scoped open tickets; own unread notifications; latest three authorized published announcements |

Coverage is not presented as a fabricated attendance rate. No invented admissions trend. All money values remain full readable amounts. Permission-driven quick actions navigate existing student/staff/attendance/fee/announcement/report/diary screens. Coordinator/Teacher aggregates intersect dated assignments; Accountant sees finance without unrelated academic/staff summaries. Dashboard query-count regression checks constant query count when student volume grows.

## 6. Security

See [rbac-matrix.md](rbac-matrix.md) and [final-rbac-report.md](final-rbac-report.md). Authenticated no-store response; prohibited object/scope-retargeting query parameters; scope checks remain server-side. Super bypass is server-side; non-Super current grants and assignments are authoritative. IDOR, parent files/results/finance/internal-note boundaries, financial allocation scope, role escalation and session revocation are covered. No secrets/default production passwords or fake production records were added.

## 7–8. Backend and Flutter results

- Full isolated PostgreSQL Laravel suite: **118 tests, 864 assertions, PASS**; no failures. Includes 19 RBAC scope cases, 10 filter regression cases and 11 dashboard cases, alongside existing domain/security/authentication coverage.
- Laravel Pint project check: **PASS**.
- Composer locked dependency audit: **no security vulnerability advisories found**. No new dependencies.
- `dart format .` executed; final `dart format --output=none --set-exit-if-changed .`: 98 files, 0 changed, exit 0.
- `flutter analyze`: **No issues found**.
- `flutter test`: **64 tests, PASS**.
- Native Android: **three functional integration cases passed across runs** (six-role workflow; persistent-session preparation; session restoration after force-stop). Framework teardown counters are not counted as extra workflows.
- Debug APK builds and normal emulator main-entry launch: **PASS**.

## 9. Emulator

Medium Phone Android API 37, `emulator-5554` (1080×2400). Fictional fixtures in the guarded isolated PostgreSQL database only. Verified Super Admin, Administrator, Coordinator, Accountant, Teacher and Parent role navigation; scoped student lists and server-driven filters; staff/guardian/RBAC screens; attendance submission; diary publication; coordinator public reply/internal note; parent CNIC/OTP and both child cards/switching; unrelated child denial; parent homework/finance UI; finance/report/import navigation and empty states. Verified light, dark, system, English/Urdu RTL, preference persistence, logout, Remember Me and restoration after an actual Android force-stop. Production session storage was unchanged. The test driver initially uninstalled the app between runs; retaining the installed test app fixed that harness issue, and both restart stages then passed. Six-role and restart cases passed in separate runs; the combined wrapper was not rerun after its final keep-app-running correction.

Screenshots: `docs/screenshots/final-dashboard` (36), `docs/screenshots/final-rbac` (35). Normal app entry also launched with emulator API URL after fixture validation. No claim of a physical-phone workflow test.

## 10. APK

Android debug APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk`

API_BASE_URL: `http://192.168.100.107:8000/api/v1` (current Wi-Fi address checked before build; development only).

Size: 228,106,858 bytes. SHA-256: `5f022aa4e168a29ef4716b9b9e24be909e9466f58b0bfd3d17f341eaba415c1a`.

Normal main-entry build verified by inspecting APK kernel assets: requested phone URL present; integration-test entry absent. Separate normal-entry emulator artifact: `mobile/build/app/outputs/flutter-apk/app-debug-emulator.apk`, using `http://10.0.2.2:8000/api/v1`. No physical handset was connected/tested.

## 11. Genuine manual/production items

The existing local `.env` APP_KEY currently has invalid AES-256 key shape. It was not changed or rotated: replacing it could make existing encrypted guardian/contact data unreadable. Restore the original valid key from a trusted local backup without posting it. Isolated tests use a separate gitignored validation key and pass; those results do not certify live encrypted guardian operations with the current invalid development key. PostgreSQL credentials were left unchanged and live staff authentication works.

Install the phone APK on a same-Wi-Fi handset and confirm its network/firewall path and own authorized account; rebuild if the Wi-Fi address changes. Debug cleartext development transport is not production deployment. Before release configure HTTPS, production debug-off settings, Android release signing, actual SMS/OTP provider, push/Firebase delivery credentials and other external services per production-configuration.md; no credentials invented and no external delivery certified. Import/biometric hardware and actual printer/paper output still require operator validation; backend import/report/security tests do not prove hardware integration or physical printing. Large real production volumes, field accessibility and a production rollout are not certified by these development tests. Review the existing offline sync conflict workflow with actual device conditions; no offline architecture removed.

The broad all-interface listener was rejected by automatic review; the safer localhost and exact authorized Wi-Fi listeners were accepted and validated. No outstanding approval request remains. No real student/parent data was used in validation.


Follow-up 2026-10-23: current encryption-key and existing guardian integrity checks pass. For the latest OTP/photos/theme implementation and test counts, see [final-media-report.md](final-media-report.md). Earlier invalid-key warnings are historical, not a current unresolved blocker.

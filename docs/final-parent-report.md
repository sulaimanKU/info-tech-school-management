# Final Parent Experience continuation report

Validated on 2026-10-26. Existing project: `E:\info-tech-school-management`. No web portal work or automatic commit.

## 1. Already complete before this continuation

The existing mobile/backend supplied CNIC/OTP parent sign-in, encrypted local SMS simulator, bearer session restoration/logout, verified guardian/student links, staff RBAC, private optional student/guardian photos, published academic/attendance APIs, fees/vouchers/receipts, diary attachments, parent-visible behavior, targeted announcements, own coordinator tickets and authenticated reports. Established INFO-TECH assets and light/dark/system/English/Urdu design infrastructure were preserved. Historical baseline was 129 Laravel tests/960 assertions and 76 Flutter tests; those historical numbers are not used as the current result.

## 2. Interrupted Friday work found

The working tree contained partial batched parent summaries/attendance/financial balance services, a new selected-child overview route, published-assessment grouping and report metadata, and extracted diary child scoping. The Flutter workspace was still the original plain menu. Five drafted parent widgets remained outside the project and were not integrated. The partial attendance batch returned arrays where existing filters expected objects; the assessment selector's regex also needed Laravel array-rule syntax because it contains a pipe.

## 3. Completed in this continuation

- Integrated modular Parent Home/child workspace, overview, reusable profile/resource widgets, dedicated published academics and monthly attendance.
- All verified children remain available; the selected child is visible throughout. A child/module key recreates every module on switching, while request generations reject late previous-child responses. No cross-child fade preserves old information.
- Home shows private photo/fallback, permanent registration, class/section/roll, actual attendance/provisional status, fee status/balance and latest **subject** result, with loading/empty/error/retry.
- Five compact destinations provide overview, academics, homework, messages and More. More reuses attendance, finance, behavior, announcements, schedules, reports and safe student profile. Existing global account/theme/language/notifications/logout remain available through the existing app shell.
- Published assessment selector drives a server filter. Subject cards show obtained/maximum marks, percentage, stored grade, date, published teacher remarks/name. Overall percentage uses total obtained/total maximum rather than averaging percentages. Session grading rules determine the overall band. Existing authenticated PDF/CSV reports receive the selected assessment and metadata.
- Actual comparable history is shown as simple progress bars without a new chart package. Comparisons require the same session, section, type and per-subject maximum-mark set, do not include later dates than the selected assessment, and require at least two points.
- Attendance provides previous/current month navigation, present/absent/late/excused counts, denominator/recorded/provisional state, daily text/icons, holidays and existing report access.
- Finance reuses read-only balances, arrears, schedules, discounts, payments, voucher detail and receipts. Voucher lists now include due dates. Existing invoice detail exposes paid/net/refunded/balance amounts. PKR formatting remains centralized; parent payments were not introduced.
- Diary subjects and optional detail context follow the selected child. Existing newest-first/date/subject filters, teacher information, private image zoom and PDF opening remain. Subject-filter network failures now enter the existing friendly retry state.
- Published parent-visible behavior, targeted announcements and the existing own-ticket coordinator workflow are retained and connected, with no teacher private contacts added.
- English/Urdu strings and existing semantic palettes support LTR/RTL, light/dark/system modes. Screens use centralized cards, status tokens, spacing and typography.

## 4. Intentionally omitted / limits of the existing model

No auth redesign, duplicate RBAC/API system, default accounts, real SMS gateway, new payment feature, public media, heavy chart package or web portal was added. No fake statistics, ranks or trend points are shown. There is no formal cross-subject examination-period table: selector groups use exact session + section + type + title + date. Assessments recorded on different dates/titles stay separate; school-wide multi-day exam normalization would require a separate approved data-model task. No class rank is introduced by the new parent presenter. Empty history shows available results without inventing a chart. Parent read caching is scoped in memory; no new sensitive disk cache or offline financial writes were added.

## 5. Important changed and created files

See [the complete file-and-purpose manifest](final-parent-files.md): 14 existing source/document files changed, 15 source/document files created, plus 16 fictional-data screenshots and the debug build artifact. Auth/session/OTP implementation, migrations, secrets and official branding assets remain unchanged.

## 6. Laravel/PostgreSQL result

**136 tests, 1,110 assertions — passed.** Full suite executed through `deployment/test-backend.ps1`, isolated PostgreSQL on port 55433 / `infotech_school_test`, including the seven new parent regressions. Test migrations/seeding were restricted by the existing database identity guard. Development `artisan migrate:status` confirms all **15 migrations applied**; no new migration or development reset was required.

## 7. Flutter result

**91 tests — passed**, including 15 new parent widget tests and the original 76. Android integration is a separate single end-to-end workflow, not included in that 91 count.

## 8. Static analysis

`flutter analyze`: **No issues found**.

## 9. PHP style

Laravel Pint applied formatting; final `php vendor/bin/pint --test`: **passed**.

## 10. Dart formatting and Android APK

`dart format .` executed. Final `dart format --output=none --set-exit-if-changed .`: **111 files, zero changes**.

Normal phone build: `flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.100.107:8000/api/v1` (**passed**; no API IP added to application source). Current detected laptop Wi-Fi address is `192.168.100.107`; a separate Ethernet default interface is `192.168.0.69`. The Wi-Fi endpoint was selected for same-Wi-Fi phone testing.

Exact APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk`.

Final artifact: 228,144,880 bytes; SHA-256 `aad7be479faacccf726d8863f3c96b2d6dab56822d2b08ffce8079433d92420b`. The APK kernel was checked for the expected phone API URL and absence of the integration workflow entrypoint. Android driver and normal phone build both returned explicit exit code **0**. Temporary isolated validation API/PostgreSQL processes were stopped after validation.

## 11. Workflows actually validated

Android emulator `emulator-5554`, Medium Phone API 37, real HTTP client and real isolated Laravel/PostgreSQL fixtures. Validation uses `http://10.0.2.2:8001/api/v1` through the guarded `deployment/start-mobile-validation.ps1 -Port 8001`, rather than development data on port 8000. Local encrypted simulator OTP is entered by the existing guarded helper without printing the code.

The integration test completed: CNIC → request OTP → verify → secure Remember Me → parent home/two children → Alpha overview → published academics/subject marks → historical selector → Beta academics → both children's different attendance → different vouchers/balances → different homework → different behavior → announcements → parent query form/submission → real coordinator follow-up → own query list clears on switching to Alpha → authenticated academic PDF → persisted Urdu/dark preferences → a new AuthController restores the parent from secure storage → logout clears storage and returns to sign-in. Direct unrelated-child overview/results/finance/diary/ticket calls are denied during the Android run. Sixteen screenshots are saved in [final-parent](screenshots/final-parent/).

Widget tests also prove delayed old-child responses cannot overwrite the current child, network retry recovery, all child module request contexts, one-child/fallback selection, no-linked-child state, four widths (320/390/768/1440), Urdu RTL/dark workspace and scaled Urdu/light academic layouts. Existing regression tests exercise system theme behavior and persisted preferences.

This was emulator automation with screenshot inspection, **not a physical-phone test**. In-process secure-session restoration was tested; a new OS force-stop/reboot restoration run was not performed in this continuation. New private diary attachment picking/opening on a physical device remains manual; existing backend/private-file and prior media coverage was rerun in the full suites.

## 12. Security/scoping verification

Server-side link checks remain authoritative. Tests cover Parent A accessing two linked children; Parent B accessing only its child; a second verified guardian legitimately sharing one child; cross-family child IDs/URL/query substitution denied for profile, overview, results, attendance, finance, behavior, diary, announcements and tickets. Assessment keys cannot be reused across children; invalid keys fail validation and unknown keys return 404. Draft results/remarks and unpublished diary content are excluded. Academic CSV/PDF retains assessment and student scope. Parent partial payment/refund/adjustment balance is correct; vouchers and receipts deny wrong-child/cross-family access. Existing full-suite cases verify private photos/files, another guardian's conversations, staff-only ticket notes, suspension/session revocation and OTP expiry/replay/rate limits. No public URLs, raw credentials, CNIC or OTP values were added to reports/screenshots.

## 13. Query improvements

Parent Home batches child enrollment, submitted attendance, calendars/settings, invoices, adjustments, verified allocations, refunds and latest published subject results. It does not run per-day database calls per child or three financial aggregate queries per invoice. A PostgreSQL regression measures the same ParentChildren SQL query count with **two vs seven linked children** and **one vs sixteen invoices**. Batched balance calculations are checked against the existing calculator, including a partial payment, refund and negative adjustment. The endpoint still loads the family's actual invoice/history set in memory; very large histories should be profiled against production-scale data before deployment. This is a measured N+1 regression improvement, not an unmeasured latency claim.

## 14. Remaining production/manual items

- Install the APK on a physical phone; test real Wi-Fi routing, keyboard/text scaling, image zoom, PDF viewer/printing, attachment selection, permission prompts, rotation and interruptions. After changing networks, detect the new LAN address and rebuild the debug APK.
- For phone testing run `cd E:\info-tech-school-management\api` then `.\serve-local.ps1 -BindAddress 192.168.100.107 -Port 8000` on the same trusted Wi-Fi; this explicitly enables only the local encrypted simulator. No firewall rules were changed. Default `.\serve-local.ps1` is loopback-only for emulator/local use.
- Recheck force-stop/reboot Remember Me, session expiry while a document is open, offline/reconnect, and local storage/device policy on target phones. New parent reads have retry/in-memory retention, not complete offline operation.
- Production SMS delivery still needs a provider account/configuration and real delivery testing. Firebase/FCM delivery requires the existing production credentials/configuration and devices; it is not certified by this parent pass.
- Production requires HTTPS, release signing/distribution, proper environment/secrets/backups/restore validation, queue/scheduler operation, monitoring, operational role/enrollment/guardian verification and realistic load testing. No external credentials were invented and no real student/guardian fixtures were used.
- Review actual examination naming/dates and configured grading bands. Multi-day normalized exam periods and policy-enabled class positions need a separately approved model/feature if required.
- Existing Android build tooling emits Java native-access and plugin/Kotlin migration warnings; the debug build succeeds. Dependency/toolchain modernization is separate from this focused parent task.

## 15. Readiness for the Web Portal phase

**Yes: the scoped Parent Experience is complete enough to move to Web Portal planning/implementation**, with the production/manual items above explicitly outstanding. This means tested mobile/backend functionality, not certification of live SMS/push delivery or production deployment. **No web portal was started. No commit was made.**

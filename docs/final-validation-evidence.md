# Final validation evidence

- Full isolated PostgreSQL Laravel suite: **118 tests, 864 assertions, PASS**; no failures. Includes 19 RBAC scope cases, 10 filter regression cases and 11 dashboard cases, alongside existing domain/security/authentication coverage.
- Laravel Pint project check: **PASS**.
- Composer locked dependency audit: **no security vulnerability advisories found**. No new dependencies.
- `dart format .` executed; final `dart format --output=none --set-exit-if-changed .`: 98 files, 0 changed, exit 0.
- `flutter analyze`: **No issues found**.
- `flutter test`: **64 tests, PASS**.
- Native Android: **three functional integration cases passed across runs** (six-role workflow; persistent-session preparation; session restoration after force-stop). Framework teardown counters are not counted as extra workflows.
- Debug APK builds and normal emulator main-entry launch: **PASS**.

Live Wi-Fi staff acceptance: login200,profile200,logout200,revoked401,invalid password401,missing email422,inactive401,parent-only staff login401. Temporary fictional account removed; development counts preserved.

Android debug APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk`

API_BASE_URL: `http://192.168.100.107:8000/api/v1` (current Wi-Fi address checked before build; development only).

Size: 228,106,858 bytes. SHA-256: `5f022aa4e168a29ef4716b9b9e24be909e9466f58b0bfd3d17f341eaba415c1a`.

Normal main-entry build verified by inspecting APK kernel assets: requested phone URL present; integration-test entry absent. Separate normal-entry emulator artifact: `mobile/build/app/outputs/flutter-apk/app-debug-emulator.apk`, using `http://10.0.2.2:8000/api/v1`. No physical handset was connected/tested.

The existing local `.env` APP_KEY currently has invalid AES-256 key shape. It was not changed or rotated: replacing it could make existing encrypted guardian/contact data unreadable. Restore the original valid key from a trusted local backup without posting it. Isolated tests use a separate gitignored validation key and pass; those results do not certify live encrypted guardian operations with the current invalid development key. PostgreSQL credentials were left unchanged and live staff authentication works.

Raw local command logs: E:\info_teach\final-backend-validation.log, dashboard-flutter-tests.log, dashboard-analyze.log, dashboard-emulator.log, dashboard-restart.log, dashboard-main-emulator.log, dashboard-phone-build.log, rbac-composer-audit.log. Native raw logs are intentionally not copied into reports because debug transport URLs are not useful handover content. Screenshots are under docs/screenshots/final-dashboard and final-rbac.

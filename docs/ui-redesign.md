> Historical Phase 1/UI foundation record. Current complete mobile scope: [final report](final-mobile-report.md), [architecture](mobile-architecture.md) and [domain workflows](domain-workflows.md).

# Phase 1 UI/UX redesign

Project: `E:\info-tech-school-management\mobile`. Scope: presentation and the requested secure session persistence choice. No Phase 2 work.

## Files changed

- `mobile/pubspec.yaml` — registers the two exact existing JPEG asset paths. No dependency additions.
- `mobile/lib/app/school_app.dart` — centralized themes, branded header, language/theme controls, account access and startup/reconnect presentation.
- `mobile/lib/features/auth/auth_controller.dart` — optional Remember Me persistence through the existing secure SessionStore. API requests and session validation stay unchanged.
- `mobile/lib/features/auth/login_screen.dart` — responsive campus hero and separate login form.
- `mobile/lib/features/home/home_screen.dart` — welcome/account/module shell and confirmation-based logout.
- `mobile/lib/l10n/strings.dart` — complete English/Urdu labels, hints, accessibility labels and dialogs.
- `mobile/test/widget_test.dart` — preserves existing regressions and checks new logout confirmation and toolbar controls.
- `README.md`, `docs/architecture.md` — current UI structure and Remember Me behavior.

## Files created

- `mobile/lib/design/app_theme.dart` — palette, typography, spacing, breakpoints, radii, card/input/button/dialog/bottom-sheet themes.
- `mobile/lib/shared/widgets/school_brand.dart` — unchanged original logo and campus-photo hero.
- `mobile/lib/shared/widgets/surface_card.dart` — surface cards, role/status badges, error/information/success states.
- `mobile/lib/shared/widgets/app_toolbar.dart` — shared language and appearance controls.
- `mobile/lib/features/auth/widgets/staff_login_form.dart` — validation, icons, show/hide password, Remember Me, reset help and request states.
- `mobile/lib/features/home/widgets/welcome_panel.dart` — real user's name and assigned role badges.
- `mobile/lib/features/home/widgets/account_sheet.dart` — read-only real account details and logout dialog.
- `mobile/lib/features/home/widgets/module_card.dart` — reusable visual-only module cards and adaptive grid.
- `mobile/test/support/auth_fixtures.dart`, `mobile/test/ui_redesign_test.dart` — isolated fake transport/storage for automated tests only.
- `docs/ui-redesign.md` — this report.
- Seven review images under `docs/screenshots/`, prefixed `ui-` (listed below).

## Login and branding

The original official logo is `assets/1.jpeg` (not PNG). It is displayed uncropped on a white logo tile in the app header, login hero, wide login form, startup/reconnect states and account sheet. The building image is `assets/2.jpeg`; it fills the login hero with a navy overlay for readable school/campus text. Both files are unchanged. No logo was generated, recolored or replaced.

Mobile login stacks the hero and form in one keyboard-safe scroll view; wide layouts place them side by side. Email/password icons, password visibility, field validation, disabled/loading Sign In, inline localized API/network errors and password clearing after submission are included. Forgot Password honestly directs users to the school administrator; no reset API or fake success flow was introduced.

## Dashboard

Branded app bar; a navy/teal welcome panel with the authenticated user's name, role badges and campus; account details sheet; language/theme access; confirmation-based logout; and eight responsive future-module cards: Students, Attendance, Academics, Fees & Finance, Staff, Reports, Messages / Announcements and Settings. Every card is marked Coming later and has no tap action. There are no statistics, fabricated records or new module endpoints.

## Remember Me and authentication preservation

- Unchecked by default: the issued token remains in memory for the current app process. It is not written to secure storage; any previous persisted token is removed after successful sign-in.
- Checked: only the issued bearer token is stored through the existing Android secure-storage adapter and restored/verified through the existing current-user endpoint on startup.
- No password is stored in application storage, preferences, files or logs. Password controllers are cleared after a completed attempt and disposed with the form.
- Remember Me does not extend the backend's existing session lifetime. It remains subject to server expiry, suspension and revocation.
- Logout still revokes the server token and then clears local storage. If revocation fails because of the network, the session remains available for a retry and an error is shown.
- API routes, payloads, mobile API client, secure-storage implementation, Laravel application and database schema are unchanged. A SHA-256 comparison against the pre-redesign Laravel source inventory found zero changes. Existing Laravel regression tests were also run.

## Themes and localization

Light, dark and system modes share a centralized design system. System mode follows platform brightness. English uses LTR; Urdu uses Flutter's RTL direction, directional alignment, mirrored navigation icons and fully translated controls/dialogs. Email values remain LTR for readability. All English localization keys have nonempty Urdu equivalents. Locale/theme remain in-memory preferences, as before, and reset on a fresh app launch.

## Executed validation

| Command/check | Result |
|---|---|
| `dart format .` | Successful |
| `flutter analyze` | No issues found |
| `flutter test` | 18 tests passed |
| `flutter build apk --debug --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1` | Successful debug APK |
| `adb install -r ...app-debug.apk` | Success on emulator-5554 |
| Emulator visual checks | English login, Urdu RTL login, dark Urdu login, original assets and appearance controls inspected |
| `php artisan test --compact` (unchanged API) | 15 passed, 1 PostgreSQL-specific test skipped on SQLite; 40 assertions |
| Backend source comparison | Zero changed source/config/test files |

The 18 Flutter tests cover login/logout, confirmation/cancellation, real account details, all eight future cards, password visibility/validation, honest reset guidance, busy/error states, Remember Me on/off and restoration, network/revocation failures, expired sessions, themes and complete localization. Responsive Urdu/keyboard tests pass at 320, 390, 768 and 1440 logical pixels wide.

APK: `mobile/build/app/outputs/flutter-apk/app-debug.apk`. The build preserves `com.infoteach.school` and the existing API URL configuration. Gradle printed a JVM native-access warning and pub noted newer package versions; neither prevented validation. No packages were upgraded for this redesign.

## Review images

- [English login on Android](screenshots/ui-login-android.png)
- [Urdu login on Android](screenshots/ui-login-urdu-android.png)
- [Dark Urdu login on Android](screenshots/ui-login-urdu-dark-android.png)
- [Wide login layout](screenshots/ui-login-wide.png)
- [Wide dashboard — test fixture](screenshots/ui-dashboard-wide-test-fixture.png)
- [Phone dashboard — test fixture](screenshots/ui-dashboard-phone-test-fixture.png)
- [Urdu dashboard — test fixture](screenshots/ui-dashboard-urdu-test-fixture.png)

Dashboard previews were rendered using a test-only Test Staff account and test HTTP transport. They are visual review artifacts, not a real staff login. Test previews use locally installed font files for readability; the Android app uses platform fonts. The temporary screenshot-rendering harness was removed; permanent tests do not depend on those local font paths. No preview or fake account is wired into the application.

## Manual checks remaining

1. Sign in to your configured Laravel/PostgreSQL backend with a development staff account and verify the displayed name, email and roles.
2. With Remember Me unchecked, terminate/relaunch the app and confirm it asks for sign-in. With it checked, terminate/relaunch and confirm the existing valid session restores; also check server expiry/suspension behavior.
3. Exercise cancel/confirm logout and a network failure during logout against the real server.
4. Check appearance, Urdu typography, keyboard scrolling and large system font settings on your physical Android devices.
5. Review the official logo/photo presentation and confirm the future cards remain unavailable.

A real staff sign-in from the redesigned emulator app was not performed because no credentials were requested or embedded. Existing backend regression coverage and mobile tests passed. No production/release build or deployment was performed.

# Final Staff Profile & Account report

2026-10-26 — existing INFO-TECH Laravel/Flutter project only. No web portal work, commit or credentials change outside fictional test fixtures.

## 1. Root cause

The old Flutter `MyStaffProfileScreen` called `GET /api/v1/my-staff-profile` without a target ID. `PayrollController::mine` correctly used the authenticated user's ID, but called `staff_profiles.where(user_id, authenticated_user.id).firstOrFail()`. Account access was therefore incorrectly dependent on an employment/payroll record existing. Its 404 became ApiFailure.notFound and the generic RecordsScreen displayed “Unable to load / This record is not available to your account / Retry connection.” Retrying cannot create the missing employment link.

A read-only development database inspection found **five staff-role accounts and zero linked staff profiles**: two Super Admins, one School Administrator, one Coordinator; no Teacher or Accountant accounts. This confirms the missing-link condition for existing staff accounts. The exact account used in the user's screenshot was not supplied, so no identity is inferred. This was not a wrong Flutter staff ID, stale identity selection or a reason to bypass authorization.

## 2. Exact fix and staff linking

Separate the authenticated **user account** from its optional official **staff employment record**. New own-account endpoints always derive identity from the bearer session. A legitimate unlinked staff account can read/edit its safe personal profile; employment and salary show a clear administrator-linking notice. A linked staff account with no paid slips gets a separate empty state. The old `my-staff-profile` URL and Flutter entry class remain available, with corrected nullable-link behavior.

No joining dates, designations or employee IDs were invented for development users. Existing production-safe linkage remains: an authorized administrator opens **Staff → staff account → Staff profile**, supplies the actual employee number/designation/joining date and saves. This uses existing `POST catalog/staff` with the account's `users.id` in `staff_profiles.user_id`; the unique FK permits one employment record per user. Self-service cannot establish or change this link. The guarded fictional emulator seeder now supplies valid links for all five roles, fixing incomplete validation data without hard-coding a real account.

## 3. Profile/account features

“Profile & Account” contains a private photo/fallback avatar header, real name/role/active status, employee number/designation where linked, personal information, official employment information, a separate salary section, password change and confirmed sign-out. Name/email/mobile/address display actual values or “Not recorded.” Gender was not part of the existing staff model and was not invented.

## 4. Editable vs protected fields

Self-service permits **name, mobile, address and own photo**. Name editing follows the existing user-name model; mobile/address are nullable account-contact fields. Email remains read-only because it is the staff login identifier; existing authorized administrative email changes remain in staff management.

Role/permissions, employee link/ID, designation/joining date, account/employment status, salary and direct photo-file references cannot be changed through self-service. Unknown/protected inputs are rejected with 422, including supplied user/staff IDs. No new mass-assignment path or duplicated RBAC exists. Personal updates are audited by field names; protected changes remain in existing permission-controlled administrative modules.

## 5. Password change

Hidden current/new/confirmation fields with individual visibility controls and no suggestions/autocorrect. Client/server validation enforces the existing policy: **12–1024 characters**, matching confirmation, and a different new password. The server verifies the current hash under a user-row lock and uses the existing User hashed cast. There is no plaintext password persistence/response/logging or alternative login mechanism.

The change transaction revokes **all existing sessions and push registrations for that user**, consistent with administrative security revocation. Flutter clears its existing secure session through the normal logout path and requires re-login. Other users' sessions remain valid. Password-change requests are throttled (six per minute). Success, wrong-current-password, validation and API errors have localized feedback.

## 6. Profile photos

Staff photos reuse `ProfilePhotoService`, `private_files`, private local storage, authenticated file downloads, `AuthorizedPhoto`, and the existing picker/preview/change/remove helper. Nullable user photo reference and file owner FK were added through one migration. JPEG/PNG/WebP MIME/extension/decoding, configured 5 MiB limit and 24 MP limit are enforced. Existing server re-encoding to at most 768 pixels strips source metadata; filenames are private UUIDs.

Only the current owner photo is readable; another staff member, another guardian, or even a Super Admin through the own-photo path cannot retrieve it. Replaced/removed photo IDs stop authorizing. Tests cover replacement/removal and corrupt, non-image and oversized uploads. Student/guardian defaults and authorization remain unchanged.

## 7. Salary slips

Separate read-only subsection showing only the authenticated user's **paid** periods, payment date/status, base salary, adjustments, net salary and authenticated PDF access. No editable salary fields were added. Missing employee link and linked/no-slips are distinct states.

The own-document endpoint first checks payroll item → staff profile → authenticated user and paid status, even for administrators. Existing broader payroll/report permissions remain in separate administrative modules. Tests explicitly deny Staff A → Staff B, unpublished/unpaid slips, and an administrator trying to use the own-document route for another employee.

## 8. Error states

Network, server/service unavailable, session expiry, forbidden, unavailable record, validation, rate-limit and missing PDF viewer have distinct localized account messages. Only plausible transient failures get “Try again.” Missing employment link instructs the user to contact the administrator; no-slips is an empty state. Forbidden/not-found do not offer a misleading connection retry. Salary document viewer receives the feature's error builder; existing parent/document viewers keep their previous default behavior.

Logout or session expiry closes account/nested routes back to the application root, preventing stale personal screens remaining above sign-in. No raw server exceptions are displayed.

## 9. Design

Uses existing INFO-TECH semantic colors, theme, SurfaceCard, AppBadge, AuthorizedPhoto, SectionHeader, ValueRow, PageBody and spacing/radius/typography. Restrained brand header, success/active status, official-details guidance and separate personal/payroll/security sections replace the plain mixed list. No logo/assets/packages were replaced. Keyboard-safe scrolling and disabled/loading submission are supported.

Light/dark/system behavior uses the existing theme architecture. English/Urdu RTL is localized. A screenshot review found two raw field labels (mobile/payment date); they were corrected and an explicit translation regression was added before the final APK build. Existing contrast/system-theme regressions remain passing; new account layouts were tested at 320, 390, 768 and 1440 widths with Urdu dark text scaling.

## 10. Roles

Super Admin/Managing Director, School Administrator, Coordinator, Accountant/Clerk and Teacher all get their **own** account and paid slips through the same session-scoped endpoints. Administrative powers do not turn My Account into an employee selector. Parent-only accounts are denied these staff APIs. Existing separate administrative staff/payroll privileges remain intact.

## 11. Security regressions

Eight new PostgreSQL tests cover five-role unlinked account access; linked personal update isolation; rejection of protected fields/ID substitution; unauthenticated and parent denial; current password/confirmation/policy failures; hashed password and all-old-session revocation with other-user session preservation; private photo access/replacement/removal/invalid uploads; and paid-salary ownership/unpaid filtering/admin own-route denial. Existing full parent/RBAC/private-file/OTP/finance suites also passed.

## 12. Files

See [every important changed/created file and purpose](final-staff-account-files.md). Twelve existing source/document files changed and twelve source/document files were created, plus ten fictional-data Android screenshots. The file manifest distinguishes optional shared helpers from unchanged Parent Experience screens.

Migration `2026_10_26_000013_add_staff_account_fields.php` adds nullable account contact/photo fields and private file ownership, without creating employment/payroll records. Applied to development successfully; **16 migrations are applied**. `.env`, external credentials and existing authentication/OTP controllers were untouched.

New authenticated routes: GET/PATCH `account/profile`, POST `account/password`, POST/DELETE `account/photo`, GET `account/salary-slips/{item}/document`. Legacy GET `my-staff-profile` remains supported.

## 13. Laravel/PostgreSQL

**144 tests, 1,239 assertions — passed** through `deployment/test-backend.ps1` against guarded PostgreSQL port 55433 / `infotech_school_test`. This is the complete existing 136 tests plus eight staff regressions. No test reset or fictional seed was applied to development data.

## 14. Flutter

**107 tests — passed**: original 91 plus sixteen staff tests. The additional Android integration workflow is separate from this unit/widget total.

## 15. Analyze

`flutter analyze`: **No issues found**.

## 16. Pint

Pint formatting executed; final `php vendor/bin/pint --test`: **passed**. `git diff --check` is clean.

## 17. Formatting / APK

`dart format .` executed; final check reports **117 files, zero changes**. Normal debug APK rebuilt for current Wi-Fi API `http://192.168.100.107:8000/api/v1` (LAN address detected, not committed into Dart source).

APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk`.

## 18. Actual emulator validation

Real Android emulator `emulator-5554`, Medium Phone API 37, real Flutter HTTP client and isolated Laravel/PostgreSQL using `http://10.0.2.2:8001/api/v1`. Fixture seeding is guarded to local environment and exact test database. The existing development server on port 8000 was preserved.

**Passed, explicit driver exit 0**: all five staff-role password logins → actual account-menu navigation → correct own account/email/employee link; logout closes profile. Teacher → edit name/mobile → image draft preview → actual authenticated private-photo upload → refreshed account/profile image; target-user query rejected; another staff salary PDF denied; own paid slip and PDF verified; required password validation → valid current-password change → sessions/sign-in transition → new-password re-login; Urdu RTL/dark account and logout. Ten screenshots are under [final-staff-account](screenshots/final-staff-account/).

Photo selection was supplied through the actual picker widget's draft callback for automation; the OS gallery/file chooser itself was not manually exercised. Backend tests cover remove/invalid-image behavior; these were not claimed as separate physical-device workflows. The last two display-label translations were covered by the final Flutter suite after the Android functional run.

## 19. Remaining manual / production items

- Supply real official employment information through existing staff management for the five currently unlinked development accounts. The safe account page now works regardless; salary remains empty/unlinked until legitimate payroll data exists.
- Install the updated normal APK on a physical phone and test gallery selection, photo removal, keyboard/accessibility scaling, PDF viewer/printing, network loss and backgrounding/force-stop/session expiry. No physical-phone run is claimed.
- Confirm the chosen all-session sign-out policy with staff operations and perform target-device re-login checks. Session information remains existing login history; no invented device management was added.
- Deploy the new migration before deploying the account API/client. Preserve existing APP_KEY, private storage, backups, role grants and trusted HTTPS production endpoint. Production signing and existing SMS/push credentials/delivery validation remain separate deployment tasks.
- Contact/profile save and photo upload use the existing separate requests. If a photo request fails after details save, review the personal details/photo and retry; successful requests are not rolled back across HTTP calls.
- Existing Java native-access/Kotlin-plugin tooling warnings do not block the debug build. Broad dependency/toolchain upgrades were outside this focused phase.

## 20. Completion

**Staff Profile & Account phase complete**, with required manual/production checks above. The missing-link root cause is handled through correct identity separation, not by weakening authorization. Existing Parent Experience, OTP, private media and staff/admin modules retain passing regressions. **No web portal was started. No commit was made.**

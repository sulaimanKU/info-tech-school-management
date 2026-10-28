# Android system architecture

The existing Laravel 12/PostgreSQL API and Flutter authentication were extended in place. Android package remains `com.infoteach.school`. No desktop or browser management UI was built. Future clients can use the same versioned REST endpoints.

## Boundaries

Flutter screens call `AuthController` and `ApiClient`; authenticated requests use the existing bearer token. API middleware resolves a live, unrevoked, unexpired session and active user on every request. Controllers validate a strict input whitelist and enforce permission/object scope. Domain services perform sensitive workflows inside database transactions. PostgreSQL constraints, row/advisory locks and immutable journal triggers backstop application rules. Audit records contain structural metadata and actor/object identifiers, never request bodies.

Backend services include StudentService, SchoolAccess, GuardianIdentity, AcademicCalendar/AcademicSummary, ticket and announcement scope services, financial services, import dispatcher/spreadsheet reader, document data/rendering services, notification service and provider interfaces. Providers keep unavailable SMS/Firebase/WhatsApp integrations separate from core operations. Real integration delivery is never simulated as success.

Flutter features are split across auth, students, administration, academics, finance, parents, communication, offline, files, imports, reports and support. Shared DomainForm/RecordsScreen/SurfaceCard/status components use the centralized navy/teal design system. RecordsScreen supports backend pagination. Student search has paged loading; diary supports date/subject filters and additional pages. Expiry or logout closes previously opened detail routes. Parent navigation uses verified child workspaces; staff navigation filters by permissions. API authorization remains authoritative.

## Identity and sessions

Staff use email/password with hash verification, throttling and a dummy hash for unknown accounts. Parents use a separate CNIC request/OTP verification flow. Both issue existing 12-hour opaque bearer sessions; only token hashes are stored server-side. Remember Me stores the token in Android secure storage; unchecked sessions remain in memory and are cleared from persistent session storage. Passwords are never retained. Session restoration calls `/auth/me`; network failure shows retry instead of granting offline authentication. Logout revokes the server session and associated push devices. Suspension revokes every session/device; reactivation requires fresh sign-in.

CNIC normalization accepts 13 digits with optional spaces/hyphens. Guardian identity/mobile values use readable cnic/mobile columns with unique normalized CNIC lookup; API representations remain masked. OTP challenge correlation retains a keyed HMAC. Keep APP_KEY unchanged for legacy conversion and actual secrets. Follow readable-data-storage.md before upgrading existing records.

## Role and permission matrix

| Role | Student scope | Academic operations | Finance | Communication | Configuration |
|---|---|---|---|---|---|
| Super Admin / MD | All | Write/review/correct | Read/write/approve | Manage/announce | School, staff, imports, exports |
| School Administrator | All | Write/review/correct | Read only | Manage/announce | School, staff, imports, exports |
| Coordinator | All | Write/review/correct | None | Manage/announce | Academic monitoring, exports |
| Accountant / Clerk | All educational profiles | None | Read/write, no management approval | Published notices/history | Exports |
| Teacher | Currently assigned sections/students | Assigned attendance/diary/marks | Own paid salary slips only | Published notices; explicitly forwarded assigned-child tickets, internal replies only | Own staff profile/drafts |
| Parent / Guardian | Verified active linked children | Published results/diary/approved behavior, child attendance | Own child balances/vouchers/receipts | Own coordinator conversations; targeted notices | Own preferences/support |

School administrators cannot create/suspend privileged administrators; only a super administrator can do that. Self-suspension is rejected. Teacher assignments require an active teacher staff profile and a configured class subject. Removing a ticket forward revokes that access. Parent IDs, file IDs, invoice IDs, notification IDs and document IDs are always rechecked server-side.

## Branding and localization

`mobile/assets/1.jpeg` is the supplied official logo; `2.jpeg` is the campus image used by the branded login hero. The originals are unchanged. Android icon/splash sizes are derived locally from the official logo by `mobile/tool/build_branding.php`. Light/dark/system palettes and typography/spacing/radii are centralized. Locale/theme preferences persist through secure local storage. English is LTR and Urdu uses Flutter RTL localization. School record values such as names, user-entered notes and import column identifiers retain their original language; generated documents preserve Unicode content and use mPDF script/font handling.

## Offline synchronization

Only attendance and diary drafts can enter the encrypted per-user queue. State is draft, pending sync, retry, conflict or saved. Stable operation IDs and server payload/version checks protect retries. A draft becomes saved only after an actual server acknowledgment. Conflicts require reviewing the latest server version and explicitly merging/resubmitting; the app never silently overwrites. Reference catalogs and attendance rosters have per-account encrypted caches with a limited lifetime. Cached data is clearly marked when used. Financial writes are never queued as successful offline transactions. Reopening an authenticated session still requires online server validation.


## Final RBAC/authentication/dashboard validation — 2026-10-23

Current results supersede earlier historical counts: PostgreSQL **118 tests / 864 assertions PASS**, Flutter **64 tests PASS**, analyze clean, Pint passed, three functional native cases passed across runs, emulator and phone normal-entry debug APKs built. See [final-auth-dashboard-report.md](final-auth-dashboard-report.md), [35-item RBAC report](final-rbac-report.md), [shared matrix](rbac-matrix.md) and their file inventories.


Local configuration caveat: invalid existing APP_KEY must be restored from original backup; not rotated. Separate isolated test key is not a replacement for the development key. No real data or secrets in reports.


## OTP/private-photo/visual follow-up — 2026-10-23

Current pass: PostgreSQL 129 tests/960 assertions PASS; Flutter 76 PASS; analyze/Pint/format clean; four functional native cases passed across runs; physical-phone debug APK built. See [final-media-report.md](final-media-report.md), [local OTP commands](local-parent-otp.md) and [file inventory](final-media-files.md). Current key/decrypt/lookup integrity checks pass; earlier APP_KEY-invalid notes describe the earlier session only.

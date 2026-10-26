# Final OTP, private profile photos and visual-design report

Verified 2026-10-23 (Asia/Karachi).

## 1. Parent OTP root cause

Current failure reproduced as HTTP503, not an assumed Flutter error: SMS_DRIVER was unset and the existing configuration defaults to disabled, so ParentAuthController rejected before delivery. The pre-existing all-interface Artisan HTTP child also lacked the parent PDO/GD flags. Historical500s were not falsely attributed from sanitized logs. At this pass the current APP_KEY is valid; an aggregate-only integrity check confirmed the existing encrypted guardian record decrypts and its keyed CNIC lookup matches. No identities, key values or credentials were exposed.

## 2. Exact OTP fix

api/serve-local.ps1 now explicitly opts its local process into the existing encrypted simulator, checks APP_ENV=local, directly runs the extension-correct HTTP worker, restores its process setting on exit and leaves .env untouched. Current exact Wi-Fi endpoint returns neutral202 for an unknown fictional identity. Added cache-write failure handling with transaction rollback, configured expiry TTL, and optional latest-pending CLI challenge selection. Parent/staff authentication remain separate. Flutter resend previously validated an empty OTP input and could block resend; this is corrected. Expiry countdown, back-to-correct-CNIC and flow-specific feedback are included.

## 3. Local-development testing

See [local-parent-otp.md](local-parent-otp.md) for complete commands. Start api/serve-local.ps1; request in the app; in another local terminal set SMS_DRIVER=local and run `php -d extension=pdo_pgsql -d extension=gd artisan school:local-otp`, optionally with a specific challenge UUID. This retrieves a one-time local encrypted delivery; enter the code through normal verification. No raw code is returned by the API, no hardcoded permanent code, and no values are published in reports.

## 4. Production SMS status

No real SMS gateway, adapter credentials or real delivery was configured/tested. Production remains disabled until a real provider is connected through the existing SmsProvider contract and validated. Local send/retrieval are environment restricted. Existing expiry/cooldown/attempt/rate limits and consumption remain authoritative.

## 5. Student photo root cause

Inspection found photo IDs omitted from student list payloads, no actual avatar in student detail (only a file-opening button), no photo controls in create/edit forms and silent image-download failures. Device validation additionally reproduced `Connection closed while receiving data` during a private-image read, despite a matching stored file and a real server200 with correct JPEG content length. This is a transport truncation, not an authorization failure or a reason to make media public.

## 6. Student photo fix

Student list now returns photo_file_id; lists/details render reusable authenticated avatars with initials/icons, loading and retry. Forms provide optional preview/change/remove controls and later upload from detail. Existing /files upload is reused. Private reads recover from up to two ClientException failures within the existing overall30-second timeout. Only private-file GET reads retry; writes/auth requests and HTTP401/403/500 never replay. No internal transport diagnostics remain in production source.

## 7. Guardian photo implementation

Small UUID migration adds parent_guardians.photo_file_id and private_files.parent_guardian_id. Optional create/edit picker, preview, later upload, replacement and removal share the same service and widgets as students. Directory/detail/parent header display current photo or fallback. Guardian creation without an image remains valid. User profile/parent-home serialization includes only the authorized photo reference.

## 8. Media security and performance

Existing authenticated GET /files/{uuid}; no public media URL, filesystem path or storage symlink required. Student access uses current SchoolAccess; guardian reads require scoped guardians.manage or the guardian’s own parent account; writes require scoped students.manage/guardians.manage. Cross-parent and unrelated staff denied. Current-reference checks deny replaced/removed photos. Validate actual JPEG/PNG/WebP, extension, configured size limit (default5MB), decode validity and at most24MP. Server re-encodes to a metadata-free JPEG no larger than768px, with generated UUID filenames. Existing client compressor retains orientation handling and temporary files are deleted. Rendering retains bytes per mounted avatar/ID and decodes at display size; no new disk photo cache. Old file rows/bytes are retained but inaccessible through current-photo delivery; storage retention cleanup is an operator policy, not a destructive auto-purge.

## 9. Login visual changes

Preserved original logo1/campus image, staff email/password, visibility, Remember Me, forgot-password guidance and separate CNIC/OTP. Central royal-blue/teal hero overlay, clear branded header/slogan, tinted authentication cards and icon Staff/Parent selector. Parent input guidance, visible code expiry, resend state, correction action and invalid/expired feedback. Flow switching clears stale errors without merging parent/staff login contracts.

## 10. Internal design changes

Centralized royal/navy, teal, education-indigo, emerald and amber semantic palette; cool neutral canvas and intentional navy/slate dark surfaces. Reusable cards, KPI cards, module cards, welcome panel and action tiles receive restrained semantic accents/borders. Existing lists/forms/loading/error/empty states remain reusable. Authenticated student/guardian profile headers and avatars add recognizable identity. No architecture/workflow rewrite or dependency additions.

## 11. Super Admin dashboard

Existing real-data summary endpoint and backend scopes retained. Existing student/active/staff/teacher/guardian/school/attendance/finance/request/notification/announcement aggregates remain real. KPI cards and welcome header use semantic tints; quick actions include authorized Add guardian alongside student/staff/attendance/finance/announcement/report actions. No new fabricated counts, trends or hardcoded identity.

## 12. Student/guardian forms

Reusable FieldKind.photo integrates a ProfilePhotoDraft, preview, select/change/remove and localized optional/add-later guidance. Student fields grouped into profile/contact/academic sections over existing supported inputs. Guardian create/edit reuse the same photo control. Existing save fields and authorization remain unchanged. Mobile profile creation and photo upload use two existing API operations; if the photo fails, the created record remains and the same open form reuses its saved ID on retry. Closing after a photo failure requires editing that record rather than creating it again. Backend multipart create also accepts an optional photo and validates it before transactionally creating its record.

## 13. English/Urdu

Every new user-facing label uses the existing English/Urdu maps. Full widget suite includes translation parity, RTL forms/filters/login/dashboard, small-to-wide layouts and photo controls at320px with1.6 scaling. Native English and Urdu dark authentication and existing role/prefs workflows passed. New native picker type labels also use the same localization map.

## 14. Light/dark/system

Preserved AppPreferences architecture and persisted modes. Central colors use appropriate light/dark surfaces/accent contrast. Existing native role test verifies light, dark, system and saved preferences; new photo/OTP case verifies Urdu/dark auth. Widget coverage includes320/390/768/1440 widths and keyboard/text scaling.

## 15. RBAC/security

All six roles and existing permission grants/scope services retained; no new RBAC system. Tests cover linked/unrelated children, current teacher scope, guardian-only ownership, unauthorized upload/removal, malformed/oversized/corrupt images, superseded images, OTP hashing/expiration/replay/limits/session/logout and production provider refusal. Existing financial/private-file/IDOR/regression coverage remains in the full suite. No .env edits, default credentials, production test data or secret logging.

## 16. Changed/created files

Complete request-entry hash comparison and per-file reasons: [final-media-files.md](final-media-files.md). Existing repository source was untracked, so these statuses compare with the inspected request-entry inventory, not a Git commit. Generated screenshot/APK evidence is separately listed. No auto-commit or web portal.

## 17. Laravel result

Full guarded PostgreSQL suite: **129 tests, 960 assertions, PASS**. Test database infotech_school_test on loopback55433, separate private validation key; development database not reset. Added photo migration applied forward to development (15 total migrations). Roles6 and permissions19 retained. Test counts supersede earlier historical reports.

## 18. Flutter result

Complete unit/widget suite: **76 tests PASS**. Separate native integration workflows are not added to this count. New tests cover optional photos, avatar fallback/retry, preview/removal, RTL/dark small layout, OTP resend/expiry/errors/restore and bounded private-read retry without replaying writes.

## 19. Analyze

Final `flutter analyze`: **No issues found**. `dart format .` executed; final no-change formatting check passed. No dependency upgrade required.

## 20. Pint

Final Laravel Pint project check: **PASS**. The old signature-valid but GD-decode-invalid PNG fixture was replaced with a valid image for its successful-upload test; its original corrupt bytes are retained in explicit rejection coverage. No meaningful test was deleted.

## 21. Emulator evidence

Medium Phone API37 / emulator-5554, loopback API mapped through10.0.2.2:8000. Four functional native cases passed across runs: existing six-role workflow; actual process-stop session preparation; restoration; new photo/OTP workflow. Six roles, scoped navigation, attendance submit, diary publication, coordinator public/internal communication, parent both-child switching, themes/languages/preferences/logout/restoration were rerun. Student creation without a photo was verified through the actual form-save callback during the device iterations; the repeatable media case reuses that fictional record on retry, then uploads/replaces/removes and renders the student photo through real authenticated Android HTTP, creates guardian without a photo/adds later/renders it, obtains a local simulator code via guarded host injector, verifies through parent UI, checks two authorized children and guardian photo, restores remembered parent session and logs out. No claim of manually operating the Android system chooser or every create/edit field: picker selection/preview is widget-tested with injected image bytes; multipart create-with-photo is backend-tested. Native screenshots are in screenshots/final-media (9) and refreshed screenshots/final-dashboard (36), visually reviewed. Earlier iterations caught transport truncation and a test that asserted before asynchronous image load; final code adds bounded read recovery and the test waits for actual rendering. Temporary transport probes were removed. Normal main-entry emulator launch also verified.

## 22. APK

**Build succeeded.** Physical-phone debug APK: `E:\info-tech-school-management\mobile\build\app\outputs\flutter-apk\app-debug.apk`. API_BASE_URL: `http://192.168.100.107:8000/api/v1`; Wi-Fi address checked immediately before build. Size 228,120,402 bytes; SHA-256 `03ee846531b95b637e9de4154c5f0f47c315a9687df6978beaf17ce50fad4bc7`. Kernel inspection confirms the requested URL and normal main entry rather than an integration-test entry. Separate normal-entry emulator artifact: `mobile/build/app/outputs/flutter-apk/app-debug-emulator.apk`. No physical handset was connected or tested.

## 23. Remaining manual/production items

No real SMS delivery, physical phone installation/network/firewall path, actual native gallery/document picker/camera source, real camera metadata/orientation on varied devices, release signing/HTTPS rollout or production external delivery credentials were certified. Perform those handset checks with fictional records. Existing production SMS/Firebase/push deployment items remain in production-configuration.md. Legacy photos are not automatically resized/backfilled; replacement normalizes them. Storage retention/purge, large-volume performance and release deployment require operator review. Existing APP_KEY issue from the earlier report is now resolved by the current local configuration: valid key, successful existing-data aggregate integrity check; no key rotation by this implementation. Local debug HTTP and simulator are development tools. No web portal started.

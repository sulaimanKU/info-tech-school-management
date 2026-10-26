# Complete mobile API reference

The current API has 126 routes. [Machine-readable route inventory](api-route-inventory.json) lists methods, URIs, controllers and middleware. Functional contracts and permission/object scopes are documented in [architecture](mobile-architecture.md), [domain workflows](domain-workflows.md), [imports/documents](imports-and-documents.md) and [production configuration](production-configuration.md).

Route groups: staff auth/session/history and account status; parent OTP; students/guardians/catalogs/teachers/files; attendance/diary/results/behavior/coordinator/exams; fees/payments/refunds/expenses/payroll/journals; parent home/tickets/announcements/notification/devices; imports/biometrics/reports. The original four foundation contracts below are preserved, not the complete route list.

# API v1

Base: `/api/v1`. Send `Accept: application/json`, JSON request bodies and `Content-Type: application/json`. All responses use `{"data": ..., "error": null}` on success, or `{"data": null, "error": {"code": 401, "message": "Authentication failed.", "fields": {}}}` on failure. No raw exception message or stack trace is returned.

| Method | Path | Access | Result |
|---|---|---|---|
| POST | `/auth/login` | Public, rate limited | token, token_type, expires_at, user |
| GET | `/auth/me` | Active, valid session | id, name, email, roles (code, name) |
| POST | `/auth/logout` | Active, valid session | logged_out: true |
| GET | `/school-settings` | Active session + settings.read | School reference settings |

Login body: `{"email":"<your-development-email>","password":"<your-private-password>"}`. Email is required and at most 254 characters; password is required and at most 1024. Passwords never appear in response data. Failed credentials, suspended accounts and parent-only accounts share HTTP 401. Successful sessions expire after 12 hours; save the token securely and attach `Authorization: Bearer <token>` on subsequent requests. Logout is a POST with an empty JSON object, and revokes that token immediately.

`401`: missing, expired, revoked, invalid token or failed login. `403`: permission denied. `404`: unknown resource. `405`: wrong HTTP method. `422`: invalid input with field error arrays. `429`: login limit with Retry-After header. `500`: safe generic server failure. Rate limits: 10 attempts/minute/IP and 5/minute/account/IP, counting all login requests. Validation errors do not echo supplied values. Never include request bodies or Authorization headers in proxy/access logs.

The permission-protected settings endpoint verifies RBAC using persisted grants rather than any mobile UI state. Seeded Super Admin and School Administrator roles have access. Other roles receive 403. No role-assignment or settings-write endpoint exists yet.

## Mobile conversation presentation metadata

`GET /api/v1/tickets/{ticket}` includes an additive `sender_kind` field on each authorized message: `parent` for the ticket's guardian, otherwise `staff`. The mobile UI uses this server-derived value to label public messages; it does not infer roles from display names. `sender_user_id` is not returned. Existing `is_internal` filtering remains enforced server-side for parents, and ticket/file authorization is unchanged. PostgreSQL regression tests verify both labels and parent internal-note exclusion.

The UI polish introduces no database migration, route replacement or permission change. See [the complete UI/UX report](final-ui-report.md) for visual changes and verification scope.


## Final RBAC/authentication/dashboard validation — 2026-10-23

Current results supersede earlier historical counts: PostgreSQL **118 tests / 864 assertions PASS**, Flutter **64 tests PASS**, analyze clean, Pint passed, three functional native cases passed across runs, emulator and phone normal-entry debug APKs built. See [final-auth-dashboard-report.md](final-auth-dashboard-report.md), [35-item RBAC report](final-rbac-report.md), [shared matrix](rbac-matrix.md) and their file inventories.

New authenticated staff-only `GET /api/v1/dashboard-summary` uses existing scopes and real aggregate data; parents receive 403. Staff/guardian/assignment/RBAC/filter extensions are described in the final report. Existing auth contracts unchanged.

Local configuration caveat: invalid existing APP_KEY must be restored from original backup; not rotated. Separate isolated test key is not a replacement for the development key. No real data or secrets in reports.


## OTP/private-photo/visual follow-up — 2026-10-23

Current pass: PostgreSQL 129 tests/960 assertions PASS; Flutter 76 PASS; analyze/Pint/format clean; four functional native cases passed across runs; physical-phone debug APK built. See [final-media-report.md](final-media-report.md), [local OTP commands](local-parent-otp.md) and [file inventory](final-media-files.md). Current key/decrypt/lookup integrity checks pass; earlier APP_KEY-invalid notes describe the earlier session only.

Private photo upload: existing POST /api/v1/files with purpose=photo, file and exactly one student_id or parent_guardian_id. JPEG/PNG/WebP only, normalized JPEG response metadata. GET /files/{uuid} remains authenticated/scoped. DELETE /students/{uuid}/photo and /guardians/{uuid}/photo clear the authorized current reference. Student/guardian POST accepts optional multipart photo; existing JSON contract unchanged. Lists/profile payloads include photo_file_id. Parent OTP contract unchanged; latest pending code retrieval is local CLI only, never API.

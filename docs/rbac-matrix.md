# Shared backend authorization contract

This matrix describes default seeded grants and the current Laravel scopes. Future clients must reuse these APIs and policies. Current database grants remain authoritative; defaults are not a substitute for checking the current profile. An active Super Admin has a server-side permission bypass. Other roles receive only their assigned grants. A hidden Flutter action is never authorization.

| Role | Default permissions | Object scope |
| --- | --- | --- |
| Super Admin / Managing Director | All 20; active-role server bypass | All applicable school objects |
| School Administrator | Operational administration; excludes rbac.manage, finance.write, finance.approve | School scope where students.read.all granted |
| Coordinator | students.read, academics.read/write/review, attendance.correct, communication.manage, announcements.manage, reports.export | Current assigned classes/sections and valid record dates; no default students.read.all |
| Accountant / Clerk | students.read, students.read.all, finance.read/write, reports.export | Finance and necessary student identity; no unrelated academic/private access |
| Teacher | students.read, academics.read/write | Current dated section/subject assignments intersected with record date |
| Parent / Guardian | No staff permission grants | Active, verified own guardian-child links and verified mobile; published/parent-visible records only |

## Permission matrix

S=Super Admin, A=Administrator, C=Coordinator, F=Accountant, T=Teacher. Parent uses child-scoped endpoints, not staff grants.

| Permission | Default roles |
| --- | --- |
| settings.read | S, A |
| students.read | S, A, C, F, T |
| academics.read | S, A, C, T |
| students.read.all | S, A, F |
| students.manage | S, A |
| guardians.manage | S, A |
| school.manage | S, A |
| staff.manage | S, A |
| academics.write | S, A, C, T |
| academics.review | S, A, C |
| attendance.correct | S, A, C |
| finance.read | S, A, F |
| finance.configure | S, A (fee schedules, fee assignments, discount policies, payment methods only) |
| finance.write | S, F |
| finance.approve | S |
| communication.manage | S, A, C |
| announcements.manage | S, A, C |
| reports.export | S, A, C, F |
| imports.manage | S, A |
| rbac.manage | S |

## Enforcement

`User::hasPermission` / `isSuperAdmin`, existing roles, permission_role and user_role_assignments remain the sole RBAC model. `SchoolAccess`, `AcademicFilters`, `AnnouncementAccess` and `TicketAccess` enforce scope. List filters intersect scope and never grant access. Direct objects, private files, receipt/payment allocations, import actions and reports require backend checks. Subject/section assignments are checked both now and at the academic record date. Suspended users and revoked sessions are rejected.

Staff administrators cannot grant Super Admin or manage RBAC; the last active Super Admin is protected. Role/status changes revoke sessions. Guardian identity/mobile use readable database columns and remain masked in API responses; exact CNIC lookup uses the unique normalized cnic column. Unlink/contact/status changes revoke affected parent sessions/challenges/push registrations. Parent password staff login is rejected. Parent internal communication notes and unpublished results remain inaccessible.

Announcements support school/class/section/role/individual targeting under publisher scope. Scoped publishers cannot enlarge their audience using a class parameter. Parent announcements intersect the selected authorized child. Finance payment/receipt access checks every allocation, not just one matching child.

Default grant changes do not silently restore legacy revoked grants. Migration adds only the three new permission grants, removes the Coordinator global-student default, and retains the existing role system.

28 October 2026 continuation: seeded administrators receive finance.configure instead of finance.write/finance.approve. Configuration permission never authorizes payroll, compensation, adjustments, payments, expenses, discount approvals or journal posting. Existing explicitly assigned database grants remain authoritative and are not silently revoked by an insert-only seeder; review any grants created by older seeder versions before a future release.

# RBAC audit before implementation — 22 October 2026

Actual architecture: users ↔ user_role_assignments ↔ roles ↔ permission_role ↔ permissions. No per-user direct permission table or Laravel policies; RequirePermission, SchoolAccess, service/controller checks and authenticated sessions provide enforcement. Staff have ordinary email/password sessions; parents use separate encrypted-identity lookup and OTP sessions.

| Role | Actual grants/scoping before this pass | Required correction |
|---|---|---|
| Super Admin | 16 seeded permissions; no role bypass | Active-user server bypass and current-user permission projection |
| Administrator | students.read.all/manage, guardians/school/staff management, academics write/review/correct, finance.read, communication/announcement management, exports/imports/settings | Preserve operational grants; explicitly protect RBAC/privileged targets |
| Coordinator | students.read.all, academics write/review/correct, communication/announcement management, exports | Remove default school-wide grant; add existing-model staff/section assignments; scope coordinator counts, records, tickets, publishing |
| Accountant | students.read.all, finance.read/write, reports.export | Academic read routes currently infer access from section visibility; add explicit academic permission gates and finance-only student actions |
| Teacher | academics.write; student/section scope from active teacher_assignments and dated current enrollment | Scope catalogs and subject reads; enforce dates and assignment changes consistently |
| Parent | No permission grants; verified active parent_student_links | Preserve child-specific navigation, published records and private-note filtering; close subject/catalog and cross-child edge cases |

Other findings: catalog sections are scoped but classes/subjects/sessions/class-subjects are globally returned; staff list lacks role/assignment summary and edit API; guardian list lacks search/mobile/link detail/edit/unlink; coordinator index is unscoped; TicketAccess communication.manage bypasses academic scope; announcement manager can target/read all audiences without scope; list/report filters incomplete. Existing financial approval checks and parent receipt scoping must remain intact. Existing student, roster, parent, private-file and academic revision mechanisms will be extended rather than replaced.

Baseline validation evidence from preceding completed task: 78 PostgreSQL tests / 542 assertions; 39 Flutter tests; analyzer clean; normal debug APK. These are baseline counts, not the result of the new RBAC pass.

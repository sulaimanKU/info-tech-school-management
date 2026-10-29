# Settings functional audit

Local implementation and test audit, 29 October 2026. Permissions are checked by APIs as well as navigation. Super Admin is abbreviated SA, School Administrator A, Coordinator C, Accountant K, Teacher T, Parent P. Custom grants still apply; role names alone do not override permission checks.

| Setting | Role / permission | UI | API (prefix `/api/v1`) | Persistence / effect | Status / notes |
|---|---|---|---|---|---|
| School name, campus, location | SA/A `school.manage` | Settings → School information | GET/PUT `school-settings` | `school_settings`; report branding and school info reload | Fixed: validated allowlisted write, saved values used in exports |
| Address, phone, email, website | SA/A `school.manage` | Same profile editor | Same | `school_settings`; safe information round trip | Added; nullable contact fields; URL/phone/email validation |
| Default academic session | SA/A `school.manage` | Same profile editor | Same; GET `catalog/sessions`, `academic-filter-options` | `school_settings`; creation forms prefer configured allowed session | Added; never expands actor's section scope; unset falls back to date-current session |
| School information read | All six authenticated roles | Account → School information | GET `school-information` | Safe allowlist, no integration configuration | Added; readonly for nonmanagers |
| Logo | Bundled app asset | Profile explanatory text | None | App asset / existing report brand asset | Intentionally not editable; no nonfunctional upload control |
| Currency and time zone | Server policy | Profile readonly | Profile GET; PUT rejects changes | PKR and Asia/Karachi preserved | No editable control that could reinterpret historic records |
| Sessions and dates | SA/A `school.manage` | Settings → School setup | GET/POST/PATCH `catalog/sessions` | `academic_sessions` | Fixed populated rename with unchanged dates; date changes with dependent records remain protected |
| Classes / sort order | SA/A `school.manage` | School setup | `catalog/classes` | `classes` | Existing working CRUD retained |
| Sections and class/session relationship | SA/A `school.manage` | School setup | `catalog/sections` | `sections` | Fixed populated rename with unchanged relationship; reassignment of populated context blocked |
| Subjects / codes | SA/A `school.manage` | School setup | `catalog/subjects` | `subjects` | Existing CRUD and validation retained |
| Class-subject map | SA/A `school.manage` | School setup | `catalog/class-subjects` | `class_subject_assignments` | Existing relationship validation retained |
| School weekdays | SA/A `school.manage` | Settings → Academic settings | GET/PUT `academic-settings` | `school_settings`; past calendar snapshots in `school_days` | Fixed atomic save; new policy prospective; submitted history protected |
| Class position display | SA/A `school.manage` | Academic settings | Same | `school_settings`; published-result display policy | Saves/reloads in same transaction |
| Grading bands by session | SA/A `school.manage` | Academic settings | Same PUT; existing PUT `grading-rules` retained | `grading_rules`; future assessment grading | Fixed shared validation, mandatory 0% floor and atomic combined save; published grades retained |
| School-day / holiday overrides | SA/A `school.manage` | Academic settings → Calendar | POST `school-days`, GET `academic-settings` | `school_days` | Session bounds and submitted day-type protection; automatic history snapshots hidden from manual override list |
| Teacher assignment | SA/A `school.manage`, C `assignments.manage` | Settings / Academics → Teaching assignments | GET `teaching-assignments`, GET `teaching-assignments/teachers`; POST/PATCH `catalog/teacher-assignments`; POST `…/{id}/deactivate` | `teacher_assignments`; immediate roster/access effect | Added scoped management; dates, valid teacher/subject, overlap and old/destination scope checks |
| Coordinator assignment | SA/A `school.manage` | School setup / existing staff assignment UI | `catalog/coordinator-assignments` | `coordinator_assignments` | Existing admin management retained; coordinator cannot expand own scope |
| Staff profiles / account status / reset | SA/A `staff.manage` (role hierarchy applies) | Staff management and school setup | `staff-accounts`, status/password endpoints; `catalog/staff` | Users, role links, staff profiles, session revocation | Existing protections retained; exact password text preserved by shared form; minimum four characters, hashed |
| Role permission grants | SA `rbac.manage` | Access control | Existing `rbac/*` routes | `permission_role` | Existing anti-escalation rules retained; new narrow `assignments.manage` permission |
| Fee schedules by session/class/date | SA/A `finance.configure` | Finance configuration | `finance/fee-schedules` | `fee_schedules`; live future quote/posting | Existing validated CRUD/upsert retained; no past invoice rewrite |
| Individual tuition overrides | SA/A `finance.configure` | Finance configuration | `finance/fee-assignments` | `student_fee_assignments` | Existing effective date / history preserved |
| Discount policies / student concessions | Policy edit `finance.configure`; grant/approve per existing finance permissions | Finance configuration / concessions | Existing finance policy/concession routes | Discount tables + approvals | Existing authorization, limits and nonstacking preserved |
| Payment method name, active flag, manual review | SA/A `finance.configure`; K readonly `finance.read` | Finance configuration | PATCH `finance/payment-methods/{id}` | `payment_methods`; future entries | Existing cash rules, ledger mapping, pending review and history retained; no gateway added |
| Salary / compensation | Explicit `payroll.write`; approval separately restricted | Payroll | Existing payroll compensation routes | Compensation/payroll tables | Existing manual payroll, partial payments and approval preserved |
| SMS / push integration | Server-only credentials | Profile metadata for managers | GET `school-settings` | Laravel config / environment; no API key write | Driver/configured flags only; keys and provider URLs excluded; no fake credential editor |
| Language | Every app user | Toolbar language toggle | None | Existing device preference store | English and Urdu complete; new labels translated and completeness-tested |
| Theme / system appearance | Every app user | Toolbar appearance menu | None | Existing device preference store | Existing light/dark/system save/reload tested |

The Settings hub now links to real destinations. Nonmanagers obtain school information without the administration hub. Every exposed editable item above uses an existing or implemented endpoint and reloads persisted state. Safe school profile responses never return arbitrary `school_settings` rows or provider credentials.

Primary regressions fixed: a readonly/arbitrary settings endpoint with no profile write; academic weekday/position save and grading save split across requests; populated catalog edits rejecting unchanged foreign keys; optional selections that could not be cleared; exact password/boolean values transformed by the shared form; future homework loading its roster from future-forbidden attendance. New tests verify profile fields, secret rejection, every role's API access, atomic rollback, historical calendar stability and UI save/reload.

Operational setup remains necessary: review sessions, grading bands, fee schedules, profiles and assignments for the actual school. Provider setup stays in the server environment. No production settings or credentials were touched.

# Teacher and coordinator workflow

Validated locally on 29 October 2026. Existing assignments, academic history, finance records and credential hashes are retained.

## Setup and access

An administrator creates an active staff account with the Teacher role, links its staff profile, and assigns a section and its configured subject. A class teacher assignment can also be recorded. Assignment dates must fall inside the section's academic session; overlapping duplicate assignments are rejected. Nonoverlapping assignments are allowed. Deactivate an assignment to withdraw access without deleting history.

Settings → Teaching assignments is available to school managers. Coordinators enter through Academics → Teaching assignments. The separate `assignments.manage` permission allows assignment management without granting staff account, RBAC, payroll or global school configuration access. Every coordinator read and mutation is restricted to authorized sections. Changing an assignment checks both its original and destination section. Coordinator-to-section assignment remains an administrator action.

## Daily teacher workspace

Home → My classes opens `GET /api/v1/my-teaching`. The server derives sections and subjects from the signed-in teacher's active staff profile and current, active assignments. It excludes assignments whose dates have not started or have ended, inactive staff, and academic sessions outside today's date. Multiple subjects in the same section do not multiply the student count.

Each class shows the session, section, assigned subjects, enrolled student count and today's attendance state. The workspace also shows pending assessments, the teacher's recent diary and published exams over the next 30 days. An empty assignment state explains that administrator setup is needed. The screen explicitly states that a daily lesson timetable is not configured; assignments are not presented as a fabricated timetable.

Open class → My class students uses `GET /api/v1/my-teaching/sections/{section}/students`. Search and pagination run on the server. Rows show names, registration numbers, roll numbers and authorized photos. UUIDs are internal relationship identifiers. The existing student profile remains subject to server authorization.

## Attendance

Open Attendance from the class. The section is preselected. The register uses the session calendar and the date's enrollment roster. Present, absent, excused and late totals are visible. School holidays use holiday defaults. Future attendance and dates outside the session are rejected by the backend.

Draft/submitted state, request idempotency and version checks remain in place. Teachers cannot silently revise submitted attendance; authorized corrections retain revisions. A successful submission clears that section's pending attendance count. Calendar day type cannot be changed beneath already submitted attendance. Weekday changes preserve previous calendar history, including today when already submitted.

## Homework / diary

Open Add homework or the existing Diary screen. Section and assigned subject are preselected where available. Student selection comes from `GET /api/v1/academic-roster?section_id=…&date=…`, independently of attendance. Future homework can therefore obtain a valid future enrollment roster without permitting future attendance.

Dates must be within the session. Current and record-date assignments are checked. Teacher draft/submission, reviewer approval/publication, attachment authorization and revision history remain intact. Parents receive only published records within their verified child relationship, including valid future homework.

## Assessments and results

Teachers select their assigned section and subject, create an assessment, enter marks for its actual roster and submit it. Reviewers can create an assessment for an eligible assigned teacher using the section/subject/date-filtered teacher selector. Arbitrary staff accounts cannot be chosen. Eligible teachers must have an active account, Teacher role, active staff profile and assignment valid on the assessment date.

Maximum marks, enrollment membership, version conflicts, authorship, review state and self-approval restrictions are enforced by the server. The UI follows `can_edit` / `can_approve`, rather than offering actions guaranteed to fail. Published grades are snapshots; changing grading configuration does not rewrite existing published results. Parents see only published results for verified linked children.

## Coordinator work

Coordinators retain scoped student reads, attendance corrections, diary and assessment review, exam schedules, observations, follow-ups, announcements and academic exports according to their permissions. They can create, edit and deactivate teaching assignments within their authorized sections and search a minimal teacher picker (name, employee/account code). They cannot use this picker to manage accounts, change salaries, grant roles, change global academic settings or assign themselves new coordinator scope.

Both navigation and endpoints enforce permissions. Hidden controls are not the security boundary: direct requests to another section or unauthorized module are rejected. Revocation takes effect on subsequent API requests.

## Local verification and practical limits

`TeacherWorkspaceTest`, `SettingsFunctionalTest`, `RoleFunctionalReportsTest`, existing academic/RBAC tests and `full_role_functional_test.dart` cover the workflows above. HTTP clients/providers are faked in tests. No production classroom or device smoke test was performed.

Rosters and calendars require configured session dates and enrollments. Assessments require grading bands including a 0% band. A class roster write retains the existing 200-student request limit; paginated class browsing is 30 rows. Historical academic records remain accessible through authorized existing screens/reports; the daily workspace intentionally shows current sessions only.

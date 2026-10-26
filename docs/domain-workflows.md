# Mobile domain workflows

## Students and guardians

Admission allocates an immutable UUID and serialized permanent registration `INF-SG-YY-####`. The registration number does not change on promotion. Student details use a whitelist; registration/creator identifiers cannot be mass-assigned. Enrollment snapshots retain class, section, session, roll and effective dates. Withdrawal, transfer, re-admission, promotion and section changes create transition history rather than deleting records. Promotion requires a higher class in a new session. Active roll numbers are unique per section. Teacher student access follows dated enrollment, including before future-effective promotions. Parent links are separately verified, many-to-many and revocable; one active primary guardian per child is enforced.

Administrators create sessions/classes/sections/subjects, assign subjects to sessions/classes, create staff accounts/profiles and teacher/class-teacher assignments. Catalog editing preserves existing identity relationships; sessions with enrollments cannot have their dates rewritten. Assignment changes use explicit deactivation/replacement. Default 2026-27 session dates (April 1–March 31) are setup assumptions and must be confirmed/edited before enrollment. They are database values, not embedded business rules.

## Attendance and diary

The school calendar configures teaching weekdays and dated overrides. Full-roster submitted attendance supports present, absent, late, excused leave and school holiday. Holiday status must match the calendar; duplicate student/day and register records are prevented. Submitted corrections require `attendance.correct`, optimistic versions and revision snapshots. Monthly percentages use configured school days; missing records are disclosed and percentages are null when nothing has been recorded.

Diary entries contain section/subject/date, teaching topic, homework, teacher and whole-class or selected-student audience. Drafts, publication, review and revision history are explicit. Parents see published entries for their child's dated enrollment and selection. Attachments are private JPG/PNG/WebP/PDF files; metadata includes uploader/timestamps. Android compresses/resizes images and removes EXIF before uploading. Images support zoom; PDF viewing uses an explicit external viewer and app-private temporary copy. Date and subject filters retain history.

## Assessments, behavior and coordination

Monthly/unit/midterm/final/oral/assignment/practical assessments require configurable grading bands including a zero-percent band. Obtained marks cannot exceed maximum marks. Full-roster marks move draft → submitted → independently approved → published. The author cannot approve their own results. Published results are read-only; coordinators explicitly return submitted/approved work to draft for correction. Parent summaries include only published records, subject/overall percentages and historical results; class position is opt-in configuration.

Behavior uses constructive observation areas/categories and a separate reviewed parent-visible publication flag. Sensitive notes are private and no public negative ranking is produced. Coordinator records cover syllabus progress, learning gaps, classroom observations, follow-ups, issues and parent communication. Monitoring uses actual attendance/diary/review records. Teacher punctuality is not fabricated without actual staff attendance hardware/data.

## Finance and accounting

All money is integer PKR minor units (100 paisa per rupee), with exact client parsing. Default 2026-27 monthly tuition is Playgroup/Nursery 1000, Prep 1200, Grade 1 1400, Grade 2 1600, Grade 3 1700, Grade 4 1800, Grade 5 2000, Grades 6/7 2400, Grade 8 2600 rupees. Admission/promotion fee defaults to 3000. Values are session/class database schedules; overrides are approved, effective-dated records.

Discount policies include sibling 20%, imam children 50%, orphan 30%, merit up to 70% and management-approved special discounts. Requests contain eligibility/effective dates/evidence, require approval and may be revoked explicitly. Sibling approval checks verified shared guardian links. The implemented stacking policy chooses the single highest applicable monetary discount; it never adds percentages. Existing invoice values are snapshots and do not change when future schedules/policies change.

Vouchers have serialized unique numbers, invoice lines, dates, discounts, additional/opening charges and derived paid/partial/unpaid/overdue/adjusted status. Old balances are reported as arrears without duplicating old charges in each new invoice. Final invoices/lines are immutable; authorized adjustments are separate records. Payments support partial/full and multiple invoice allocations, exact totals, cash/bank/configured methods and unique request/reference protection. Bank payments remain pending and do not reduce balances until verified. Receipts exist only for verified payments. Refund allocations reopen the correct invoice balance and post separate immutable journal entries. Supporting proofs remain private.

Every expense above PKR 500 requires prior management approval, enforced in service logic and a PostgreSQL transition guard. Paid expenses cannot be silently edited. Payroll requires approved effective compensation for every eligible active staff member, authorized adjustments/deductions, period generation, approval and later disbursement. Completed periods are immutable; later adjustments use a new period. Own paid salary slips are available to staff. Financial locks serialize generation and adjustment workflows.

The ledger posts balanced debit/credit lines to configured cash/bank, receivables, revenue, scholarship/expense, salary, payable and opening-equity accounts. Posted journals/lines are immutable; explicit reversal records preserve history. Deferred PostgreSQL checks enforce balance at commit. The mobile accounting view/report is a trial balance, not an income-minus-expense “balance sheet”. Domain corrections use invoice adjustments/payment refunds; unrestricted arbitrary ledger reversal is not exposed to clients because it could diverge from source records.

## Communication and notices

Parents choose a linked child/category, submit a message and permitted private attachments, receive acknowledgment and track responses/status/resolution. Coordinators manage priority/assignee, internal notes/forwarding, escalation and resolution. Status transitions are validated. Teacher access requires explicit forwarding plus current class scope; teachers can add internal notes, not impersonate a coordinator response. Parents never receive private teacher phone numbers or internal notes/attachments. Message creation retries are content-sensitive; duplicate message retries do not increment workflow versions. Events/messages are append-only.

Announcements support school notice/holiday/exam/result/PTM/fees/event/emergency/homework/admission categories, drafts, publication timing, expiry/archive and targeted audiences (all users, parents, class, individual parent, teachers, staff). Read states and notification history are per-user. Scheduled publication notifications are processed by the scheduled command. Notifications navigate to authoritative protected records; they do not bypass access checks.

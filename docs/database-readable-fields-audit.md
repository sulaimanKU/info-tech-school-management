# Database readable-fields audit

Audited 29 October 2026 (Asia/Karachi). Complete guarded disposable MariaDB 10.11.14 schema: **86 tables and 713 columns** after validated legacy cleanup. Schema export contains metadata only; no row values, provider keys, passwords, tokens or production data. Application, migrations, configuration, seeders, routes, import dispatch and Flutter runtime source were inspected. Production was not accessed.

UUIDs are ordinary identifiers, not encryption. Operational profile/contact/academic/financial values remain readable to an authorized database administrator. UUID keys and foreign keys stay in place to preserve relationships and history. Additional business codes improve readability without rewriting keys.

## Storage policy and exceptions

- users.password uses Laravel's hashed cast and Hash::check. Four-character managed passwords are accepted; no password/hash is returned by APIs.
- otp_challenges.otp_hash is a one-way Laravel hash; lookup_hash is the existing HMAC used for challenge lookup. Authentication-session bearer values are represented by digests, not recoverable tokens.
- push_devices.token_ciphertext protects a real delivery credential; token_hash supports device deduplication. FCM OAuth access credentials are encrypted in cache. The local/testing OTP simulator caches short-lived encrypted deliveries separately; production OTP verification stores hashes and the local simulator is prohibited there.
- payload_hash, fingerprints, SHA-256 file checksums and transaction-lock keys are integrity/deduplication data, not ciphertext hiding school records. Corresponding operational fields remain readable.
- External SMS/gateway keys and service-account private keys remain configuration/private files; never put them in ordinary school_settings records or example credentials. APP_KEY and production .env were not changed.
- JSON, serialization, base64 encodings, integer minor monetary units, UUIDs and the generated active_roll_key are not encryption. Framework cache/job storage is classified by contents; do not infer that every cache entry is a school profile or that encoded jobs are encrypted.

## Internal primary keys and business identifiers

| Table | Primary key | Kind | Human identifier / reason for internal key |
| --- | --- | --- | --- |
| academic_sessions | id | UUID | name/date range |
| accounts | id | UUID | code/name |
| admission_requests | id | UUID | admission_number (ADM); student registration_number |
| announcements | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| announcement_reads | announcement_id, user_id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| assessments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| assessment_results | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| assessment_revisions | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| attendance_records | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| attendance_registers | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| attendance_revisions | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| audit_logs | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| authentication_sessions | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| behavior_observations | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| biometric_mappings | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| biometric_punches | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| cache | key | numeric/semantic | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| cache_locks | key | numeric/semantic | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| classes | id | UUID | name/sort_order |
| class_subject_assignments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| coordination_records | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| coordinator_assignments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| diary_attachments | diary_entry_id, private_file_id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| diary_entries | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| diary_revisions | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| diary_students | diary_entry_id, student_id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| discount_policies | id | UUID | code/name |
| document_sequences | key | numeric/semantic | key |
| enrollments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| exam_schedules | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| expenses | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| expense_categories | id | UUID | code/name |
| failed_jobs | id | numeric/semantic | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| fee_categories | id | UUID | code/name |
| fee_invoices | id | UUID | voucher_number |
| fee_schedules | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| grading_rules | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| import_batches | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| invoice_adjustments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| invoice_lines | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| jobs | id | numeric/semantic | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| job_batches | id | numeric/semantic | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| journal_entries | id | UUID | number |
| journal_lines | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| migrations | id | numeric/semantic | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| opening_balance_coverage | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| otp_challenges | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| parent_guardians | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| parent_student_links | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| payments | id | UUID | receipt_number once verified; request UUID remains technical while pending |
| payment_allocations | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| payment_methods | id | UUID | code/name |
| payment_refunds | id | UUID | number |
| payroll_adjustments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| payroll_items | id | UUID | period + joined user account_code; API/report slip_number |
| payroll_payments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| payroll_payment_allocations | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| payroll_periods | id | UUID | unique period; API payroll_number = INF-SG-PR-YYYY-MM |
| permissions | id | UUID | code/name |
| permission_role | permission_id, role_id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| private_files | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| push_deliveries | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| push_devices | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| refund_allocations | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| registration_sequences | year | numeric/semantic | year |
| roles | id | UUID | code/name |
| school_days | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| school_notifications | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| school_settings | key | numeric/semantic | key |
| sections | id | UUID | session/class/name |
| staff_compensation | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| staff_profiles | id | UUID | employee_number, joined users.account_code and name |
| students | id | UUID | registration_number |
| student_discounts | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| student_fee_assignments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| student_transitions | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| subjects | id | UUID | code/name |
| support_messages | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| support_message_attachments | support_message_id, private_file_id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| support_tickets | id | UUID | number |
| support_ticket_events | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| support_ticket_forwards | support_ticket_id, user_id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| teacher_assignments | id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| transaction_locks | key | numeric/semantic | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |
| users | id | UUID | account_code (ACC); name/email |
| user_role_assignments | user_id, role_id | UUID | Use linked business records plus date/status; retain internal key for history, joins and API ownership checks |

All UUID primary keys above remain unchanged. Foreign-key destinations are listed for every referencing column below. UUID request/client/reference identifiers support idempotency or navigation; they are not normal user labels. Account codes are role-independent so role changes cannot rename historical references. Payroll/slip numbers are derived from stable existing keys, without updates to immutable ledger history.

## Complete column inventory

| Table | Column | Purpose | Storage type | Readable/Hashed/Encrypted | Reason |
| --- | --- | --- | --- | --- | --- |
| academic_sessions | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| academic_sessions | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| academic_sessions | starts_on | starts on | date | Readable | Ordinary operational or framework value; no application encryption |
| academic_sessions | ends_on | ends on | date | Readable | Ordinary operational or framework value; no application encryption |
| academic_sessions | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| academic_sessions | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| accounts | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| accounts | code | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| accounts | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| accounts | type | type | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| accounts | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| accounts | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| accounts | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| admission_requests | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| admission_requests | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| admission_requests | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| admission_requests | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| admission_requests | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| admission_requests | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| admission_requests | admission_number | Human business/document identifier | varchar(32) | Readable | Human-readable identity; UUID relationship key remains separate |
| announcements | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| announcements | title | title | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| announcements | body | body | text | Readable | Ordinary operational or framework value; no application encryption |
| announcements | kind | kind | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| announcements | audience | audience | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| announcements | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| announcements | target_user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| announcements | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| announcements | publish_at | publish at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| announcements | expires_at | expires at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| announcements | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| announcements | published_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| announcements | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| announcements | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| announcements | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| announcements | class_id | Relationship to classes.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| announcements | target_role_code | target role code | varchar(40) | Readable | Ordinary operational or framework value; no application encryption |
| announcement_reads | announcement_id | Relationship to announcements.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| announcement_reads | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| announcement_reads | read_at | read at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| assessments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| assessments | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessments | subject_id | Relationship to subjects.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessments | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessments | teacher_user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessments | type | type | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| assessments | title | title | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| assessments | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| assessments | maximum_marks | maximum marks | decimal(8,2) | Readable | Ordinary operational or framework value; no application encryption |
| assessments | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| assessments | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| assessments | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessments | published_at | published at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| assessments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| assessments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| assessment_results | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| assessment_results | assessment_id | Relationship to assessments.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessment_results | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessment_results | maximum_marks | maximum marks | decimal(8,2) | Readable | Ordinary operational or framework value; no application encryption |
| assessment_results | obtained_marks | obtained marks | decimal(8,2) | Readable | Ordinary operational or framework value; no application encryption |
| assessment_results | percentage | percentage | decimal(5,2) | Readable | Ordinary operational or framework value; no application encryption |
| assessment_results | grade | grade | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| assessment_results | remarks | remarks | text | Readable | Ordinary operational or framework value; no application encryption |
| assessment_results | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| assessment_results | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| assessment_revisions | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| assessment_revisions | assessment_id | Relationship to assessments.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessment_revisions | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| assessment_revisions | snapshot | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| assessment_revisions | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| assessment_revisions | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| assessment_revisions | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| attendance_records | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| attendance_records | attendance_register_id | Relationship to attendance_registers.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| attendance_records | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| attendance_records | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| attendance_records | status | status | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| attendance_records | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| attendance_records | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| attendance_registers | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| attendance_registers | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| attendance_registers | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| attendance_registers | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| attendance_registers | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| attendance_registers | last_request_id | last request id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| attendance_registers | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| attendance_registers | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| attendance_registers | updated_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| attendance_registers | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| attendance_registers | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| attendance_revisions | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| attendance_revisions | attendance_register_id | Relationship to attendance_registers.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| attendance_revisions | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| attendance_revisions | snapshot | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| attendance_revisions | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| attendance_revisions | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| attendance_revisions | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| audit_logs | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| audit_logs | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| audit_logs | event | event | varchar(64) | Readable | Ordinary operational or framework value; no application encryption |
| audit_logs | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| audit_logs | subject_type | subject type | varchar(64) | Readable | Ordinary operational or framework value; no application encryption |
| audit_logs | subject_id | subject id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| audit_logs | context | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| authentication_sessions | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| authentication_sessions | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| authentication_sessions | token_hash | Bearer token verification | char(64) | Hashed | SHA-256 digest; never store/return bearer plaintext in DB |
| authentication_sessions | method | method | varchar(32) | Readable | Ordinary operational or framework value; no application encryption |
| authentication_sessions | expires_at | expires at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| authentication_sessions | revoked_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| authentication_sessions | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| authentication_sessions | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| behavior_observations | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| behavior_observations | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| behavior_observations | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| behavior_observations | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| behavior_observations | area | area | varchar(40) | Readable | Ordinary operational or framework value; no application encryption |
| behavior_observations | category | category | varchar(40) | Readable | Ordinary operational or framework value; no application encryption |
| behavior_observations | observation | observation | text | Readable | Ordinary operational or framework value; no application encryption |
| behavior_observations | parent_visible | parent visible | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| behavior_observations | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| behavior_observations | published_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| behavior_observations | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| behavior_observations | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| biometric_mappings | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| biometric_mappings | device_code | device code | varchar(80) | Readable | Ordinary operational or framework value; no application encryption |
| biometric_mappings | external_id | external id | varchar(80) | Readable | Readable internal identifier/reference; API relationships retained |
| biometric_mappings | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| biometric_mappings | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| biometric_mappings | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| biometric_mappings | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| biometric_punches | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| biometric_punches | import_batch_id | Relationship to import_batches.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| biometric_punches | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| biometric_punches | device_code | device code | varchar(80) | Readable | Ordinary operational or framework value; no application encryption |
| biometric_punches | punched_at | punched at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| biometric_punches | direction | direction | varchar(8) | Readable | Ordinary operational or framework value; no application encryption |
| biometric_punches | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| biometric_punches | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| cache | key | Record primary key | varchar(255) | Readable | Semantic/numeric framework or sequence identity |
| cache | value | Framework cache entries | mediumtext | Readable / Encrypted by entry | Ordinary entries serialized; local OTP and FCM credential cache encrypted explicitly |
| cache | expiration | expiration | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| cache_locks | key | Record primary key | varchar(255) | Readable | Semantic/numeric framework or sequence identity |
| cache_locks | owner | owner | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| cache_locks | expiration | expiration | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| classes | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| classes | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| classes | sort_order | sort order | smallint(5) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| classes | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| classes | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| class_subject_assignments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| class_subject_assignments | class_id | Relationship to classes.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| class_subject_assignments | subject_id | Relationship to subjects.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| class_subject_assignments | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| class_subject_assignments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| class_subject_assignments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| coordination_records | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| coordination_records | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| coordination_records | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| coordination_records | assigned_to | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| coordination_records | kind | kind | varchar(32) | Readable | Ordinary operational or framework value; no application encryption |
| coordination_records | title | title | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| coordination_records | notes | notes | text | Readable | Ordinary operational or framework value; no application encryption |
| coordination_records | progress_percent | progress percent | smallint(5) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| coordination_records | follow_up_on | follow up on | date | Readable | Ordinary operational or framework value; no application encryption |
| coordination_records | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| coordination_records | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| coordination_records | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| coordination_records | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| coordinator_assignments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| coordinator_assignments | staff_profile_id | Relationship to staff_profiles.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| coordinator_assignments | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| coordinator_assignments | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| coordinator_assignments | starts_on | starts on | date | Readable | Ordinary operational or framework value; no application encryption |
| coordinator_assignments | ends_on | ends on | date | Readable | Ordinary operational or framework value; no application encryption |
| coordinator_assignments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| coordinator_assignments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| coordinator_assignments | active_coordinator_key | active coordinator key | char(36) | Readable | Ordinary operational or framework value; no application encryption |
| diary_attachments | diary_entry_id | Relationship to diary_entries.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_attachments | private_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_entries | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| diary_entries | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_entries | subject_id | Relationship to subjects.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_entries | teacher_user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_entries | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| diary_entries | topic | topic | varchar(300) | Readable | Ordinary operational or framework value; no application encryption |
| diary_entries | homework | homework | text | Readable | Ordinary operational or framework value; no application encryption |
| diary_entries | whole_class | whole class | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| diary_entries | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| diary_entries | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| diary_entries | client_id | client id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| diary_entries | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| diary_entries | reviewed_at | reviewed at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| diary_entries | reviewed_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_entries | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| diary_entries | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| diary_revisions | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| diary_revisions | diary_entry_id | Relationship to diary_entries.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_revisions | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| diary_revisions | snapshot | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| diary_revisions | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_revisions | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| diary_revisions | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| diary_students | diary_entry_id | Relationship to diary_entries.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| diary_students | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| discount_policies | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| discount_policies | code | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| discount_policies | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| discount_policies | maximum_basis_points | maximum basis points | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| discount_policies | applies_to | applies to | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| discount_policies | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| discount_policies | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| discount_policies | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| document_sequences | key | Record primary key | varchar(255) | Readable | Semantic/numeric framework or sequence identity |
| document_sequences | next_number | next number | bigint(20) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| enrollments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| enrollments | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| enrollments | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| enrollments | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| enrollments | roll_number | roll number | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| enrollments | starts_on | starts on | date | Readable | Ordinary operational or framework value; no application encryption |
| enrollments | ends_on | ends on | date | Readable | Ordinary operational or framework value; no application encryption |
| enrollments | status | status | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| enrollments | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| enrollments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| enrollments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| enrollments | active_student_key | active student key | char(36) | Readable | Ordinary operational or framework value; no application encryption |
| enrollments | active_roll_key | active roll key | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| exam_schedules | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| exam_schedules | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| exam_schedules | subject_id | Relationship to subjects.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| exam_schedules | title | title | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| exam_schedules | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| exam_schedules | starts_at | starts at | time | Readable | Ordinary operational or framework value; no application encryption |
| exam_schedules | ends_at | ends at | time | Readable | Ordinary operational or framework value; no application encryption |
| exam_schedules | room | room | varchar(100) | Readable | Ordinary operational or framework value; no application encryption |
| exam_schedules | status | status | varchar(16) | Readable | Ordinary operational or framework value; no application encryption |
| exam_schedules | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| exam_schedules | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| exam_schedules | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| exam_schedules | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| expenses | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| expenses | expense_category_id | Relationship to expense_categories.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| expenses | description | description | varchar(500) | Readable | Ordinary operational or framework value; no application encryption |
| expenses | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| expenses | expense_on | expense on | date | Readable | Ordinary operational or framework value; no application encryption |
| expenses | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| expenses | proof_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| expenses | requested_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| expenses | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| expenses | approved_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| expenses | paid_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| expenses | payment_method_id | Relationship to payment_methods.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| expenses | paid_on | paid on | date | Readable | Ordinary operational or framework value; no application encryption |
| expenses | reference | reference | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| expenses | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| expenses | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| expense_categories | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| expense_categories | code | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| expense_categories | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| expense_categories | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| expense_categories | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| failed_jobs | id | Record primary key | bigint(20) unsigned | Readable | Semantic/numeric framework or sequence identity |
| failed_jobs | uuid | uuid | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| failed_jobs | connection | connection | text | Readable | Ordinary operational or framework value; no application encryption |
| failed_jobs | queue | queue | text | Readable | Ordinary operational or framework value; no application encryption |
| failed_jobs | payload | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| failed_jobs | exception | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| failed_jobs | failed_at | failed at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| fee_categories | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| fee_categories | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| fee_categories | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| fee_categories | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| fee_invoices | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| fee_invoices | voucher_number | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| fee_invoices | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| fee_invoices | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| fee_invoices | period | period | varchar(7) | Readable | Ordinary operational or framework value; no application encryption |
| fee_invoices | kind | kind | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| fee_invoices | issued_on | issued on | date | Readable | Ordinary operational or framework value; no application encryption |
| fee_invoices | due_on | due on | date | Readable | Ordinary operational or framework value; no application encryption |
| fee_invoices | gross_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| fee_invoices | discount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| fee_invoices | total_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| fee_invoices | request_id | request id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| fee_invoices | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| fee_invoices | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| fee_invoices | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| fee_invoices | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| fee_invoices | admission_student_key | admission student key | char(36) | Readable | Ordinary operational or framework value; no application encryption |
| fee_schedules | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| fee_schedules | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| fee_schedules | class_id | Relationship to classes.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| fee_schedules | kind | kind | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| fee_schedules | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| fee_schedules | effective_on | effective on | date | Readable | Ordinary operational or framework value; no application encryption |
| fee_schedules | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| fee_schedules | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| fee_schedules | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| grading_rules | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| grading_rules | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| grading_rules | minimum_percent | minimum percent | decimal(5,2) | Readable | Ordinary operational or framework value; no application encryption |
| grading_rules | grade | grade | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| grading_rules | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| grading_rules | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| import_batches | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| import_batches | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| import_batches | kind | kind | varchar(32) | Readable | Ordinary operational or framework value; no application encryption |
| import_batches | fingerprint | Import duplicate detection | varchar(64) | Hashed | Integrity/deduplication fingerprint; source_rows remains readable |
| import_batches | mapping | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| import_batches | status | status | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| import_batches | report | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| import_batches | expires_at | expires at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| import_batches | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| import_batches | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| import_batches | source_rows | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| invoice_adjustments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| invoice_adjustments | fee_invoice_id | Relationship to fee_invoices.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| invoice_adjustments | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| invoice_adjustments | reason | reason | varchar(500) | Readable | Ordinary operational or framework value; no application encryption |
| invoice_adjustments | request_id | request id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| invoice_adjustments | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| invoice_adjustments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| invoice_adjustments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| invoice_lines | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| invoice_lines | fee_invoice_id | Relationship to fee_invoices.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| invoice_lines | description | description | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| invoice_lines | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| invoice_lines | kind | kind | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| invoice_lines | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| invoice_lines | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| jobs | id | Record primary key | bigint(20) unsigned | Readable | Semantic/numeric framework or sequence identity |
| jobs | queue | queue | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| jobs | payload | Structured history/staging/framework data | longtext | Readable | JSON or serialization is not encryption; restrict access and exclude secrets from logs/payloads |
| jobs | attempts | attempts | tinyint(3) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| jobs | reserved_at | reserved at | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| jobs | available_at | available at | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| jobs | created_at | Lifecycle timestamp | int(10) unsigned | Readable | Readable audit/state date |
| job_batches | id | Record primary key | varchar(255) | Readable | Semantic/numeric framework or sequence identity |
| job_batches | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| job_batches | total_jobs | total jobs | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| job_batches | pending_jobs | pending jobs | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| job_batches | failed_jobs | failed jobs | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| job_batches | failed_job_ids | failed job ids | longtext | Readable | Ordinary operational or framework value; no application encryption |
| job_batches | options | options | mediumtext | Readable | Ordinary operational or framework value; no application encryption |
| job_batches | cancelled_at | cancelled at | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| job_batches | created_at | Lifecycle timestamp | int(11) | Readable | Readable audit/state date |
| job_batches | finished_at | finished at | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| journal_entries | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| journal_entries | number | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| journal_entries | posting_key | posting key | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| journal_entries | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| journal_entries | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| journal_entries | description | description | varchar(300) | Readable | Ordinary operational or framework value; no application encryption |
| journal_entries | reference_type | reference type | varchar(40) | Readable | Ordinary operational or framework value; no application encryption |
| journal_entries | reference_id | reference id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| journal_entries | reversal_of | Relationship to journal_entries.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| journal_entries | posted_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| journal_entries | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| journal_entries | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| journal_lines | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| journal_lines | journal_entry_id | Relationship to journal_entries.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| journal_lines | account_id | Relationship to accounts.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| journal_lines | debit_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| journal_lines | credit_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| journal_lines | currency | currency | char(3) | Readable | Ordinary operational or framework value; no application encryption |
| journal_lines | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| journal_lines | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| migrations | id | Record primary key | int(10) unsigned | Readable | Semantic/numeric framework or sequence identity |
| migrations | migration | migration | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| migrations | batch | batch | int(11) | Readable | Ordinary operational or framework value; no application encryption |
| opening_balance_coverage | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| opening_balance_coverage | fee_invoice_id | Relationship to fee_invoices.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| opening_balance_coverage | from_period | from period | char(7) | Readable | Ordinary operational or framework value; no application encryption |
| opening_balance_coverage | through_period | through period | char(7) | Readable | Ordinary operational or framework value; no application encryption |
| opening_balance_coverage | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| opening_balance_coverage | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| opening_balance_coverage | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| otp_challenges | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| otp_challenges | parent_guardian_id | Relationship to parent_guardians.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| otp_challenges | lookup_hash | Private challenge lookup | char(64) | Hashed | Existing HMAC; retains challenge privacy |
| otp_challenges | otp_hash | OTP verification | varchar(255) | Hashed | One-way hash with expiry/attempt controls; never expose OTP |
| otp_challenges | expires_at | expires at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| otp_challenges | consumed_at | consumed at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| otp_challenges | attempts | attempts | smallint(5) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| otp_challenges | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| otp_challenges | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| parent_guardians | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| parent_guardians | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| parent_guardians | full_name | full name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| parent_guardians | mobile_verified_at | mobile verified at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| parent_guardians | verified_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| parent_guardians | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| parent_guardians | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| parent_guardians | photo_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| parent_guardians | cnic | cnic | char(13) | Readable | Ordinary operational or framework value; no application encryption |
| parent_guardians | mobile | mobile | varchar(13) | Readable | Ordinary operational or framework value; no application encryption |
| parent_student_links | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| parent_student_links | parent_guardian_id | Relationship to parent_guardians.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| parent_student_links | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| parent_student_links | relationship | relationship | varchar(32) | Readable | Ordinary operational or framework value; no application encryption |
| parent_student_links | is_primary | is primary | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| parent_student_links | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| parent_student_links | verified_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| parent_student_links | verified_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| parent_student_links | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| parent_student_links | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| parent_student_links | primary_guardian_key | primary guardian key | char(36) | Readable | Ordinary operational or framework value; no application encryption |
| payments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payments | receipt_number | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| payments | payment_method_id | Relationship to payment_methods.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payments | paid_on | paid on | date | Readable | Ordinary operational or framework value; no application encryption |
| payments | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payments | reference | reference | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| payments | proof_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payments | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| payments | request_id | request id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| payments | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| payments | recorded_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payments | verified_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payments | verified_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payments | notes | notes | varchar(500) | Readable | Ordinary operational or framework value; no application encryption |
| payment_allocations | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payment_allocations | payment_id | Relationship to payments.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payment_allocations | fee_invoice_id | Relationship to fee_invoices.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payment_allocations | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payment_allocations | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payment_allocations | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payment_methods | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payment_methods | code | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| payment_methods | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| payment_methods | account_id | Relationship to accounts.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payment_methods | requires_verification | requires verification | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| payment_methods | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| payment_methods | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payment_methods | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payment_refunds | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payment_refunds | payment_id | Relationship to payments.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payment_refunds | number | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| payment_refunds | refunded_on | refunded on | date | Readable | Ordinary operational or framework value; no application encryption |
| payment_refunds | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payment_refunds | reason | reason | varchar(500) | Readable | Ordinary operational or framework value; no application encryption |
| payment_refunds | request_id | request id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| payment_refunds | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| payment_refunds | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payment_refunds | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payment_refunds | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_adjustments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payroll_adjustments | staff_profile_id | Relationship to staff_profiles.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_adjustments | period | period | varchar(7) | Readable | Ordinary operational or framework value; no application encryption |
| payroll_adjustments | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payroll_adjustments | reason | reason | varchar(500) | Readable | Ordinary operational or framework value; no application encryption |
| payroll_adjustments | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_adjustments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_adjustments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_items | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payroll_items | payroll_period_id | Relationship to payroll_periods.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_items | staff_profile_id | Relationship to staff_profiles.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_items | base_salary_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payroll_items | adjustment_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payroll_items | net_salary_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payroll_items | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_items | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_payments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payroll_payments | payroll_period_id | Relationship to payroll_periods.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_payments | payment_method_id | Relationship to payment_methods.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_payments | recorded_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_payments | request_id | request id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| payroll_payments | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| payroll_payments | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payroll_payments | paid_on | paid on | date | Readable | Ordinary operational or framework value; no application encryption |
| payroll_payments | reference | reference | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| payroll_payments | notes | notes | varchar(500) | Readable | Ordinary operational or framework value; no application encryption |
| payroll_payments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_payments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_payment_allocations | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payroll_payment_allocations | payroll_payment_id | Relationship to payroll_payments.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_payment_allocations | payroll_item_id | Relationship to payroll_items.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_payment_allocations | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payroll_payment_allocations | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_payment_allocations | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_periods | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| payroll_periods | period | period | varchar(7) | Readable | Ordinary operational or framework value; no application encryption |
| payroll_periods | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| payroll_periods | total_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| payroll_periods | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_periods | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_periods | approved_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_periods | paid_on | paid on | date | Readable | Ordinary operational or framework value; no application encryption |
| payroll_periods | payment_method_id | Relationship to payment_methods.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| payroll_periods | reference | reference | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| payroll_periods | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| payroll_periods | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| permissions | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| permissions | code | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| permissions | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| permissions | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| permissions | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| permission_role | permission_id | Relationship to permissions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| permission_role | role_id | Relationship to roles.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| private_files | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| private_files | uploaded_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| private_files | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| private_files | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| private_files | path | path | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| private_files | mime | mime | varchar(100) | Readable | Ordinary operational or framework value; no application encryption |
| private_files | purpose | purpose | varchar(32) | Readable | Ordinary operational or framework value; no application encryption |
| private_files | size | size | bigint(20) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| private_files | sha256 | Attachment content verification | char(64) | Hashed | Integrity checksum, not an encrypted file/name |
| private_files | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| private_files | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| private_files | parent_guardian_id | Relationship to parent_guardians.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| private_files | owner_user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| push_deliveries | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| push_deliveries | school_notification_id | Relationship to school_notifications.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| push_deliveries | push_device_id | Relationship to push_devices.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| push_deliveries | status | status | varchar(16) | Readable | Ordinary operational or framework value; no application encryption |
| push_deliveries | attempts | attempts | smallint(5) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| push_deliveries | next_attempt_at | next attempt at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| push_deliveries | lease_until | lease until | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| push_deliveries | failure_code | failure code | varchar(40) | Readable | Ordinary operational or framework value; no application encryption |
| push_deliveries | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| push_deliveries | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| push_devices | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| push_devices | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| push_devices | authentication_session_id | Relationship to authentication_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| push_devices | token_hash | Device delivery credential deduplication | char(64) | Hashed | SHA-256 digest; not a business identifier |
| push_devices | token_ciphertext | Device push delivery credential | text | Encrypted | Recoverable only for authorized provider dispatch; actual credential |
| push_devices | platform | platform | varchar(16) | Readable | Ordinary operational or framework value; no application encryption |
| push_devices | revoked_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| push_devices | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| push_devices | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| refund_allocations | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| refund_allocations | payment_refund_id | Relationship to payment_refunds.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| refund_allocations | payment_allocation_id | Relationship to payment_allocations.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| refund_allocations | amount_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| refund_allocations | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| refund_allocations | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| registration_sequences | year | Record primary key | smallint(5) unsigned | Readable | Semantic/numeric framework or sequence identity |
| registration_sequences | next_number | next number | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| roles | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| roles | code | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| roles | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| roles | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| roles | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| school_days | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| school_days | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| school_days | date | date | date | Readable | Ordinary operational or framework value; no application encryption |
| school_days | is_school_day | is school day | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| school_days | label | label | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| school_days | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| school_days | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| school_notifications | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| school_notifications | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| school_notifications | event_key | event key | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| school_notifications | kind | kind | varchar(40) | Readable | Ordinary operational or framework value; no application encryption |
| school_notifications | target_type | target type | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| school_notifications | target_id | target id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| school_notifications | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| school_notifications | read_at | read at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| school_notifications | push_status | push status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| school_notifications | push_attempts | push attempts | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| school_notifications | push_after | push after | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| school_notifications | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| school_notifications | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| school_settings | key | Record primary key | varchar(255) | Readable | Semantic/numeric framework or sequence identity |
| school_settings | value | value | longtext | Readable | Readable school business configuration; provider secrets belong in private configuration |
| school_settings | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| school_settings | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| sections | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| sections | class_id | Relationship to classes.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| sections | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| sections | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| sections | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| sections | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| staff_compensation | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| staff_compensation | staff_profile_id | Relationship to staff_profiles.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| staff_compensation | base_salary_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| staff_compensation | effective_on | effective on | date | Readable | Ordinary operational or framework value; no application encryption |
| staff_compensation | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| staff_compensation | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| staff_compensation | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| staff_profiles | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| staff_profiles | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| staff_profiles | employee_number | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| staff_profiles | joined_on | joined on | date | Readable | Ordinary operational or framework value; no application encryption |
| staff_profiles | designation | designation | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| staff_profiles | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| staff_profiles | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| staff_profiles | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| students | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| students | registration_number | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| students | admitted_on | admitted on | date | Readable | Ordinary operational or framework value; no application encryption |
| students | full_name | full name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| students | gender | gender | varchar(16) | Readable | Ordinary operational or framework value; no application encryption |
| students | date_of_birth | date of birth | date | Readable | Ordinary operational or framework value; no application encryption |
| students | father_name | father name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| students | address | address | text | Readable | Ordinary operational or framework value; no application encryption |
| students | previous_school | previous school | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| students | emergency_contact | emergency contact | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| students | status | status | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| students | fee_category_id | Relationship to fee_categories.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| students | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| students | updated_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| students | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| students | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| students | photo_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_discounts | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| student_discounts | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_discounts | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_discounts | discount_policy_id | Relationship to discount_policies.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_discounts | basis_points | basis points | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| student_discounts | starts_on | starts on | date | Readable | Ordinary operational or framework value; no application encryption |
| student_discounts | ends_on | ends on | date | Readable | Ordinary operational or framework value; no application encryption |
| student_discounts | eligibility_notes | eligibility notes | text | Readable | Ordinary operational or framework value; no application encryption |
| student_discounts | evidence_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_discounts | status | status | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| student_discounts | requested_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_discounts | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_discounts | approved_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| student_discounts | revoked_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| student_discounts | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| student_discounts | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| student_fee_assignments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| student_fee_assignments | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_fee_assignments | academic_session_id | Relationship to academic_sessions.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_fee_assignments | fee_category_id | Relationship to fee_categories.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_fee_assignments | tuition_override_minor | Money amount in minor units (PKR paisa) | bigint(20) | Readable | Readable integer amount; divide by 100 for rupees |
| student_fee_assignments | effective_on | effective on | date | Readable | Ordinary operational or framework value; no application encryption |
| student_fee_assignments | approved_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_fee_assignments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| student_fee_assignments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| student_transitions | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| student_transitions | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_transitions | type | type | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| student_transitions | from_enrollment_id | Relationship to enrollments.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_transitions | to_enrollment_id | Relationship to enrollments.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_transitions | effective_on | effective on | date | Readable | Ordinary operational or framework value; no application encryption |
| student_transitions | reason | reason | text | Readable | Ordinary operational or framework value; no application encryption |
| student_transitions | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| student_transitions | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| subjects | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| subjects | code | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| subjects | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| subjects | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| subjects | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_messages | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| support_messages | support_ticket_id | Relationship to support_tickets.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_messages | sender_user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_messages | body | body | text | Readable | Ordinary operational or framework value; no application encryption |
| support_messages | is_internal | is internal | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| support_messages | client_id | client id | char(36) | Readable | Readable internal identifier/reference; API relationships retained |
| support_messages | payload_hash | Request/history integrity and idempotency | char(64) | Hashed | SHA-256 fingerprint; ordinary source fields remain readable |
| support_messages | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_messages | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_message_attachments | support_message_id | Relationship to support_messages.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_message_attachments | private_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_tickets | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| support_tickets | student_id | Relationship to students.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_tickets | guardian_user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_tickets | number | Human business/document identifier | varchar(255) | Readable | Human-readable identity; UUID relationship key remains separate |
| support_tickets | category | category | varchar(40) | Readable | Ordinary operational or framework value; no application encryption |
| support_tickets | subject | subject | varchar(200) | Readable | Ordinary operational or framework value; no application encryption |
| support_tickets | status | status | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| support_tickets | priority | priority | varchar(20) | Readable | Ordinary operational or framework value; no application encryption |
| support_tickets | assigned_to | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_tickets | version | version | int(10) unsigned | Readable | Ordinary operational or framework value; no application encryption |
| support_tickets | resolved_at | resolved at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| support_tickets | closed_at | closed at | timestamp | Readable | Ordinary operational or framework value; no application encryption |
| support_tickets | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_tickets | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_ticket_events | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| support_ticket_events | support_ticket_id | Relationship to support_tickets.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_ticket_events | from_status | from status | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| support_ticket_events | to_status | to status | varchar(24) | Readable | Ordinary operational or framework value; no application encryption |
| support_ticket_events | created_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_ticket_events | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_ticket_events | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_ticket_forwards | support_ticket_id | Relationship to support_tickets.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_ticket_forwards | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_ticket_forwards | forwarded_by | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| support_ticket_forwards | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| support_ticket_forwards | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| teacher_assignments | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| teacher_assignments | staff_profile_id | Relationship to staff_profiles.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| teacher_assignments | section_id | Relationship to sections.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| teacher_assignments | subject_id | Relationship to subjects.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| teacher_assignments | is_class_teacher | is class teacher | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| teacher_assignments | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| teacher_assignments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| teacher_assignments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| teacher_assignments | starts_on | starts on | date | Readable | Ordinary operational or framework value; no application encryption |
| teacher_assignments | ends_on | ends on | date | Readable | Ordinary operational or framework value; no application encryption |
| teacher_assignments | class_teacher_key | class teacher key | char(36) | Readable | Ordinary operational or framework value; no application encryption |
| teacher_assignments | active_subject_key | active subject key | char(36) | Readable | Ordinary operational or framework value; no application encryption |
| transaction_locks | key | Transaction coordination | char(64) | Hashed | Digest of lock namespace/key; no hidden profile values |
| users | id | Record primary key | char(36) | Readable | Internal UUID, not ciphertext |
| users | name | name | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| users | email | email | varchar(255) | Readable | Ordinary operational or framework value; no application encryption |
| users | password | Account login verification | varchar(255) | Hashed | One-way Laravel password hash; never readable or reversibly encrypted |
| users | is_active | is active | tinyint(1) | Readable | Ordinary operational or framework value; no application encryption |
| users | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| users | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| users | mobile | mobile | varchar(30) | Readable | Ordinary operational or framework value; no application encryption |
| users | address | address | text | Readable | Ordinary operational or framework value; no application encryption |
| users | photo_file_id | Relationship to private_files.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| users | account_code | Human business/document identifier | varchar(32) | Readable | Human-readable identity; UUID relationship key remains separate |
| user_role_assignments | user_id | Relationship to users.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| user_role_assignments | role_id | Relationship to roles.id | char(36) | Readable | Internal foreign key; preserves referential integrity, not ciphertext |
| user_role_assignments | created_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |
| user_role_assignments | updated_at | Lifecycle timestamp | timestamp | Readable | Readable audit/state date |

## Transitional legacy columns

A fresh migration chain temporarily retains the following five legacy fields for the existing safe conversion command. These are absent from the finalized 713-column export. With them present, the schema has 718 columns. Their presence is not a new policy to encrypt ordinary data.

| Table | Column | Purpose | Storage type | Readable/Hashed/Encrypted | Reason |
| --- | --- | --- | --- | --- | --- |
| parent_guardians | cnic_ciphertext | Legacy CNIC migration source | text nullable | Encrypted (legacy) | Convert to readable cnic with existing validated converter; production not converted here |
| parent_guardians | mobile_ciphertext | Legacy mobile migration source | text nullable | Encrypted (legacy) | Convert to readable mobile; Spext receives the readable number |
| parent_guardians | cnic_lookup | Legacy guardian lookup | char(64) nullable | Hashed (legacy HMAC) | Replaced by normalized unique readable cnic, cleanup only after validation |
| parent_guardians | cnic_last4 | Legacy display helper | char(4) nullable | Readable | Obsolete helper after readable identity migration |
| import_batches | encrypted_rows | Legacy import staging source | text nullable | Encrypted (legacy) | Convert and verify readable source_rows before removing |

The current continuation only adds account/admission business-code columns. It does not run conversion against production or change APP_KEY. The existing guarded conversion test verifies readback, cleanup DDL and repeat execution without rewriting secrets.

## Hardcoded UUID audit

All tracked repository text was scanned for UUID literals, excluding real environment/secret files and binary/generated dependencies. No runtime production UUID dependency was found in api/app, api/config, api/routes, api/database or mobile/lib. Seven documentation CSV templates contain illustrative fixture UUIDs on their example row; none is used as an application default. Tests generate fresh relationship UUIDs. Fresh installation and the complete test suite succeed after recreating those relationships.

## Reproduction

The exporter tests/Support/export-readable-schema.php requires the fixed disposable-loopback DB guard before querying schema metadata. Run it only with the documented isolated test configuration, after guarded conversion finalization if you want the finalized schema. It never exports record values. See monthly-school-audit-report.md for validation, deployment order, identifiers and limitations.

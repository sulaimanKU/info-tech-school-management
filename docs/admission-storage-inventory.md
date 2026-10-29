# Complete database storage inventory

Pre-finalization inventory: 86 guarded disposable MariaDB tables and 716 columns; names/types only, no record values. The companion admission-storage-schema.json records the finalized 711-column schema after five obsolete guardian/import columns were safely removed in the isolated conversion test. Every column in every application/framework table is listed. Classification is based on the schema and application source audit. Legacy guardian/import columns intentionally remain until the existing validated conversion command finalizes them.

## academic_sessions

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| name | Readable operational/configuration value (varchar) |
| starts_on | Readable operational/configuration value (date) |
| ends_on | Readable operational/configuration value (date) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## accounts

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| code | Readable operational/configuration value (varchar) |
| name | Readable operational/configuration value (varchar) |
| type | Readable operational/configuration value (varchar) |
| is_active | Readable operational/configuration value (tinyint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## admission_requests

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## announcements

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| title | Readable operational/configuration value (varchar) |
| body | Readable operational/configuration value (text) |
| kind | Readable operational/configuration value (varchar) |
| audience | Readable operational/configuration value (varchar) |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| target_user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| status | Readable operational/configuration value (varchar) |
| publish_at | Readable operational/configuration value (timestamp) |
| expires_at | Readable operational/configuration value (timestamp) |
| created_by | Readable operational/configuration value (char) |
| published_by | Readable operational/configuration value (char) |
| version | Readable operational/configuration value (int) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| class_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| target_role_code | Readable operational/configuration value (varchar) |

## announcement_reads

| Column | Storage/classification |
| --- | --- |
| announcement_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| read_at | Readable operational/configuration value (timestamp) |

## assessments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| subject_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| teacher_user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| type | Readable operational/configuration value (varchar) |
| title | Readable operational/configuration value (varchar) |
| date | Readable operational/configuration value (date) |
| maximum_marks | Readable operational/configuration value (decimal) |
| status | Readable operational/configuration value (varchar) |
| version | Readable operational/configuration value (int) |
| approved_by | Readable operational/configuration value (char) |
| published_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## assessment_results

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| assessment_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| maximum_marks | Readable operational/configuration value (decimal) |
| obtained_marks | Readable operational/configuration value (decimal) |
| percentage | Readable operational/configuration value (decimal) |
| grade | Readable operational/configuration value (varchar) |
| remarks | Readable operational/configuration value (text) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## assessment_revisions

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| assessment_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| version | Readable operational/configuration value (int) |
| snapshot | Readable operational/configuration value (longtext) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## attendance_records

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| attendance_register_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| date | Readable operational/configuration value (date) |
| status | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## attendance_registers

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| date | Readable operational/configuration value (date) |
| status | Readable operational/configuration value (varchar) |
| version | Readable operational/configuration value (int) |
| last_request_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| created_by | Readable operational/configuration value (char) |
| updated_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## attendance_revisions

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| attendance_register_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| version | Readable operational/configuration value (int) |
| snapshot | Readable operational/configuration value (longtext) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## audit_logs

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| event | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| subject_type | Readable operational/configuration value (varchar) |
| subject_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| context | Readable operational/configuration value (longtext) |

## authentication_sessions

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| token_hash | Security digest/HMAC; retained |
| method | Readable operational/configuration value (varchar) |
| expires_at | Readable operational/configuration value (timestamp) |
| revoked_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## behavior_observations

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| created_by | Readable operational/configuration value (char) |
| date | Readable operational/configuration value (date) |
| area | Readable operational/configuration value (varchar) |
| category | Readable operational/configuration value (varchar) |
| observation | Readable operational/configuration value (text) |
| parent_visible | Readable operational/configuration value (tinyint) |
| status | Readable operational/configuration value (varchar) |
| published_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## biometric_mappings

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| device_code | Readable operational/configuration value (varchar) |
| external_id | Normal readable identifier/reference (varchar); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## biometric_punches

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| import_batch_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| device_code | Readable operational/configuration value (varchar) |
| punched_at | Readable operational/configuration value (timestamp) |
| direction | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## cache

| Column | Storage/classification |
| --- | --- |
| key | Readable operational/configuration value (varchar) |
| value | Framework serialized cache: local OTP and FCM access-token entries are intentionally encrypted; ordinary counters/settings are not blanket encrypted |
| expiration | Readable operational/configuration value (int) |

## cache_locks

| Column | Storage/classification |
| --- | --- |
| key | Readable operational/configuration value (varchar) |
| owner | Readable operational/configuration value (varchar) |
| expiration | Readable operational/configuration value (int) |

## classes

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| name | Readable operational/configuration value (varchar) |
| sort_order | Readable operational/configuration value (smallint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## class_subject_assignments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| class_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| subject_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## coordination_records

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| assigned_to | Readable operational/configuration value (char) |
| kind | Readable operational/configuration value (varchar) |
| title | Readable operational/configuration value (varchar) |
| notes | Readable operational/configuration value (text) |
| progress_percent | Readable operational/configuration value (smallint) |
| follow_up_on | Readable operational/configuration value (date) |
| status | Readable operational/configuration value (varchar) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## coordinator_assignments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| staff_profile_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| is_active | Readable operational/configuration value (tinyint) |
| starts_on | Readable operational/configuration value (date) |
| ends_on | Readable operational/configuration value (date) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| active_coordinator_key | Readable operational/configuration value (char) |

## diary_attachments

| Column | Storage/classification |
| --- | --- |
| diary_entry_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| private_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |

## diary_entries

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| subject_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| teacher_user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| date | Readable operational/configuration value (date) |
| topic | Readable operational/configuration value (varchar) |
| homework | Readable operational/configuration value (text) |
| whole_class | Readable operational/configuration value (tinyint) |
| status | Readable operational/configuration value (varchar) |
| version | Readable operational/configuration value (int) |
| client_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| reviewed_at | Readable operational/configuration value (timestamp) |
| reviewed_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## diary_revisions

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| diary_entry_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| version | Readable operational/configuration value (int) |
| snapshot | Readable operational/configuration value (longtext) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## diary_students

| Column | Storage/classification |
| --- | --- |
| diary_entry_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |

## discount_policies

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| code | Readable operational/configuration value (varchar) |
| name | Readable operational/configuration value (varchar) |
| maximum_basis_points | Readable operational/configuration value (int) |
| applies_to | Readable operational/configuration value (varchar) |
| is_active | Readable operational/configuration value (tinyint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## document_sequences

| Column | Storage/classification |
| --- | --- |
| key | Readable operational/configuration value (varchar) |
| next_number | Readable operational/configuration value (bigint) |

## enrollments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| roll_number | Readable operational/configuration value (int) |
| starts_on | Readable operational/configuration value (date) |
| ends_on | Readable operational/configuration value (date) |
| status | Readable operational/configuration value (varchar) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| active_student_key | Readable operational/configuration value (char) |
| active_roll_key | Readable operational/configuration value (char) |

## exam_schedules

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| subject_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| title | Readable operational/configuration value (varchar) |
| date | Readable operational/configuration value (date) |
| starts_at | Readable operational/configuration value (time) |
| ends_at | Readable operational/configuration value (time) |
| room | Readable operational/configuration value (varchar) |
| status | Readable operational/configuration value (varchar) |
| version | Readable operational/configuration value (int) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## expenses

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| expense_category_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| description | Readable operational/configuration value (varchar) |
| amount_minor | Readable operational/configuration value (bigint) |
| expense_on | Readable operational/configuration value (date) |
| status | Readable operational/configuration value (varchar) |
| proof_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| requested_by | Readable operational/configuration value (char) |
| approved_by | Readable operational/configuration value (char) |
| approved_at | Readable operational/configuration value (timestamp) |
| paid_by | Readable operational/configuration value (char) |
| payment_method_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| paid_on | Readable operational/configuration value (date) |
| reference | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## expense_categories

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| code | Readable operational/configuration value (varchar) |
| name | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## failed_jobs

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (bigint); UUIDs are not ciphertext |
| uuid | Readable operational/configuration value (varchar) |
| connection | Readable operational/configuration value (text) |
| queue | Readable operational/configuration value (text) |
| payload | Framework failed-job envelope; serialized/encoded representation is not evidence of profile ciphertext |
| exception | Framework diagnostic text; preserve redaction and access controls |
| failed_at | Readable operational/configuration value (timestamp) |

## fee_categories

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| name | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## fee_invoices

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| voucher_number | Readable operational/configuration value (varchar) |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| period | Readable operational/configuration value (varchar) |
| kind | Readable operational/configuration value (varchar) |
| issued_on | Readable operational/configuration value (date) |
| due_on | Readable operational/configuration value (date) |
| gross_minor | Readable operational/configuration value (bigint) |
| discount_minor | Readable operational/configuration value (bigint) |
| total_minor | Readable operational/configuration value (bigint) |
| request_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| admission_student_key | Readable operational/configuration value (char) |

## fee_schedules

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| class_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| kind | Readable operational/configuration value (varchar) |
| amount_minor | Readable operational/configuration value (bigint) |
| effective_on | Readable operational/configuration value (date) |
| approved_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## grading_rules

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| minimum_percent | Readable operational/configuration value (decimal) |
| grade | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## import_batches

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| created_by | Readable operational/configuration value (char) |
| kind | Readable operational/configuration value (varchar) |
| fingerprint | Integrity/deduplication/locking digest; source data remains readable |
| encrypted_rows | Legacy ordinary-data ciphertext; validated conversion required before removal |
| mapping | Readable operational/configuration value (longtext) |
| status | Readable operational/configuration value (varchar) |
| report | Readable operational/configuration value (longtext) |
| expires_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| source_rows | Readable operational/configuration value (longtext) |

## invoice_adjustments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| fee_invoice_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| amount_minor | Readable operational/configuration value (bigint) |
| reason | Readable operational/configuration value (varchar) |
| request_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| approved_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## invoice_lines

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| fee_invoice_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| description | Readable operational/configuration value (varchar) |
| amount_minor | Readable operational/configuration value (bigint) |
| kind | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## jobs

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (bigint); UUIDs are not ciphertext |
| queue | Readable operational/configuration value (varchar) |
| payload | Framework job envelope (JSON/PHP serialization); no custom encrypted application jobs found |
| attempts | Readable operational/configuration value (tinyint) |
| reserved_at | Readable operational/configuration value (int) |
| available_at | Readable operational/configuration value (int) |
| created_at | Readable operational/configuration value (int) |

## job_batches

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (varchar); UUIDs are not ciphertext |
| name | Readable operational/configuration value (varchar) |
| total_jobs | Readable operational/configuration value (int) |
| pending_jobs | Readable operational/configuration value (int) |
| failed_jobs | Readable operational/configuration value (int) |
| failed_job_ids | Readable operational/configuration value (longtext) |
| options | Readable operational/configuration value (mediumtext) |
| cancelled_at | Readable operational/configuration value (int) |
| created_at | Readable operational/configuration value (int) |
| finished_at | Readable operational/configuration value (int) |

## journal_entries

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| number | Readable operational/configuration value (varchar) |
| posting_key | Readable operational/configuration value (varchar) |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| date | Readable operational/configuration value (date) |
| description | Readable operational/configuration value (varchar) |
| reference_type | Readable operational/configuration value (varchar) |
| reference_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| reversal_of | Readable operational/configuration value (char) |
| posted_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## journal_lines

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| journal_entry_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| account_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| debit_minor | Readable operational/configuration value (bigint) |
| credit_minor | Readable operational/configuration value (bigint) |
| currency | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## migrations

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (int); UUIDs are not ciphertext |
| migration | Readable operational/configuration value (varchar) |
| batch | Readable operational/configuration value (int) |

## opening_balance_coverage

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| fee_invoice_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| from_period | Readable operational/configuration value (char) |
| through_period | Readable operational/configuration value (char) |
| approved_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## otp_challenges

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| parent_guardian_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| lookup_hash | Security digest/HMAC; retained |
| otp_hash | Security digest/HMAC; retained |
| expires_at | Readable operational/configuration value (timestamp) |
| consumed_at | Readable operational/configuration value (timestamp) |
| attempts | Readable operational/configuration value (smallint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## parent_guardians

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| full_name | Readable operational/configuration value (varchar) |
| cnic_ciphertext | Legacy ordinary-data ciphertext; validated conversion required before removal |
| cnic_lookup | Legacy guardian lookup HMAC; removed by successful conversion finalization |
| cnic_last4 | Readable legacy masking helper; removed with legacy guardian columns |
| mobile_ciphertext | Legacy ordinary-data ciphertext; validated conversion required before removal |
| mobile_verified_at | Readable operational/configuration value (timestamp) |
| verified_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| photo_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| cnic | Readable operational/configuration value (char) |
| mobile | Readable operational/configuration value (varchar) |

## parent_student_links

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| parent_guardian_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| relationship | Readable operational/configuration value (varchar) |
| is_primary | Readable operational/configuration value (tinyint) |
| is_active | Readable operational/configuration value (tinyint) |
| verified_by | Readable operational/configuration value (char) |
| verified_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| primary_guardian_key | Readable operational/configuration value (char) |

## payments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| receipt_number | Readable operational/configuration value (varchar) |
| payment_method_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| paid_on | Readable operational/configuration value (date) |
| amount_minor | Readable operational/configuration value (bigint) |
| reference | Readable operational/configuration value (varchar) |
| proof_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| status | Readable operational/configuration value (varchar) |
| request_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| recorded_by | Readable operational/configuration value (char) |
| verified_by | Readable operational/configuration value (char) |
| verified_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| notes | Readable operational/configuration value (varchar) |

## payment_allocations

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payment_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| fee_invoice_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| amount_minor | Readable operational/configuration value (bigint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## payment_methods

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| code | Readable operational/configuration value (varchar) |
| name | Readable operational/configuration value (varchar) |
| account_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| requires_verification | Readable operational/configuration value (tinyint) |
| is_active | Readable operational/configuration value (tinyint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## payment_refunds

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payment_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| number | Readable operational/configuration value (varchar) |
| refunded_on | Readable operational/configuration value (date) |
| amount_minor | Readable operational/configuration value (bigint) |
| reason | Readable operational/configuration value (varchar) |
| request_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| approved_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## payroll_adjustments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| staff_profile_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| period | Readable operational/configuration value (varchar) |
| amount_minor | Readable operational/configuration value (bigint) |
| reason | Readable operational/configuration value (varchar) |
| approved_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## payroll_items

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payroll_period_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| staff_profile_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| base_salary_minor | Readable operational/configuration value (bigint) |
| adjustment_minor | Readable operational/configuration value (bigint) |
| net_salary_minor | Readable operational/configuration value (bigint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## payroll_payments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payroll_period_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payment_method_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| recorded_by | Readable operational/configuration value (char) |
| request_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| amount_minor | Readable operational/configuration value (bigint) |
| paid_on | Readable operational/configuration value (date) |
| reference | Readable operational/configuration value (varchar) |
| notes | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## payroll_payment_allocations

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payroll_payment_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payroll_item_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| amount_minor | Readable operational/configuration value (bigint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## payroll_periods

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| period | Readable operational/configuration value (varchar) |
| status | Readable operational/configuration value (varchar) |
| total_minor | Readable operational/configuration value (bigint) |
| created_by | Readable operational/configuration value (char) |
| approved_by | Readable operational/configuration value (char) |
| approved_at | Readable operational/configuration value (timestamp) |
| paid_on | Readable operational/configuration value (date) |
| payment_method_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| reference | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## permissions

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| code | Readable operational/configuration value (varchar) |
| name | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## permission_role

| Column | Storage/classification |
| --- | --- |
| permission_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| role_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |

## private_files

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| uploaded_by | Readable operational/configuration value (char) |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| path | Readable operational/configuration value (varchar) |
| mime | Readable operational/configuration value (varchar) |
| purpose | Readable operational/configuration value (varchar) |
| size | Readable operational/configuration value (bigint) |
| sha256 | Integrity/deduplication/locking digest; source data remains readable |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| parent_guardian_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| owner_user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |

## push_deliveries

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| school_notification_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| push_device_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| status | Readable operational/configuration value (varchar) |
| attempts | Readable operational/configuration value (smallint) |
| next_attempt_at | Readable operational/configuration value (timestamp) |
| lease_until | Readable operational/configuration value (timestamp) |
| failure_code | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## push_devices

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| authentication_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| token_hash | Security digest/HMAC; retained |
| token_ciphertext | Encrypted push delivery credential; retained |
| platform | Readable operational/configuration value (varchar) |
| revoked_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## refund_allocations

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payment_refund_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payment_allocation_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| amount_minor | Readable operational/configuration value (bigint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## registration_sequences

| Column | Storage/classification |
| --- | --- |
| year | Readable operational/configuration value (smallint) |
| next_number | Readable operational/configuration value (int) |

## roles

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| code | Readable operational/configuration value (varchar) |
| name | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## school_days

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| date | Readable operational/configuration value (date) |
| is_school_day | Readable operational/configuration value (tinyint) |
| label | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## school_notifications

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| event_key | Readable operational/configuration value (varchar) |
| kind | Readable operational/configuration value (varchar) |
| target_type | Readable operational/configuration value (varchar) |
| target_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| read_at | Readable operational/configuration value (timestamp) |
| push_status | Readable operational/configuration value (varchar) |
| push_attempts | Readable operational/configuration value (int) |
| push_after | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## school_settings

| Column | Storage/classification |
| --- | --- |
| key | Readable operational/configuration value (varchar) |
| value | Readable operational/configuration value (longtext) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## sections

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| class_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| name | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## staff_compensation

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| staff_profile_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| base_salary_minor | Readable operational/configuration value (bigint) |
| effective_on | Readable operational/configuration value (date) |
| approved_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## staff_profiles

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| employee_number | Readable operational/configuration value (varchar) |
| joined_on | Readable operational/configuration value (date) |
| designation | Readable operational/configuration value (varchar) |
| is_active | Readable operational/configuration value (tinyint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## students

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| registration_number | Readable operational/configuration value (varchar) |
| admitted_on | Readable operational/configuration value (date) |
| full_name | Readable operational/configuration value (varchar) |
| gender | Readable operational/configuration value (varchar) |
| date_of_birth | Readable operational/configuration value (date) |
| father_name | Readable operational/configuration value (varchar) |
| address | Readable operational/configuration value (text) |
| previous_school | Readable operational/configuration value (varchar) |
| emergency_contact | Readable operational/configuration value (varchar) |
| status | Readable operational/configuration value (varchar) |
| fee_category_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| created_by | Readable operational/configuration value (char) |
| updated_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| photo_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |

## student_discounts

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| discount_policy_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| basis_points | Readable operational/configuration value (int) |
| starts_on | Readable operational/configuration value (date) |
| ends_on | Readable operational/configuration value (date) |
| eligibility_notes | Readable operational/configuration value (text) |
| evidence_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| status | Readable operational/configuration value (varchar) |
| requested_by | Readable operational/configuration value (char) |
| approved_by | Readable operational/configuration value (char) |
| approved_at | Readable operational/configuration value (timestamp) |
| revoked_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## student_fee_assignments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| academic_session_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| fee_category_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| tuition_override_minor | Readable operational/configuration value (bigint) |
| effective_on | Readable operational/configuration value (date) |
| approved_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## student_transitions

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| type | Readable operational/configuration value (varchar) |
| from_enrollment_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| to_enrollment_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| effective_on | Readable operational/configuration value (date) |
| reason | Readable operational/configuration value (text) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |

## subjects

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| code | Readable operational/configuration value (varchar) |
| name | Readable operational/configuration value (varchar) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## support_messages

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| support_ticket_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| sender_user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| body | Readable operational/configuration value (text) |
| is_internal | Readable operational/configuration value (tinyint) |
| client_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| payload_hash | Integrity/deduplication/locking digest; source data remains readable |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## support_message_attachments

| Column | Storage/classification |
| --- | --- |
| support_message_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| private_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |

## support_tickets

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| student_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| guardian_user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| number | Readable operational/configuration value (varchar) |
| category | Readable operational/configuration value (varchar) |
| subject | Readable operational/configuration value (varchar) |
| status | Readable operational/configuration value (varchar) |
| priority | Readable operational/configuration value (varchar) |
| assigned_to | Readable operational/configuration value (char) |
| version | Readable operational/configuration value (int) |
| resolved_at | Readable operational/configuration value (timestamp) |
| closed_at | Readable operational/configuration value (timestamp) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## support_ticket_events

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| support_ticket_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| from_status | Readable operational/configuration value (varchar) |
| to_status | Readable operational/configuration value (varchar) |
| created_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## support_ticket_forwards

| Column | Storage/classification |
| --- | --- |
| support_ticket_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| forwarded_by | Readable operational/configuration value (char) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

## teacher_assignments

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| staff_profile_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| section_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| subject_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| is_class_teacher | Readable operational/configuration value (tinyint) |
| is_active | Readable operational/configuration value (tinyint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| starts_on | Readable operational/configuration value (date) |
| ends_on | Readable operational/configuration value (date) |
| class_teacher_key | Readable operational/configuration value (char) |
| active_subject_key | Readable operational/configuration value (char) |

## transaction_locks

| Column | Storage/classification |
| --- | --- |
| key | Integrity/deduplication/locking digest; source data remains readable |

## users

| Column | Storage/classification |
| --- | --- |
| id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| name | Readable operational/configuration value (varchar) |
| email | Readable operational/configuration value (varchar) |
| password | One-way password hash; retained |
| is_active | Readable operational/configuration value (tinyint) |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |
| mobile | Readable operational/configuration value (varchar) |
| address | Readable operational/configuration value (text) |
| photo_file_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |

## user_role_assignments

| Column | Storage/classification |
| --- | --- |
| user_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| role_id | Normal readable identifier/reference (char); UUIDs are not ciphertext |
| created_at | Readable operational/configuration value (timestamp) |
| updated_at | Readable operational/configuration value (timestamp) |

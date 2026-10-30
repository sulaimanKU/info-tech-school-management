<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class MultipleTeachingAssignmentTest extends MobileTestCase
{
    private function teacherProfile(): array
    {
        $teacher = $this->user('teacher');
        $profile = Domain::insert('staff_profiles', ['user_id' => $teacher->id, 'employee_number' => 'STF-FICTIONAL', 'joined_on' => '2026-01-01', 'designation' => 'Teacher']);

        return [$teacher, $profile];
    }

    private function row(string $section, ?string $subject = null): array
    {
        return ['section_id' => $section, 'subject_id' => $subject ?? $this->subject, 'is_class_teacher' => false];
    }

    private function payload(string $profile, array $rows): array
    {
        return ['staff_profile_id' => $profile, 'academic_session_id' => $this->session, 'assignments' => $rows];
    }

    public function test_one_account_creates_multiple_classes_sections_and_subjects_atomically_and_sees_all_students(): void
    {
        [$teacher, $profile] = $this->teacherProfile();
        $class = DB::table('classes')->where('name', 'Grade 2')->value('id');
        $third = Domain::insert('sections', ['class_id' => $class, 'academic_session_id' => $this->session, 'name' => 'Fictional C']);
        $math = Domain::insert('subjects', ['code' => 'fictional-maths', 'name' => 'Fictional Mathematics']);
        foreach ([DB::table('sections')->find($this->section)->class_id, $class] as $classId) {
            Domain::insert('class_subject_assignments', ['class_id' => $classId, 'academic_session_id' => $this->session, 'subject_id' => $math]);
        }
        $studentIds = [$this->student(), $this->student($this->otherSection), $this->student($third)];
        $userCount = User::count();
        $rows = [$this->row($this->section), $this->row($this->otherSection), $this->row($this->section, $math), $this->row($third, $math)];
        $this->asUser($this->admin)->postJson('/api/v1/teaching-assignments', $this->payload($profile, $rows))->assertCreated()->assertJsonPath('data.count', 4)->assertJsonCount(4, 'data.ids');
        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('staff_profiles', 1);
        $this->assertDatabaseCount('teacher_assignments', 4);
        $this->asUser($teacher)->getJson('/api/v1/my-teaching')->assertOk()->assertJsonPath('data.assignment_count', 4)->assertJsonPath('data.student_count', 3)->assertJsonCount(3, 'data.sections');
        foreach ([$this->section, $this->otherSection, $third] as $index => $section) {
            $this->getJson('/api/v1/my-teaching/sections/'.$section.'/students')->assertOk()->assertJsonPath('data.data.0.id', $studentIds[$index]);
        }
    }

    public function test_existing_and_in_batch_duplicates_reject_the_whole_request_without_partial_writes(): void
    {
        [, $profile] = $this->teacherProfile();
        $row = $this->row($this->section);
        $this->asUser($this->admin);
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$row, $row]))->assertUnprocessable()->assertJsonValidationErrors('assignments.1.assignment', 'error.fields');
        $this->assertDatabaseCount('teacher_assignments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$row]))->assertCreated();
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->otherSection), $row]))->assertUnprocessable();
        $this->assertDatabaseCount('teacher_assignments', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_teacher_session_class_section_and_subject_filters_intersect_scope(): void
    {
        [, $profile] = $this->teacherProfile();
        $other = $this->teacher();
        $otherProfile = DB::table('staff_profiles')->where('user_id', $other->id)->value('id');
        $this->asUser($this->admin)->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->section), $this->row($this->otherSection)]))->assertCreated();
        $class = DB::table('sections')->find($this->section)->class_id;
        $this->getJson('/api/v1/teaching-assignments?'.http_build_query(['staff_profile_id' => $profile, 'academic_session_id' => $this->session, 'class_id' => $class, 'section_id' => $this->otherSection, 'subject_id' => $this->subject]))->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.staff_profile_id', $profile);
        $this->getJson('/api/v1/teaching-assignments?staff_profile_id='.$otherProfile)->assertOk()->assertJsonCount(1, 'data.data');
        $this->asUser($this->coordinator())->getJson('/api/v1/teaching-assignments?staff_profile_id='.$profile)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.section_id', $this->section);
        $this->getJson('/api/v1/teaching-assignments?staff_profile_id='.$profile.'&section_id='.$this->otherSection)->assertForbidden();
    }

    public function test_coordinator_batch_cannot_cross_scope_and_rolls_back_while_admins_retain_access(): void
    {
        [, $profile] = $this->teacherProfile();
        $input = $this->payload($profile, [$this->row($this->section), $this->row($this->otherSection)]);
        $this->asUser($this->coordinator())->postJson('/api/v1/teaching-assignments', $input)->assertForbidden();
        $this->assertDatabaseCount('teacher_assignments', 0);
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->section)]))->assertCreated();
        $this->asUser($this->user('school_administrator'))->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->otherSection)]))->assertCreated();
        foreach (['teacher', 'parent_guardian', 'accountant_clerk', 'coordinator'] as $role) {
            $this->asUser($this->user($role))->postJson('/api/v1/teaching-assignments', $input)->assertForbidden();
        }
        $this->assertDatabaseCount('teacher_assignments', 2);
    }

    public function test_batch_rejects_mismatched_session_subject_invalid_dates_and_unexpected_row_fields(): void
    {
        [, $profile] = $this->teacherProfile();
        $otherSession = Domain::insert('academic_sessions', ['name' => 'Fictional Next 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $next = Domain::insert('sections', ['class_id' => DB::table('sections')->find($this->section)->class_id, 'academic_session_id' => $otherSession, 'name' => 'Next A']);
        $unrelated = Domain::insert('subjects', ['code' => 'unconfigured-subject', 'name' => 'Fictional Not Configured']);
        $this->asUser($this->admin);
        foreach ([
            $this->row($next),
            $this->row($this->section, $unrelated),
            [...$this->row($this->section), 'starts_on' => '2025-12-31'],
            [...$this->row($this->section), 'starts_on' => '2026-11-01', 'ends_on' => '2026-10-01'],
            [...$this->row($this->section), 'is_active' => false],
        ] as $invalid) {
            $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->otherSection), $invalid]))->assertUnprocessable();
            $this->assertDatabaseCount('teacher_assignments', 0);
        }
    }

    public function test_batch_rejects_inactive_or_non_teacher_profiles_without_changing_accounts(): void
    {
        [$teacher, $profile] = $this->teacherProfile();
        $hash = $teacher->password;
        $this->asUser($this->admin);
        DB::table('staff_profiles')->where('id', $profile)->update(['is_active' => false]);
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->section)]))->assertUnprocessable();
        DB::table('staff_profiles')->where('id', $profile)->update(['is_active' => true]);
        $teacher->update(['is_active' => false]);
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->section)]))->assertUnprocessable();
        $teacher->update(['is_active' => true]);
        $teacher->roles()->detach();
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->section)]))->assertUnprocessable();
        $this->assertDatabaseCount('teacher_assignments', 0);
        $this->assertSame($hash, $teacher->fresh()->password);
    }

    public function test_batch_limits_and_row_schema_reject_oversized_or_missing_input_before_writes(): void
    {
        [, $profile] = $this->teacherProfile();
        $this->asUser($this->admin);
        foreach ([[], array_fill(0, 51, $this->row($this->section)), [['section_id' => $this->section]]] as $rows) {
            $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, $rows))->assertUnprocessable();
        }
        $this->assertDatabaseCount('teacher_assignments', 0);
    }

    public function test_multiple_assignments_do_not_allow_unrelated_students_sections_or_subject_writes(): void
    {
        [$teacher, $profile] = $this->teacherProfile();
        $one = $this->student();
        $two = $this->student($this->otherSection);
        $outside = Domain::insert('sections', ['class_id' => DB::table('sections')->find($this->section)->class_id, 'academic_session_id' => $this->session, 'name' => 'Fictional Outside']);
        $unrelated = $this->student($outside);
        $otherSubject = Domain::insert('subjects', ['code' => 'separate-science', 'name' => 'Fictional Science']);
        Domain::insert('class_subject_assignments', ['class_id' => DB::table('sections')->find($this->section)->class_id, 'academic_session_id' => $this->session, 'subject_id' => $otherSubject]);
        Domain::insert('grading_rules', ['academic_session_id' => $this->session, 'minimum_percent' => 0, 'grade' => 'F']);
        $this->asUser($this->admin)->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->section), $this->row($this->otherSection)]))->assertCreated();
        $this->asUser($teacher);
        $this->getJson('/api/v1/my-teaching/sections/'.$outside.'/students')->assertForbidden();
        $this->getJson('/api/v1/students/'.$unrelated)->assertNotFound();
        foreach ([$this->section => $one, $this->otherSection => $two] as $section => $student) {
            $this->getJson('/api/v1/attendance/register?section_id='.$section.'&date=2026-10-01')->assertOk()->assertJsonPath('data.students.0.id', $student);
        }
        $attendance = ['section_id' => $outside, 'date' => '2026-10-01', 'status' => 'draft', 'version' => 0, 'request_id' => (string) Str::uuid(), 'records' => [['student_id' => $unrelated, 'status' => 'present']]];
        $this->postJson('/api/v1/attendance/register', $attendance)->assertForbidden();
        $this->postJson('/api/v1/attendance/register', [...$attendance, 'section_id' => $this->section])->assertUnprocessable();
        $diary = ['section_id' => $outside, 'subject_id' => $this->subject, 'date' => '2026-10-01', 'topic' => 'Fictional topic', 'homework' => 'Fictional work', 'whole_class' => true, 'status' => 'draft', 'version' => 0, 'client_id' => (string) Str::uuid(), 'students' => [], 'attachments' => []];
        $assessment = ['section_id' => $outside, 'subject_id' => $this->subject, 'date' => '2026-10-01', 'type' => 'monthly_test', 'title' => 'Fictional assessment', 'maximum_marks' => 100];
        foreach ([$diary, [...$diary, 'section_id' => $this->section, 'subject_id' => $otherSubject]] as $input) {
            $this->postJson('/api/v1/diary', $input)->assertForbidden();
        }
        foreach ([$assessment, [...$assessment, 'section_id' => $this->section, 'subject_id' => $otherSubject]] as $input) {
            $this->postJson('/api/v1/assessments', $input)->assertForbidden();
        }
        $this->postJson('/api/v1/diary', [...$diary, 'section_id' => $this->otherSection])->assertOk();
        $this->postJson('/api/v1/assessments', [...$assessment, 'section_id' => $this->otherSection])->assertCreated();
    }

    public function test_deactivation_preserves_teacher_student_and_historical_academic_rows(): void
    {
        [$teacher, $profile] = $this->teacherProfile();
        $student = $this->student();
        $this->asUser($this->admin);
        $ids = $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$this->row($this->section), $this->row($this->otherSection)]))->assertCreated()->json('data.ids');
        $assessment = Domain::insert('assessments', ['section_id' => $this->section, 'academic_session_id' => $this->session, 'subject_id' => $this->subject, 'teacher_user_id' => $teacher->id, 'type' => 'monthly_test', 'title' => 'Fictional retained assessment', 'date' => '2026-10-01', 'maximum_marks' => 100]);
        $this->postJson('/api/v1/catalog/teacher-assignments/'.$ids[0].'/deactivate')->assertOk();
        $this->assertDatabaseHas('teacher_assignments', ['id' => $ids[0], 'is_active' => false]);
        $this->assertDatabaseHas('assessments', ['id' => $assessment]);
        $this->assertDatabaseHas('students', ['id' => $student]);
        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
        $this->assertDatabaseCount('teacher_assignments', 2);
        $this->asUser($teacher)->getJson('/api/v1/my-teaching')->assertOk()->assertJsonCount(1, 'data.sections')->assertJsonPath('data.sections.0.section_id', $this->otherSection);
        $this->getJson('/api/v1/my-teaching/sections/'.$this->section.'/students')->assertForbidden();
    }

    public function test_single_edit_and_batch_keep_enabled_row_uniqueness_and_preserve_deactivated_history(): void
    {
        [, $profile] = $this->teacherProfile();
        $this->asUser($this->admin);
        $old = [...$this->row($this->section), 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30'];
        $new = [...$this->row($this->section), 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31'];
        $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$old, $new]))->assertUnprocessable()->assertJsonValidationErrors('assignments.1.assignment', 'error.fields');
        $this->assertDatabaseCount('teacher_assignments', 0);
        $oldId = $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$old]))->assertCreated()->json('data.ids.0');
        $this->postJson('/api/v1/catalog/teacher-assignments/'.$oldId.'/deactivate')->assertOk();
        $newId = $this->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$new]))->assertCreated()->json('data.ids.0');
        $this->patchJson('/api/v1/catalog/teacher-assignments/'.$oldId, ['is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('assignment', 'error.fields');
        $this->assertDatabaseHas('teacher_assignments', ['id' => $oldId, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_active' => false]);
        $this->assertDatabaseHas('teacher_assignments', ['id' => $newId, 'starts_on' => '2026-07-01', 'is_active' => true]);
        $this->postJson('/api/v1/catalog/teacher-assignments', ['staff_profile_id' => $profile, ...$new])->assertUnprocessable();
        $this->assertDatabaseCount('teacher_assignments', 2);
    }

    public function test_class_teacher_and_coordinator_uniqueness_are_validated_before_database_conflicts(): void
    {
        [, $profile] = $this->teacherProfile();
        $second = $this->user('teacher');
        $secondProfile = Domain::insert('staff_profiles', ['user_id' => $second->id, 'employee_number' => 'STF-SECOND', 'joined_on' => '2026-01-01', 'designation' => 'Teacher']);
        $old = [...$this->row($this->section), 'is_class_teacher' => true, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30'];
        $new = [...$old, 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31'];
        $this->asUser($this->admin)->postJson('/api/v1/teaching-assignments', $this->payload($profile, [$old]))->assertCreated();
        $this->postJson('/api/v1/teaching-assignments', $this->payload($secondProfile, [$new]))->assertUnprocessable()->assertJsonValidationErrors('assignments.0.assignment', 'error.fields');
        $coordinator = $this->coordinator();
        $coordinatorProfile = DB::table('staff_profiles')->where('user_id', $coordinator->id)->value('id');
        $oldCoordinator = DB::table('coordinator_assignments')->where('staff_profile_id', $coordinatorProfile)->value('id');
        $this->patchJson('/api/v1/catalog/coordinator-assignments/'.$oldCoordinator, ['starts_on' => '2026-01-01', 'ends_on' => '2026-06-30'])->assertOk();
        $input = ['staff_profile_id' => $coordinatorProfile, 'section_id' => $this->section, 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31'];
        $this->postJson('/api/v1/catalog/coordinator-assignments', $input)->assertUnprocessable()->assertJsonValidationErrors('assignment', 'error.fields');
        $this->postJson('/api/v1/catalog/coordinator-assignments/'.$oldCoordinator.'/deactivate')->assertOk();
        $this->postJson('/api/v1/catalog/coordinator-assignments', $input)->assertCreated();
        $this->assertDatabaseCount('teacher_assignments', 1);
        $this->assertDatabaseCount('coordinator_assignments', 2);
        $this->assertDatabaseHas('coordinator_assignments', ['id' => $oldCoordinator, 'is_active' => false, 'ends_on' => '2026-06-30']);
    }
}

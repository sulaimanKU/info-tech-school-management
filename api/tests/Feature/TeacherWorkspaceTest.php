<?php

namespace Tests\Feature;

use App\Support\Domain;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileTestCase;

class TeacherWorkspaceTest extends MobileTestCase
{
    public function test_daily_workspace_excludes_future_assignments_and_closed_sessions_without_deleting_history(): void
    {
        $this->asUser($this->teacher());
        DB::table('teacher_assignments')->update(['starts_on' => now()->addDay()->toDateString()]);
        $this->getJson('/api/v1/my-teaching')->assertOk()->assertJsonCount(0, 'data.sections');
        DB::table('teacher_assignments')->update(['starts_on' => null]);
        DB::table('academic_sessions')->where('id', $this->session)->update(['ends_on' => now()->subDay()->toDateString()]);
        $this->getJson('/api/v1/my-teaching')->assertOk()->assertJsonCount(0, 'data.sections');
        $this->assertDatabaseCount('teacher_assignments', 1);
    }

    public function test_teacher_workspace_and_paginated_search_only_show_assignments_and_enrolled_students(): void
    {
        $own = $this->student();
        $other = $this->student($this->otherSection);
        $teacher = $this->teacher();
        $this->asUser($teacher)->getJson('/api/v1/my-teaching')->assertOk()->assertJsonCount(1, 'data.sections')->assertJsonPath('data.sections.0.section_id', $this->section)->assertJsonPath('data.sections.0.student_count', 1)->assertJsonPath('data.sections.0.subjects.0.id', $this->subject)->assertJsonPath('data.schedule_available', false);
        $this->getJson('/api/v1/my-teaching/sections/'.$this->section.'/students')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $own)->assertJsonMissingPath('data.data.0.address');
        $this->getJson('/api/v1/my-teaching/sections/'.$this->section.'/students?search=not-enrolled')->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/my-teaching/sections/'.$this->otherSection.'/students')->assertForbidden();
        $this->getJson('/api/v1/students/'.$other)->assertNotFound();
        DB::table('teacher_assignments')->update(['is_active' => false]);
        $this->getJson('/api/v1/my-teaching')->assertOk()->assertJsonCount(0, 'data.sections');
        $this->getJson('/api/v1/my-teaching/sections/'.$this->section.'/students')->assertForbidden();
    }

    public function test_multiple_subject_section_and_class_assignments_do_not_multiply_roster_counts(): void
    {
        $this->student();
        $this->student($this->otherSection);
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
        $subject = Domain::insert('subjects', ['code' => 'fictional-math', 'name' => 'Fictional Math']);
        Domain::insert('class_subject_assignments', ['class_id' => DB::table('sections')->find($this->section)->class_id, 'academic_session_id' => $this->session, 'subject_id' => $subject]);
        Domain::insert('teacher_assignments', ['staff_profile_id' => $profile, 'section_id' => $this->section, 'subject_id' => $subject]);
        Domain::insert('teacher_assignments', ['staff_profile_id' => $profile, 'section_id' => $this->otherSection, 'subject_id' => $this->subject]);
        $this->asUser($teacher)->getJson('/api/v1/my-teaching')->assertOk()->assertJsonPath('data.assignment_count', 3)->assertJsonPath('data.student_count', 2)->assertJsonCount(2, 'data.sections')->assertJsonCount(2, 'data.sections.0.subjects');
    }

    public function test_coordinator_can_manage_only_teacher_assignments_in_own_scope_without_staff_security_access(): void
    {
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
        $coordinator = $this->coordinator();
        $this->asUser($coordinator)->getJson('/api/v1/teaching-assignments')->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/teaching-assignments/teachers')->assertOk()->assertJsonPath('data.data.0.user_id', $teacher->id)->assertJsonMissingPath('data.data.0.email');
        $assignment = DB::table('teacher_assignments')->value('id');
        $this->patchJson('/api/v1/catalog/teacher-assignments/'.$assignment, ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->assertOk();
        $this->postJson('/api/v1/catalog/teacher-assignments', ['staff_profile_id' => $profile, 'section_id' => $this->otherSection, 'subject_id' => $this->subject, 'is_class_teacher' => false])->assertForbidden();
        $this->getJson('/api/v1/staff-accounts')->assertForbidden();
        $this->postJson('/api/v1/catalog/coordinator-assignments', ['staff_profile_id' => DB::table('staff_profiles')->where('user_id', $coordinator->id)->value('id'), 'section_id' => $this->otherSection])->assertForbidden();
        $this->postJson('/api/v1/catalog/teacher-assignments/'.$assignment.'/deactivate')->assertOk();
        $this->asUser($teacher)->getJson('/api/v1/my-teaching')->assertJsonCount(0, 'data.sections');
    }

    public function test_unassigned_coordinator_teacher_accountant_and_parent_cannot_manage_school_assignments(): void
    {
        foreach ([$this->teacher(), $this->user('accountant_clerk'), $this->user('parent_guardian')] as $user) {
            $this->asUser($user)->getJson('/api/v1/teaching-assignments')->assertForbidden();
        }
        $this->asUser($this->user('coordinator'))->getJson('/api/v1/teaching-assignments')->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_future_homework_roster_is_available_without_allowing_future_attendance_or_outside_session(): void
    {
        $student = $this->student();
        $this->asUser($this->teacher());
        $future = now()->addDay()->toDateString();
        $this->getJson('/api/v1/academic-roster?section_id='.$this->section.'&date='.$future)->assertOk()->assertJsonPath('data.students.0.id', $student);
        $this->getJson('/api/v1/attendance/register?section_id='.$this->section.'&date='.$future)->assertUnprocessable();
        $this->getJson('/api/v1/academic-roster?section_id='.$this->otherSection.'&date='.$future)->assertForbidden();
        $this->getJson('/api/v1/academic-roster?section_id='.$this->section.'&date=2027-01-01')->assertUnprocessable();
    }

    public function test_admin_assigns_assessment_to_teacher_then_marks_review_and_parent_publication_work(): void
    {
        $student = $this->student();
        $other = $this->student($this->otherSection);
        [, $parent] = $this->guardian($student);
        $teacher = $this->teacher();
        Domain::insert('grading_rules', ['academic_session_id' => $this->session, 'minimum_percent' => 0, 'grade' => 'F']);
        Domain::insert('grading_rules', ['academic_session_id' => $this->session, 'minimum_percent' => 80, 'grade' => 'A']);
        $input = ['section_id' => $this->section, 'subject_id' => $this->subject, 'teacher_user_id' => $teacher->id, 'type' => 'monthly_test', 'title' => 'Fictional configured assessment', 'date' => '2026-10-01', 'maximum_marks' => 100];
        $this->asUser($this->admin);
        $id = $this->postJson('/api/v1/assessments', $input)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/assessments', [...$input, 'teacher_user_id' => $this->teacher($this->otherSection)->id])->assertUnprocessable();
        $this->asUser($teacher)->getJson('/api/v1/assessments')->assertJsonPath('data.data.0.id', $id);
        $this->getJson('/api/v1/assessments/'.$id)->assertJsonPath('data.can_edit', true);
        $this->putJson('/api/v1/assessments/'.$id.'/marks', ['version' => 1, 'status' => 'submitted', 'results' => [['student_id' => $other, 'obtained_marks' => 90]]])->assertUnprocessable();
        $this->putJson('/api/v1/assessments/'.$id.'/marks', ['version' => 1, 'status' => 'submitted', 'results' => [['student_id' => $student, 'obtained_marks' => 101]]])->assertUnprocessable();
        $this->putJson('/api/v1/assessments/'.$id.'/marks', ['version' => 1, 'status' => 'submitted', 'results' => [['student_id' => $student, 'obtained_marks' => 90]]])->assertOk();
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/assessments')->assertJsonCount(0, 'data.items');
        $this->asUser($this->admin)->postJson('/api/v1/assessments/'.$id.'/review', ['version' => 2, 'action' => 'approve'])->assertOk();
        $this->postJson('/api/v1/assessments/'.$id.'/review', ['version' => 3, 'action' => 'publish'])->assertOk();
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/assessments')->assertJsonPath('data.items.0.grade', 'A');
        $this->getJson('/api/v1/students/'.$other.'/assessments')->assertNotFound();
    }

    public function test_coordinator_cannot_move_or_deactivate_an_outside_assignment_to_gain_access(): void
    {
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
        $outside = Domain::insert('teacher_assignments', ['staff_profile_id' => $profile, 'section_id' => $this->otherSection, 'subject_id' => $this->subject]);
        $this->asUser($this->coordinator());
        $this->patchJson('/api/v1/catalog/teacher-assignments/'.$outside, ['section_id' => $this->section])->assertForbidden();
        $this->postJson('/api/v1/catalog/teacher-assignments/'.$outside.'/deactivate')->assertForbidden();
        $this->assertDatabaseHas('teacher_assignments', ['id' => $outside, 'section_id' => $this->otherSection, 'is_active' => true]);
    }
}

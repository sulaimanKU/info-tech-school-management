<?php

namespace Tests\Feature;

use App\Support\Domain;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class AcademicWorkflowTest extends MobileTestCase
{
    private function attendance(string $student, array $extra = []): array
    {
        return [...['section_id' => $this->section, 'date' => '2026-10-01', 'status' => 'submitted', 'version' => 0, 'request_id' => (string) Str::uuid(), 'records' => [['student_id' => $student, 'status' => 'present']]], ...$extra];
    }

    private function diary(array $extra = []): array
    {
        return [...['section_id' => $this->section, 'subject_id' => $this->subject, 'date' => '2026-10-01', 'topic' => 'Fictional lesson', 'homework' => 'Read the fictional worksheet.', 'whole_class' => true, 'status' => 'draft', 'version' => 0, 'client_id' => (string) Str::uuid(), 'students' => [], 'attachments' => []], ...$extra];
    }

    public function test_attendance_submit_retry_conflict_and_correction_authorization(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();
        $v = $this->attendance($student);
        $this->asUser($teacher);
        $one = $this->postJson('/api/v1/attendance/register', $v)->assertOk()->assertJsonPath('data.version', 1);
        $this->postJson('/api/v1/attendance/register', $v)->assertOk()->assertJsonPath('data.id', $one->json('data.id'));
        $this->assertDatabaseCount('attendance_records', 1);
        $this->postJson('/api/v1/attendance/register', $this->attendance($student))->assertStatus(409);
        $this->postJson('/api/v1/attendance/register', $this->attendance($student, ['version' => 1]))->assertForbidden();
        $this->asUser($this->coordinator())->postJson('/api/v1/attendance/register', $this->attendance($student, ['version' => 1, 'records' => [['student_id' => $student, 'status' => 'late']]]))->assertOk()->assertJsonPath('data.version', 2);
        $this->assertDatabaseCount('attendance_revisions', 2);
    }

    public function test_teacher_cannot_mark_unassigned_class_or_omit_submitted_students(): void
    {
        $one = $this->student();
        $this->student(roll: 2);
        $other = $this->student($this->otherSection);
        $this->asUser($this->teacher());
        $this->postJson('/api/v1/attendance/register', $this->attendance($one))->assertUnprocessable();
        $this->postJson('/api/v1/attendance/register', $this->attendance($other, ['section_id' => $this->otherSection]))->assertForbidden();
    }

    public function test_calendar_denominator_and_parent_history_are_scoped(): void
    {
        $student = $this->student();
        $other = $this->student($this->otherSection);
        [, $parent] = $this->guardian($student);
        $this->asUser($this->teacher())->postJson('/api/v1/attendance/register', $this->attendance($student))->assertOk();
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/attendance?month=2026-10')->assertOk()->assertJsonPath('data.recorded_days', 1)->assertJsonPath('data.provisional', true);
        $this->getJson('/api/v1/students/'.$other.'/attendance?month=2026-10')->assertNotFound();
    }

    public function test_diary_drafts_private_and_selected_audience_published_only(): void
    {
        $one = $this->student();
        $two = $this->student(roll: 2);
        [, $p1] = $this->guardian($one);
        [, $p2] = $this->guardian($two, '0000000000002');
        $teacher = $this->teacher();
        $this->asUser($teacher);
        $v = $this->diary(['whole_class' => false, 'students' => [$one]]);
        $id = $this->postJson('/api/v1/diary', $v)->assertOk()->json('data.id');
        $this->asUser($p1)->getJson('/api/v1/diary/'.$id)->assertNotFound();
        $this->asUser($teacher)->patchJson('/api/v1/diary/'.$id, [...$v, 'status' => 'published', 'version' => 1])->assertOk();
        $this->asUser($p1)->getJson('/api/v1/diary/'.$id)->assertOk();
        $this->asUser($p2)->getJson('/api/v1/diary/'.$id)->assertNotFound();
        $this->getJson('/api/v1/diary')->assertOk()->assertJsonCount(0, 'data.data');
        $this->asUser($teacher)->getJson('/api/v1/diary/'.$id.'/revisions')->assertOk()->assertJsonCount(2, 'data.items');
    }

    public function test_assessment_review_publication_and_parent_marks_privacy(): void
    {
        $student = $this->student();
        [, $parent] = $this->guardian($student);
        $teacher = $this->teacher();
        Domain::insert('grading_rules', ['academic_session_id' => $this->session, 'minimum_percent' => 0, 'grade' => 'F']);
        Domain::insert('grading_rules', ['academic_session_id' => $this->session, 'minimum_percent' => 80, 'grade' => 'A']);
        $this->asUser($teacher);
        $id = $this->postJson('/api/v1/assessments', ['section_id' => $this->section, 'subject_id' => $this->subject, 'type' => 'monthly_test', 'title' => 'Fictional Test', 'date' => '2026-10-01', 'maximum_marks' => 100])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/assessments/'.$id.'/marks', ['version' => 1, 'status' => 'submitted', 'results' => [['student_id' => $student, 'obtained_marks' => 85, 'remarks' => 'Fictional encouraging remark']]])->assertOk();
        $this->postJson('/api/v1/assessments/'.$id.'/review', ['action' => 'publish', 'version' => 2])->assertForbidden();
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/assessments')->assertOk()->assertJsonCount(0, 'data.items');
        $this->asUser($this->coordinator())->postJson('/api/v1/assessments/'.$id.'/review', ['action' => 'approve', 'version' => 2])->assertOk();
        $this->postJson('/api/v1/assessments/'.$id.'/review', ['action' => 'publish', 'version' => 3])->assertOk();
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/assessments')->assertOk()->assertJsonPath('data.overall_percentage', 85)->assertJsonPath('data.items.0.grade', 'A');
        $this->getJson('/api/v1/assessments/'.$id)->assertForbidden();
    }

    public function test_behavior_is_private_until_review_and_opted_in_for_parent(): void
    {
        $student = $this->student();
        [, $parent] = $this->guardian($student);
        $this->asUser($this->teacher());
        $id = $this->postJson('/api/v1/students/'.$student.'/behavior', ['date' => '2026-10-01', 'area' => 'participation', 'category' => 'improving', 'observation' => 'Fictional constructive observation.', 'parent_visible' => true])->assertCreated()->json('data.id');
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/behavior')->assertOk()->assertJsonCount(0, 'data.items');
        $this->asUser($this->coordinator())->postJson('/api/v1/behavior/'.$id.'/publish')->assertOk();
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/behavior')->assertOk()->assertJsonCount(1, 'data.items');
    }
}

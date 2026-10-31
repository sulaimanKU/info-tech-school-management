<?php

namespace Tests\Feature;

use App\Support\Domain;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileTestCase;

class SubjectBulkAssignmentTest extends MobileTestCase
{
    public function test_subject_codes_and_multi_class_subjects_save_with_one_section_form_master(): void
    {
        $teacher = $this->user('teacher');
        $hash = $teacher->password;
        $class = DB::table('sections')->find($this->section)->class_id;
        $otherClass = DB::table('classes')->where('name', 'Grade 2')->value('id');
        $otherSection = Domain::insert('sections', ['class_id' => $otherClass, 'academic_session_id' => $this->session, 'name' => 'A']);
        $english = Domain::insert('subjects', ['code' => 'ENG-01', 'name' => 'Language']);
        $secondEnglish = Domain::insert('subjects', ['code' => 'ENG-02', 'name' => 'Language']);
        foreach ([$class, $otherClass] as $classId) {
            foreach ([$english, $secondEnglish] as $subject) {
                Domain::insert('class_subject_assignments', ['class_id' => $classId, 'academic_session_id' => $this->session, 'subject_id' => $subject]);
            }
        }
        $this->asUser($this->admin)->getJson('/api/v1/teaching-assignments/teachers')->assertOk()->assertJsonPath('data.data.0.profile_link_required', true);
        $this->getJson('/api/v1/catalog/subjects')->assertOk()->assertJsonFragment(['code' => 'ENG-01', 'name' => 'Language'])->assertJsonFragment(['code' => 'ENG-02', 'name' => 'Language']);
        $this->getJson('/api/v1/academic-filter-options?section_id='.$this->section)->assertOk()->assertJsonFragment(['id' => $english, 'code' => 'ENG-01', 'name' => 'Language'])->assertJsonFragment(['id' => $secondEnglish, 'code' => 'ENG-02', 'name' => 'Language']);
        $rows = [
            ['section_id' => $this->section, 'subject_id' => $english, 'is_class_teacher' => false],
            ['section_id' => $this->section, 'subject_id' => $secondEnglish, 'is_class_teacher' => false],
            ['section_id' => $otherSection, 'subject_id' => $english, 'is_class_teacher' => false],
            ['section_id' => $this->section, 'subject_id' => null, 'is_class_teacher' => true],
        ];
        $input = ['teacher_user_id' => $teacher->id, 'academic_session_id' => $this->session, 'employment' => ['joined_on' => '2026-01-01'], 'assignments' => $rows];
        $this->postJson('/api/v1/teaching-assignments', $input)->assertCreated()->assertJsonPath('data.count', 4);
        $this->assertDatabaseCount('staff_profiles', 1);
        $this->assertSame(1, DB::table('teacher_assignments')->where('is_class_teacher', true)->whereNull('subject_id')->count());
        $this->getJson('/api/v1/teaching-assignments?teacher_user_id='.$teacher->id)->assertOk()->assertJsonCount(4, 'data.data')->assertJsonFragment(['subject_code' => 'ENG-01', 'subject_name' => 'Language'])->assertJsonFragment(['subject_code' => 'ENG-02', 'subject_name' => 'Language']);
        unset($input['employment']);
        $this->postJson('/api/v1/teaching-assignments', $input)->assertUnprocessable();
        $this->assertDatabaseCount('teacher_assignments', 4);
        $this->assertSame($hash, $teacher->fresh()->password);
    }

    public function test_form_master_conflict_is_explained_and_rolls_back_the_entire_batch(): void
    {
        $owner = $this->teacher();
        $ownerProfile = DB::table('staff_profiles')->where('user_id', $owner->id)->value('id');
        $teacher = $this->user('teacher');
        $this->asUser($this->admin)->postJson('/api/v1/teaching-assignments', [
            'teacher_user_id' => $teacher->id, 'academic_session_id' => $this->session,
            'employment' => ['joined_on' => '2026-01-01'],
            'assignments' => [
                ['section_id' => $this->section, 'subject_id' => $this->subject, 'is_class_teacher' => false],
                ['section_id' => $this->section, 'subject_id' => null, 'is_class_teacher' => true],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('assignments.1.assignment', 'error.fields')->assertSee('This section already has an active class teacher.');
        $this->assertDatabaseCount('teacher_assignments', 1);
        $this->assertDatabaseHas('teacher_assignments', ['staff_profile_id' => $ownerProfile, 'section_id' => $this->section, 'is_class_teacher' => true]);
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $teacher->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Support\Domain;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileTestCase;

class StudentManagementTest extends MobileTestCase
{
    public function test_admission_update_and_permanent_number_after_promotion(): void
    {
        $this->asUser($this->admin);
        $id = $this->postJson('/api/v1/students', $this->admission())->assertCreated()->json('data.id');
        $number = DB::table('students')->where('id', $id)->value('registration_number');
        $this->assertSame('INF-SG-26-0001', $number);
        $this->patchJson('/api/v1/students/'.$id, ['full_name' => 'Fictional Revised Name', 'registration_number' => 'ATTACK', 'created_by' => 'ATTACK'])->assertOk()->assertJsonPath('data.registration_number', $number);
        $session = Domain::insert('academic_sessions', ['name' => 'Test 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $section = Domain::insert('sections', ['class_id' => DB::table('classes')->where('name', 'Grade 2')->value('id'), 'academic_session_id' => $session, 'name' => 'A']);
        $this->postJson('/api/v1/students/'.$id.'/transitions', ['type' => 'promotion', 'effective_on' => '2027-01-01', 'section_id' => $section, 'roll_number' => 1])->assertOk()->assertJsonPath('data.registration_number', $number);
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseHas('student_transitions', ['student_id' => $id, 'type' => 'promotion']);
    }

    public function test_withdrawal_readmission_and_duplicate_roll(): void
    {
        $id = $this->student();
        $this->asUser($this->admin);
        $this->postJson('/api/v1/students', $this->admission())->assertUnprocessable();
        $this->postJson('/api/v1/students/'.$id.'/transitions', ['type' => 'withdrawal', 'effective_on' => '2026-10-02'])->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->postJson('/api/v1/students/'.$id.'/transitions', ['type' => 'readmission', 'effective_on' => '2026-10-03', 'section_id' => $this->section, 'roll_number' => 1])->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_teacher_only_sees_assigned_class_and_cannot_admit(): void
    {
        $own = $this->student();
        $other = $this->student($this->otherSection);
        $this->asUser($this->teacher());
        $this->getJson('/api/v1/students/'.$own)->assertOk();
        $this->getJson('/api/v1/students/'.$other)->assertNotFound();
        $this->getJson('/api/v1/students')->assertOk()->assertJsonCount(1, 'data.data');
        $this->postJson('/api/v1/students', $this->admission())->assertForbidden();
    }

    public function test_parent_links_multiple_children_and_cross_parent_idor(): void
    {
        $one = $this->student();
        $two = $this->student(roll: 2);
        $other = $this->student($this->otherSection);
        [$g,$parent] = $this->guardian($one);
        $this->asUser($this->admin)->putJson('/api/v1/guardians/'.$g.'/links', ['student_id' => $two, 'relationship' => 'guardian', 'is_primary' => true])->assertOk();
        [, $otherParent] = $this->guardian($other, '0000000000002');
        $this->asUser($parent)->getJson('/api/v1/students')->assertOk()->assertJsonCount(2, 'data.data');
        $this->getJson('/api/v1/students/'.$other)->assertNotFound();
        $this->asUser($otherParent)->getJson('/api/v1/students/'.$one)->assertNotFound();
        $this->getJson('/api/v1/guardians')->assertForbidden();
    }

    public function test_guardian_values_readable_and_role_escalation_forbidden(): void
    {
        $id = $this->student();
        [$g] = $this->guardian($id);
        $row = DB::table('parent_guardians')->find($g);
        $this->assertSame('0000000000001', $row->cnic);
        $this->assertSame('+923000000001', $row->mobile);
        $this->getJson('/api/v1/guardians')->assertOk()->assertDontSee('0000000000001')->assertDontSee('+923000000001')->assertDontSee('cnic_lookup');
        $this->asUser($this->user('school_administrator'))->postJson('/api/v1/staff-accounts', ['name' => 'Fictional', 'email' => 'role@example.test', 'password' => 'fictional-password-only', 'role' => 'super_admin'])->assertForbidden();
    }
}

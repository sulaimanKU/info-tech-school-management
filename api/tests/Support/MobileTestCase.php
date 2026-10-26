<?php

namespace Tests\Support;

use App\Models\Role;
use App\Models\User;
use App\Services\SessionIssuer;
use App\Services\StudentService;
use App\Support\Domain;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MobilePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class MobileTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $section;

    protected string $otherSection;

    protected string $subject;

    protected string $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(MobilePermissionSeeder::class);
        $this->admin = $this->user('super_admin');
        $this->session = Domain::insert('academic_sessions', ['name' => 'Fictional Test 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $class = DB::table('classes')->where('name', 'Grade 1')->value('id');
        $this->section = Domain::insert('sections', ['class_id' => $class, 'academic_session_id' => $this->session, 'name' => 'Fictional A']);
        $this->otherSection = Domain::insert('sections', ['class_id' => $class, 'academic_session_id' => $this->session, 'name' => 'Fictional B']);
        $this->subject = Domain::insert('subjects', ['code' => 'fictional-en', 'name' => 'Fictional English']);
        Domain::insert('class_subject_assignments', ['class_id' => $class, 'subject_id' => $this->subject, 'academic_session_id' => $this->session]);
    }

    protected function user(string $role): User
    {
        $id = Str::uuid();
        $u = User::create(['name' => 'Fictional '.ucfirst($role), 'email' => $id.'@example.test', 'password' => 'fictional-password-only']);
        $u->roles()->attach(Role::where('code', $role)->firstOrFail());

        return $u;
    }

    protected function asUser(User $user): static
    {
        $token = app(SessionIssuer::class)->issue($user, 'test')['token'];

        return $this->withToken($token);
    }

    protected function admission(?string $section = null, int $roll = 1): array
    {
        return ['full_name' => 'Fictional Test Student', 'admitted_on' => '2026-10-01', 'date_of_birth' => '2020-01-01', 'gender' => 'male', 'section_id' => $section ?? $this->section, 'roll_number' => $roll];
    }

    protected function student(?string $section = null, int $roll = 1): string
    {
        return app(StudentService::class)->admit($this->admin, $this->admission($section, $roll));
    }

    protected function teacher(?string $section = null): User
    {
        $u = $this->user('teacher');
        $p = Domain::insert('staff_profiles', ['user_id' => $u->id, 'employee_number' => 'TEST-'.Str::uuid(), 'joined_on' => '2026-01-01', 'designation' => 'Test Teacher']);
        Domain::insert('teacher_assignments', ['staff_profile_id' => $p, 'section_id' => $section ?? $this->section, 'subject_id' => $this->subject, 'is_class_teacher' => true]);

        return $u;
    }

    protected function coordinator(?string $section = null): User
    {
        $u = $this->user('coordinator');
        $p = Domain::insert('staff_profiles', ['user_id' => $u->id, 'employee_number' => 'CO-'.Str::uuid(), 'joined_on' => '2026-01-01', 'designation' => 'Fictional coordinator']);
        Domain::insert('coordinator_assignments', ['staff_profile_id' => $p, 'section_id' => $section ?? $this->section]);

        return $u;
    }

    protected function guardian(string $student, string $cnic = '0000000000001'): array
    {
        $this->asUser($this->admin);
        $res = $this->postJson('/api/v1/guardians', ['full_name' => 'Fictional Guardian', 'cnic' => $cnic, 'mobile' => '+923000000001', 'mobile_verified' => true])->assertCreated();
        $id = $res->json('data.id');
        $this->putJson('/api/v1/guardians/'.$id.'/links', ['student_id' => $student, 'relationship' => 'guardian', 'is_primary' => true])->assertOk();

        return [$id, User::findOrFail(DB::table('parent_guardians')->where('id', $id)->value('user_id'))];
    }
}

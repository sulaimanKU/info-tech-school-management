<?php

namespace Tests\Support;

use App\Http\Controllers\GuardianController;
use App\Models\Role;
use App\Models\User;
use App\Services\StudentService;
use App\Support\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MobileValidationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'infotech_school_test') {
            throw new \RuntimeException('Isolated local validation database only.');
        }
        if (User::where('email', 'md@fictional.example.test')->exists()) {
            return;
        }
        DB::transaction(function () {
            $users = [];
            foreach (['md' => 'super_admin', 'administrator' => 'school_administrator', 'teacher' => 'teacher', 'coordinator' => 'coordinator', 'accountant' => 'accountant_clerk'] as $label => $role) {
                $u = User::create(['name' => 'Fictional '.ucfirst($label), 'email' => $label.'@fictional.example.test', 'password' => 'Fictional-validation-only-2026']);
                $u->roles()->attach(Role::where('code', $role)->firstOrFail());
                $users[$label] = $u;
            }
            $session = DB::table('academic_sessions')->where('name', '2026-27')->value('id');
            $a = Domain::insert('sections', ['name' => 'Fictional A', 'class_id' => DB::table('classes')->where('name', 'Grade 1')->value('id'), 'academic_session_id' => $session]);
            $b = Domain::insert('sections', ['name' => 'Fictional B', 'class_id' => DB::table('classes')->where('name', 'Grade 2')->value('id'), 'academic_session_id' => $session]);
            $subject = Domain::insert('subjects', ['code' => 'fictional-english', 'name' => 'Fictional English']);
            foreach ([$a, $b] as $section) {
                $class = DB::table('sections')->where('id', $section)->value('class_id');
                Domain::insert('class_subject_assignments', ['class_id' => $class, 'subject_id' => $subject, 'academic_session_id' => $session]);
            }
            $profile = Domain::insert('staff_profiles', ['user_id' => $users['teacher']->id, 'employee_number' => 'FICTIONAL-T-001', 'joined_on' => '2026-04-01', 'designation' => 'Fictional Teacher']);
            Domain::insert('teacher_assignments', ['staff_profile_id' => $profile, 'section_id' => $a, 'subject_id' => $subject, 'is_class_teacher' => true]);
            $cp = Domain::insert('staff_profiles', ['user_id' => $users['coordinator']->id, 'employee_number' => 'FICTIONAL-C-001', 'joined_on' => '2026-04-01', 'designation' => 'Fictional Coordinator']);
            Domain::insert('coordinator_assignments', ['staff_profile_id' => $cp, 'section_id' => $a]);
            $students = [];
            foreach ([['Fictional Child Alpha', $a, 1], ['Fictional Child Beta', $a, 2], ['Fictional Unrelated Child', $b, 1]] as [$name,$section,$roll]) {
                $students[] = app(StudentService::class)->admit($users['md'], ['full_name' => $name, 'admitted_on' => '2026-10-01', 'date_of_birth' => '2020-01-01', 'gender' => 'male', 'section_id' => $section, 'roll_number' => $roll]);
            }
            $request = Request::create('/fixture', 'POST', ['full_name' => 'Fictional Validation Guardian', 'cnic' => '0000000000001', 'mobile' => '+923000000001', 'mobile_verified' => true]);
            $request->setUserResolver(fn () => $users['md']);
            $guardians = app(GuardianController::class);
            $g = $guardians->store($request)->getData(true)['data']['id'];
            foreach (array_slice($students, 0, 2) as $student) {
                $request = Request::create('/fixture', 'PUT', ['student_id' => $student, 'relationship' => 'guardian', 'is_primary' => true]);
                $request->setUserResolver(fn () => $users['md']);
                $guardians->link($request, $g);
            }
            foreach ([[0, 'F'], [50, 'C'], [70, 'B'], [85, 'A']] as [$minimum,$grade]) {
                Domain::insert('grading_rules', ['academic_session_id' => $session, 'minimum_percent' => $minimum, 'grade' => $grade]);
            }
        });
        $this->call(ParentValidationSeeder::class);
    }
}

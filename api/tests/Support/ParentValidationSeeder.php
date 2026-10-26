<?php

namespace Tests\Support;

use App\Models\User;
use App\Support\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ParentValidationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'infotech_school_test') {
            throw new \RuntimeException('Isolated fictional validation database only.');
        }
        $admin = User::where('email', 'md@fictional.example.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@fictional.example.test')->firstOrFail();
        $subject = DB::table('subjects')->where('code', 'fictional-english')->value('id');
        $students = DB::table('students')->whereIn('full_name', ['Fictional Child Alpha', 'Fictional Child Beta'])->orderBy('full_name')->get();
        foreach ($students as $index => $student) {
            $e = DB::table('enrollments')->where('student_id', $student->id)->first();
            foreach ([['2026-10-01', 'Fictional September', 60], ['2026-10-10', 'Fictional October', 80]] as [$date,$title,$marks]) {
                $id = Domain::insert('assessments', ['academic_session_id' => $e->academic_session_id, 'section_id' => $e->section_id, 'subject_id' => $subject, 'teacher_user_id' => $teacher->id, 'type' => 'monthly_test', 'title' => $title, 'date' => $date, 'maximum_marks' => 100, 'status' => 'published', 'published_at' => now()]);
                // Existing assessment model stores a subject test per class. Separate dated titles keep child fixtures explicit.
                Domain::insert('assessment_results', ['assessment_id' => $id, 'student_id' => $student->id, 'obtained_marks' => $marks - $index * 20, 'maximum_marks' => 100, 'percentage' => $marks - $index * 20, 'grade' => $index ? 'C' : 'B', 'remarks' => 'Fictional encouraging remark']);
            }
            Domain::insert('fee_invoices', ['student_id' => $student->id, 'academic_session_id' => $e->academic_session_id, 'voucher_number' => 'FICTIONAL-PARENT-'.($index + 1), 'period' => '2026-10', 'kind' => 'additional', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-30', 'gross_minor' => ($index + 1) * 123400, 'discount_minor' => 0, 'total_minor' => ($index + 1) * 123400, 'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('b', 64), 'created_by' => $admin->id]);
            $id = Domain::insert('diary_entries', ['section_id' => $e->section_id, 'subject_id' => $subject, 'teacher_user_id' => $teacher->id, 'date' => '2026-10-01', 'topic' => $index ? 'Fictional Beta homework' : 'Fictional Alpha homework', 'homework' => 'Read the fictional worksheet.', 'whole_class' => false, 'status' => 'published', 'version' => 1, 'client_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('b', 64)]);
            DB::table('diary_students')->insert(['diary_entry_id' => $id, 'student_id' => $student->id]);
            Domain::insert('behavior_observations', ['student_id' => $student->id, 'created_by' => $teacher->id, 'date' => '2026-10-01', 'area' => 'participation', 'category' => 'improving', 'observation' => $index ? 'Fictional Beta encouragement' : 'Fictional Alpha encouragement', 'parent_visible' => true, 'status' => 'published', 'published_by' => $admin->id]);
        }
        $section = DB::table('enrollments')->where('student_id', $students->first()->id)->value('section_id');
        $register = Domain::insert('attendance_registers', ['section_id' => $section, 'date' => '2026-10-01', 'status' => 'submitted', 'version' => 1, 'last_request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('b', 64), 'created_by' => $teacher->id, 'updated_by' => $teacher->id]);
        foreach ($students as $i => $student) {
            Domain::insert('attendance_records', ['attendance_register_id' => $register, 'student_id' => $student->id, 'date' => '2026-10-01', 'status' => $i ? 'absent' : 'present']);
        }
    }
}

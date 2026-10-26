<?php

namespace App\Services;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StudentService
{
    public function admit(User $u, array $v): string
    {
        return DB::transaction(function () use ($u, $v) {
            if ($v['date_of_birth'] > $v['admitted_on']) {
                Domain::fail('date_of_birth', 'Birth date must not follow admission date.');
            }
            $year = (int) date('Y', strtotime($v['admitted_on']));
            DB::table('registration_sequences')->insertOrIgnore(['year' => $year, 'next_number' => 1]);
            $seq = DB::table('registration_sequences')->where('year', $year)->lockForUpdate()->first();
            DB::table('registration_sequences')->where('year', $year)->increment('next_number');
            $id = Domain::insert('students', ['registration_number' => sprintf('INF-SG-%02d-%04d', $year % 100, $seq->next_number), ...array_intersect_key($v, array_flip(['full_name', 'admitted_on', 'date_of_birth', 'gender', 'father_name', 'address', 'previous_school', 'emergency_contact', 'fee_category_id'])), 'status' => 'active', 'created_by' => $u->id, 'updated_by' => $u->id]);
            $enrollment = $this->enroll($u, $id, $v);
            DB::table('student_transitions')->insert(['id' => Str::uuid(), 'student_id' => $id, 'type' => 'admission', 'to_enrollment_id' => $enrollment, 'effective_on' => $v['admitted_on'], 'created_by' => $u->id, 'created_at' => now()]);
            Audit::record('student_admitted', $u->id, 'students', $id);

            return $id;
        });
    }

    public function enroll(User $u, string $student, array $v): string
    {
        $section = DB::table('sections')->where('id', $v['section_id'])->lockForUpdate()->firstOrFail();
        $session = DB::table('academic_sessions')->where('id', $section->academic_session_id)->firstOrFail();
        $date = $v['effective_on'] ?? $v['admitted_on'];
        if ($date < $session->starts_on || $date > $session->ends_on) {
            Domain::fail('effective_on', 'Date must fall within the academic session.');
        }
        if (DB::table('enrollments')->where('student_id', $student)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date))->exists()) {
            Domain::fail('effective_on', 'Enrollment dates overlap existing history.');
        }
        if (DB::table('enrollments')->where('student_id', $student)->where('status', 'active')->exists()) {
            Domain::fail('student', 'An active enrollment already exists.');
        }
        if (DB::table('enrollments')->where('section_id', $section->id)->where('roll_number', $v['roll_number'])->where('status', 'active')->exists()) {
            Domain::fail('roll_number', 'Roll number is already assigned.');
        }

        return Domain::insert('enrollments', ['student_id' => $student, 'section_id' => $section->id, 'academic_session_id' => $section->academic_session_id, 'roll_number' => $v['roll_number'], 'starts_on' => $date, 'status' => 'active', 'created_by' => $u->id]);
    }

    public function transition(User $u, string $id, array $v): void
    {
        DB::transaction(function () use ($u, $id, $v) {
            $student = DB::table('students')->where('id', $id)->lockForUpdate()->firstOrFail();
            $old = DB::table('enrollments')->where('student_id', $id)->where('status', 'active')->lockForUpdate()->first();
            $type = $v['type'];
            if ($type === 'readmission' && $student->status === 'active') {
                Domain::fail('type', 'Student is already active.');
            }
            if ($type !== 'readmission' && ! $old) {
                Domain::fail('type', 'An active enrollment is required.');
            }
            if ($old && $v['effective_on'] <= $old->starts_on) {
                Domain::fail('effective_on', 'Transition date must follow the enrollment start date.');
            }
            if ($old) {
                DB::table('enrollments')->where('id', $old->id)->update(['status' => $type === 'withdrawal' ? 'withdrawn' : ($type === 'transfer' ? 'transferred' : 'completed'), 'ends_on' => max($old->starts_on, Carbon::parse($v['effective_on'])->subDay()->toDateString()), 'updated_at' => now()]);
            }
            $new = null;
            if (in_array($type, ['promotion', 'readmission', 'section_change'])) {
                if ($type === 'promotion' && $old) {
                    $from = DB::table('sections')->join('classes', 'classes.id', '=', 'sections.class_id')->where('sections.id', $old->section_id)->first(['classes.sort_order']);
                    $to = DB::table('sections')->join('classes', 'classes.id', '=', 'sections.class_id')->where('sections.id', $v['section_id'])->first(['classes.sort_order', 'sections.academic_session_id']);
                    if (! $to || $to->sort_order <= $from->sort_order || $to->academic_session_id === $old->academic_session_id) {
                        Domain::fail('section_id', 'Promotion requires a higher class in a new session.');
                    }
                }
                $new = $this->enroll($u, $id, $v);
            }
            DB::table('students')->where('id', $id)->update(['status' => $type === 'withdrawal' ? 'withdrawn' : ($type === 'transfer' ? 'transferred' : 'active'), 'updated_by' => $u->id, 'updated_at' => now()]);
            DB::table('student_transitions')->insert(['id' => Str::uuid(), 'student_id' => $id, 'type' => $type, 'from_enrollment_id' => $old?->id, 'to_enrollment_id' => $new, 'effective_on' => $v['effective_on'], 'reason' => $v['reason'] ?? null, 'created_by' => $u->id, 'created_at' => now()]);
            Audit::record('student_'.$type, $u->id, 'students', $id);
        });
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\AcademicFilters;
use App\Services\Finance\AdmissionFinance;
use App\Services\Finance\FinancialLock;
use App\Services\ProfilePhotoService;
use App\Services\SchoolAccess;
use App\Services\StudentService;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function __construct(private SchoolAccess $access, private StudentService $service) {}

    public function index(Request $r)
    {
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r, ['status' => 'nullable|in:active,withdrawn,transferred', 'enrollment_status' => 'nullable|in:active,completed,withdrawn,transferred']);
        $q = $filters->students($this->access->students($r->user()), $r, $v);

        return Domain::response($q->orderBy('full_name')->paginate(30, ['id', 'full_name', 'registration_number', 'status', 'photo_file_id']));
    }

    public function show(Request $r, string $student)
    {
        $s = $this->access->student($r->user(), $student);
        // Teachers/parents see the educational profile, not sensitive admission address/contact fields.
        $data = array_intersect_key((array) $s, array_flip(['id', 'full_name', 'registration_number', 'gender', 'date_of_birth', 'admitted_on', 'status', 'photo_file_id']));
        if ($r->user()->hasPermission('students.manage')) {
            $data = (array) $s;
        }
        if (! $this->access->isParent($r->user()) && ! $r->user()->hasPermission('academics.read') && ! $r->user()->hasPermission('academics.write') && ! $r->user()->hasPermission('students.manage')) {
            unset($data['date_of_birth'],$data['gender']);
        }
        $data['enrollments'] = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 's.class_id')->join('academic_sessions as a', 'a.id', '=', 'e.academic_session_id')->where('e.student_id', $student)->orderByDesc('e.starts_on')->get(['e.*', 's.name as section_name', 'c.name as class_name', 'a.name as session_name']);

        return Domain::response($data);
    }

    public function store(Request $r)
    {
        $this->access->require($r->user(), 'students.manage');
        $v = $r->validate($this->rules() + AdmissionFinance::rules() + ['section_id' => 'required|uuid|exists:sections,id', 'roll_number' => 'required|integer|min:1']);
        $this->access->section($r->user(), $v['section_id']);
        $photo = $r->file('photo');
        if ($photo) {
            app(ProfilePhotoService::class)->validate($photo);
        }
        if (isset($v['admission_finance']) && ! isset($v['request_id'])) {
            Domain::fail('request_id', 'A stable admission request ID is required for finance setup.');
        }
        $id = DB::transaction(function () use ($r, $v, $photo) {
            $hash = hash('sha256', json_encode([$v, $photo ? hash_file('sha256', $photo->getRealPath()) : null]));
            if (isset($v['request_id'])) {
                app(FinancialLock::class)->request('admission', $v['request_id']);
                $old = DB::table('admission_requests')->where('id', $v['request_id'])->first();
                if ($old) {
                    abort_unless($old->user_id === $r->user()->id && hash_equals($old->payload_hash, $hash), 409);

                    return $old->student_id;
                }
            }
            $id = $this->service->admit($r->user(), $v);
            app(AdmissionFinance::class)->apply($r->user(), $id, $v['admission_finance'] ?? []);
            if (isset($v['request_id'])) {
                DB::table('admission_requests')->insert(['id' => $v['request_id'], 'user_id' => $r->user()->id, 'student_id' => $id, 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($photo) {
                app(ProfilePhotoService::class)->save($r->user(), $photo, $id);
            }

            return $id;
        });

        return Domain::response(['id' => $id, 'registration_number' => DB::table('students')->find($id)->registration_number], 201);
    }

    public function update(Request $r, string $student)
    {
        $this->access->require($r->user(), 'students.manage');
        $existing = $this->access->student($r->user(), $student);
        $rules = array_map(fn ($s) => str_replace('required', 'sometimes', $s), $this->rules());
        unset($rules['admitted_on']);
        $v = $r->validate($rules);
        if (isset($v['date_of_birth']) && $v['date_of_birth'] > $existing->admitted_on) {
            Domain::fail('date_of_birth', 'Birth date must not follow admission date.');
        }
        DB::transaction(function () use ($r, $student, $v) {
            DB::table('students')->where('id', $student)->update([...$v, 'updated_by' => $r->user()->id, 'updated_at' => now()]);
            Audit::record('student_updated', $r->user()->id, 'students', $student, ['changed_fields' => array_keys($v)]);
        });

        return $this->show($r, $student);
    }

    public function transition(Request $r, string $student)
    {
        $this->access->require($r->user(), 'students.manage');
        $v = $r->validate(['type' => 'required|in:withdrawal,transfer,readmission,promotion,section_change', 'effective_on' => 'required|date', 'section_id' => 'required_if:type,readmission,promotion,section_change|uuid|exists:sections,id', 'roll_number' => 'required_if:type,readmission,promotion,section_change|integer|min:1', 'reason' => 'nullable|string|max:2000']);
        if (isset($v['section_id'])) {
            $this->access->section($r->user(), $v['section_id']);
        }
        $this->service->transition($r->user(), $student, $v);

        return $this->show($r, $student);
    }

    private function rules(): array
    {
        return ['full_name' => 'required|string|max:200', 'admitted_on' => 'required|date|before_or_equal:today', 'gender' => 'required|in:male,female,other', 'date_of_birth' => 'required|date|before_or_equal:today', 'father_name' => 'nullable|string|max:200', 'address' => 'nullable|string|max:2000', 'previous_school' => 'nullable|string|max:200', 'emergency_contact' => 'nullable|regex:/^\+?[0-9 -]{7,25}$/', 'fee_category_id' => 'nullable|uuid|exists:fee_categories,id'];
    }
}

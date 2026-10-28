<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\GuardianIdentity;
use App\Services\ProfilePhotoService;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardianController extends Controller
{
    public function __construct(private SchoolAccess $access, private GuardianIdentity $identity) {}

    public function index(Request $r)
    {
        $this->access->require($r->user(), 'guardians.manage');

        $v = $r->validate(['search' => 'nullable|string|max:100', 'student_id' => 'nullable|uuid|exists:students,id', 'page' => 'nullable|integer|min:1']);
        $q = $this->query($r);
        $status = $r->validate(['status' => 'nullable|in:active,inactive']);
        if (isset($status['status'])) {
            $q->where('u.is_active', $status['status'] === 'active');
        }
        if (! empty($v['search'])) {
            $search = preg_replace('/[\s-]/', '', $v['search']);
            $q->where(function ($q) use ($v, $search) {
                $q->where('g.full_name', 'like', '%'.$v['search'].'%');
                if (preg_match('/^\d{13}$/', $search)) {
                    $q->orWhere('g.cnic', $this->identity->normalize($search));
                }
            });
        }
        if (! empty($v['student_id'])) {
            $this->access->student($r->user(), $v['student_id']);
            $q->whereIn('g.id', DB::table('parent_student_links')->where('student_id', $v['student_id'])->select('parent_guardian_id'));
        }

        return Domain::response($q->orderBy('g.full_name')->paginate(30, ['g.*', 'u.is_active'])->through(fn ($g) => $this->safe($g, $r->user())));
    }

    private function query(Request $r)
    {
        $q = DB::table('parent_guardians as g')->join('users as u', 'u.id', '=', 'g.user_id');
        if (! $r->user()->hasPermission('students.read.all')) {
            $q->where(fn ($q) => $q->where('g.verified_by', $r->user()->id)->orWhereIn('g.id', DB::table('parent_student_links')->whereIn('student_id', $this->access->students($r->user())->select('students.id'))->select('parent_guardian_id')));
        }

        return $q;
    }

    private function safe(object $g, User $actor): array
    {
        return ['photo_file_id' => $g->photo_file_id, 'id' => $g->id, 'full_name' => $g->full_name, 'cnic_masked' => $this->identity->mask($g->cnic), 'mobile_masked' => '********'.substr($g->mobile, -4), 'mobile_verified' => $g->mobile_verified_at !== null, 'is_active' => (bool) $g->is_active,
            'children' => DB::table('parent_student_links as l')->join('students as s', 's.id', '=', 'l.student_id')->where('l.parent_guardian_id', $g->id)->whereIn('l.student_id', $this->access->students($actor)->select('students.id'))->get(['l.id', 'l.student_id', 'l.relationship', 'l.is_active', 'l.is_primary', 'l.verified_at', 's.full_name', 's.registration_number'])];
    }

    public function show(Request $r, string $guardian)
    {
        $this->access->require($r->user(), 'guardians.manage');
        $g = $this->query($r)->where('g.id', $guardian)->firstOrFail(['g.*', 'u.is_active']);

        return Domain::response($this->safe($g, $r->user()));
    }

    public function update(Request $r, string $guardian)
    {
        $this->access->require($r->user(), 'guardians.manage');
        $this->query($r)->where('g.id', $guardian)->firstOrFail();
        $v = $r->validate(['is_active' => 'sometimes|boolean', 'full_name' => 'sometimes|required|string|max:200', 'mobile' => 'sometimes|required|regex:/^\+923[0-9]{9}$/', 'mobile_verified' => 'exclude_without:mobile|required|accepted']);
        DB::transaction(function () use ($r, $guardian, $v) {
            $g = DB::table('parent_guardians')->where('id', $guardian)->lockForUpdate()->firstOrFail();
            $data = [];
            if (array_key_exists('is_active', $v)) {
                User::whereKey($g->user_id)->update(['is_active' => $v['is_active']]);
                if (! $v['is_active']) {
                    DB::table('authentication_sessions')->where('user_id', $g->user_id)->update(['revoked_at' => now(), 'updated_at' => now()]);
                    DB::table('push_devices')->where('user_id', $g->user_id)->update(['revoked_at' => now(), 'updated_at' => now()]);
                }
            }
            if (isset($v['full_name'])) {
                $data['full_name'] = $v['full_name'];
                User::whereKey($g->user_id)->update(['name' => $v['full_name']]);
            }
            if (isset($v['mobile'])) {
                $data += ['mobile' => $v['mobile'], 'mobile_verified_at' => now(), 'verified_by' => $r->user()->id];
                DB::table('authentication_sessions')->where('user_id', $g->user_id)->update(['revoked_at' => now(), 'updated_at' => now()]);
                DB::table('otp_challenges')->where('parent_guardian_id', $guardian)->whereNull('consumed_at')->update(['consumed_at' => now()]);
                DB::table('push_devices')->where('user_id', $g->user_id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            }
            DB::table('parent_guardians')->where('id', $guardian)->update([...$data, 'updated_at' => now()]);
            Audit::record('guardian_contact_updated', $r->user()->id, 'parent_guardians', $guardian, ['changed_fields' => array_keys($v)]);
        });

        return Domain::response(['saved' => true]);
    }

    public function unlink(Request $r, string $guardian, string $student)
    {
        $this->access->require($r->user(), 'guardians.manage');
        $this->access->student($r->user(), $student);
        DB::transaction(function () use ($r, $guardian, $student) {
            $row = DB::table('parent_student_links')->where('parent_guardian_id', $guardian)->where('student_id', $student)->lockForUpdate()->firstOrFail();
            DB::table('parent_student_links')->where('id', $row->id)->update(['is_active' => false, 'is_primary' => false, 'updated_at' => now()]);
            Audit::record('guardian_unlinked', $r->user()->id, 'students', $student, ['guardian_id' => $guardian]);
        });

        return Domain::response(['saved' => true]);
    }

    public function store(Request $r)
    {
        $this->access->require($r->user(), 'guardians.manage');
        $v = $r->validate(['full_name' => 'required|string|max:200', 'cnic' => 'required|string|max:20', 'mobile' => 'required|regex:/^\+923[0-9]{9}$/', 'mobile_verified' => 'required|accepted']);
        $cnic = $this->identity->normalize($v['cnic']);
        $photo = $r->file('photo');
        if ($photo) {
            app(ProfilePhotoService::class)->validate($photo);
        }
        $id = DB::transaction(function () use ($r, $v, $cnic, $photo) {
            if (DB::table('parent_guardians')->where('cnic', $cnic)->exists()) {
                Domain::fail('cnic', 'A guardian with this identity is already registered.');
            }
            $user = User::create(['name' => $v['full_name'], 'email' => 'guardian-'.Str::uuid().'@internal.invalid', 'password' => bin2hex(random_bytes(32))]);
            $user->roles()->attach(Role::where('code', 'parent_guardian')->firstOrFail());
            $id = Domain::insert('parent_guardians', ['user_id' => $user->id, 'full_name' => $v['full_name'], 'cnic' => $cnic, 'mobile' => $v['mobile'], 'mobile_verified_at' => now(), 'verified_by' => $r->user()->id]);
            if ($photo) {
                app(ProfilePhotoService::class)->save($r->user(), $photo, $id, true);
            }
            Audit::record('guardian_verified', $r->user()->id, 'parent_guardians', $id);

            return $id;
        });

        return Domain::response(['id' => $id, 'full_name' => $v['full_name'], 'cnic_masked' => $this->identity->mask(substr($cnic, -4))], 201);
    }

    public function link(Request $r, string $guardian)
    {
        $this->access->require($r->user(), 'guardians.manage');
        $this->query($r)->where('g.id', $guardian)->firstOrFail();
        $v = $r->validate(['student_id' => 'required|uuid|exists:students,id', 'relationship' => 'required|in:father,mother,guardian,other', 'is_primary' => 'required|boolean', 'is_active' => 'sometimes|boolean']);
        $this->access->student($r->user(), $v['student_id']);
        DB::transaction(function () use ($r, $guardian, $v) {
            DB::table('students')->where('id', $v['student_id'])->lockForUpdate()->firstOrFail();
            if ($v['is_primary']) {
                DB::table('parent_student_links')->where('student_id', $v['student_id'])->update(['is_primary' => false, 'updated_at' => now()]);
            }
            $existing = DB::table('parent_student_links')->where('parent_guardian_id', $guardian)->where('student_id', $v['student_id'])->first();
            $data = [...$v, 'parent_guardian_id' => $guardian, 'verified_by' => $r->user()->id, 'verified_at' => now(), 'updated_at' => now()];
            if ($existing) {
                DB::table('parent_student_links')->where('id', $existing->id)->update($data);
            } else {
                Domain::insert('parent_student_links', $data);
            }
            Audit::record('guardian_link_verified', $r->user()->id, 'students', $v['student_id']);
        });

        return Domain::response(['linked' => true]);
    }
}

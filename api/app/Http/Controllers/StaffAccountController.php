<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProfilePhotoService;
use App\Services\ReportData;
use App\Services\ReportRenderer;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffAccountController extends Controller
{
    public static function authorize($user): void
    {
        abort_unless($user->isStaff(), 403);
    }

    private function input(Request $r, array $allowed): void
    {
        self::authorize($r->user());
        if (array_diff(array_keys($r->all()), $allowed)) {
            Domain::fail('profile', 'Unsupported account field.');
        }
    }

    public function show(Request $r)
    {
        $this->input($r, []);
        $u = $r->user()->fresh();
        $profile = DB::table('staff_profiles')->where('user_id', $u->id)->first(['id', 'employee_number', 'designation', 'joined_on', 'is_active']);

        return Domain::response(['account' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'mobile' => $u->mobile, 'address' => $u->address, 'photo_file_id' => $u->photo_file_id, 'is_active' => $u->is_active, 'roles' => $u->roles()->get(['code', 'name'])], 'profile' => $profile, 'staff_linked' => $profile !== null])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $r)
    {
        $this->input($r, ['name', 'mobile', 'address']);
        $v = $r->validate(['name' => 'sometimes|required|string|max:200', 'mobile' => ['nullable', 'string', 'max:30', 'regex:/^\+?(?:[ ()-]*[0-9]){7,15}[ ()-]*$/'], 'address' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($r, $v) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->firstOrFail();
            DB::table('users')->where('id', $r->user()->id)->update([...$v, 'updated_at' => now()]);
            Audit::record('staff_self_profile_updated', $r->user()->id, 'users', $r->user()->id, ['fields' => array_keys($v)]);
        });

        // GET presentation validates no query/body fields; do not mutate request context.
        return Domain::response(['saved' => true]);
    }

    public function password(Request $r)
    {
        $this->input($r, ['current_password', 'password', 'password_confirmation']);
        $v = $r->validate(['current_password' => 'required|string|max:1024', 'password' => 'required|string|min:12|max:1024|confirmed|different:current_password', 'password_confirmation' => 'required|string|max:1024']);
        DB::transaction(function () use ($r, $v) {
            $u = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($v['current_password'], $u->password)) {
                Domain::fail('current_password', 'The current password is incorrect.');
            }
            $u->password = $v['password'];
            $u->save();
            DB::table('authentication_sessions')->where('user_id', $u->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
            DB::table('push_devices')->where('user_id', $u->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            Audit::record('staff_password_changed', $u->id, 'users', $u->id);
        });

        return Domain::response(['saved' => true, 'reauthenticate' => true]);
    }

    public function photo(Request $r, ProfilePhotoService $photos)
    {
        $this->input($r, ['file']);
        $r->validate(['file' => 'required|file']);

        return Domain::response($photos->save($r->user(), $r->file('file'), $r->user()->id, false, true), 201);
    }

    public function removePhoto(Request $r)
    {
        $this->input($r, []);
        DB::transaction(function () use ($r) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->firstOrFail();
            DB::table('users')->where('id', $r->user()->id)->update(['photo_file_id' => null, 'updated_at' => now()]);
            Audit::record('staff_photo_removed', $r->user()->id, 'users', $r->user()->id);
        });

        return Domain::response(['saved' => true]);
    }

    public function salary(Request $r)
    {
        $this->input($r, []);
        $profile = DB::table('staff_profiles')->where('user_id', $r->user()->id)->first();
        $items = $profile ? DB::table('payroll_items as i')->join('payroll_periods as p', 'p.id', '=', 'i.payroll_period_id')->where('i.staff_profile_id', $profile->id)->where('p.status', 'paid')->orderByDesc('p.period')->get(['i.id', 'i.base_salary_minor', 'i.adjustment_minor', 'i.net_salary_minor', 'p.period', 'p.paid_on', 'p.status']) : collect();

        return Domain::response(['profile' => $profile, 'staff_linked' => $profile !== null, 'items' => $items])->header('Cache-Control', 'private, no-store');
    }

    public function salaryDocument(Request $r, string $item)
    {
        $this->input($r, []);
        DB::table('payroll_items as i')->join('staff_profiles as s', 's.id', '=', 'i.staff_profile_id')->join('payroll_periods as p', 'p.id', '=', 'i.payroll_period_id')->where('i.id', $item)->where('s.user_id', $r->user()->id)->where('p.status', 'paid')->firstOrFail();
        $r->merge(['payroll_item_id' => $item]);

        return app(ReportController::class)->show($r, 'salary_slip', app(ReportData::class), app(ReportRenderer::class));
    }
}

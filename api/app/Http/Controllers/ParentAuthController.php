<?php

namespace App\Http\Controllers;

use App\Contracts\SmsProvider;
use App\Models\User;
use App\Services\GuardianIdentity;
use App\Services\SessionIssuer;
use App\Services\Sms\DisabledSmsProvider;
use App\Support\Audit;
use App\Support\Domain;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ParentAuthController extends Controller
{
    public function requestOtp(Request $r, GuardianIdentity $identity, SmsProvider $sms)
    {
        abort_if($sms instanceof DisabledSmsProvider, 503);
        $v = $r->validate(['cnic' => 'required|string|max:20']);
        $cnic = $identity->normalize($v['cnic']);
        $lookup = $identity->lookup($cnic);

        return DB::transaction(function () use ($lookup, $cnic, $sms) {
            app(\App\Services\Finance\FinancialLock::class)->request('parent-otp', $lookup);
            $last = DB::table('otp_challenges')->where('lookup_hash', $lookup)->orderByDesc('created_at')->first();
            if ($last && now()->diffInSeconds(Carbon::parse($last->created_at), true) < config('school.otp_cooldown_seconds')) {
                abort(429);
            }
            DB::table('otp_challenges')->where('lookup_hash', $lookup)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $guardian = DB::table('parent_guardians as g')->join('users as u', 'u.id', '=', 'g.user_id')->where('g.cnic', $cnic)->whereNotNull('g.mobile_verified_at')->where('u.is_active', true)->first(['g.*']);
            $otp = (string) random_int(100000, 999999);
            $id = Domain::insert('otp_challenges', ['parent_guardian_id' => $guardian?->id, 'lookup_hash' => $lookup, 'otp_hash' => Hash::make($otp), 'expires_at' => now()->addSeconds(config('school.otp_lifetime_seconds'))]);
            if ($guardian) {
                $sms->sendOtp($id, $guardian->mobile, $otp);
            }
            Audit::record('parent_otp_requested');

            return Domain::response(['challenge_id' => $id, 'expires_in' => config('school.otp_lifetime_seconds'), 'resend_after' => config('school.otp_cooldown_seconds'), 'message' => 'If the account is eligible, a code will be sent to its verified mobile.'], 202);
        });
    }

    public function verify(Request $r, SessionIssuer $issuer)
    {
        $v = $r->validate(['challenge_id' => 'required|uuid', 'otp' => 'required|digits:6']);
        // Return errors outside the transaction, so failed attempt counters are committed.
        $result = DB::transaction(function () use ($v, $issuer) {
            $challenge = DB::table('otp_challenges')->where('id', $v['challenge_id'])->lockForUpdate()->first();
            if (! $challenge || $challenge->consumed_at || now()->gte($challenge->expires_at) || $challenge->attempts >= config('school.otp_attempts')) {
                Audit::record('parent_otp_failed');

                return null;
            }
            DB::table('otp_challenges')->where('id', $challenge->id)->increment('attempts');
            if (! Hash::check($v['otp'], $challenge->otp_hash) || ! $challenge->parent_guardian_id) {
                Audit::record('parent_otp_failed');

                return null;
            }
            $guardian = DB::table('parent_guardians')->where('id', $challenge->parent_guardian_id)->whereNotNull('mobile_verified_at')->first();
            $user = $guardian ? User::whereKey($guardian->user_id)->lockForUpdate()->first() : null;
            if (! $user?->is_active) {
                Audit::record('parent_otp_failed');

                return null;
            }
            DB::table('otp_challenges')->where('id', $challenge->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            $data = $issuer->issue($user, 'parent_sms_otp');
            Audit::record('parent_login_succeeded', $user->id);

            return $data;
        });
        abort_unless($result, 401);

        return Domain::response($result);
    }
}

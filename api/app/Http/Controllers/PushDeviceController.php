<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PushDeviceController extends Controller
{
    public function register(Request $r)
    {
        $v = $r->validate(['token' => 'required|string|min:20|max:4096|regex:/^[A-Za-z0-9_:\-]+$/', 'platform' => 'required|in:android']);
        $hash = hash('sha256', $v['token']);
        $id = DB::transaction(function () use ($r, $v, $hash) {
            app(\App\Services\Finance\FinancialLock::class)->request('push', $hash);
            $old = DB::table('push_devices')->where('token_hash', $hash)->lockForUpdate()->first();
            $values = ['user_id' => $r->user()->id, 'authentication_session_id' => $r->attributes->get('session_id'), 'token_ciphertext' => Crypt::encryptString($v['token']), 'platform' => 'android', 'revoked_at' => null];
            if ($old) {
                $id = $old->id;
                DB::table('push_devices')->where('id', $id)->update([...$values, 'updated_at' => now()]);
            } else {
                $id = Domain::insert('push_devices', [...$values, 'token_hash' => $hash]);
            }Audit::record('push_device_registered', $r->user()->id, 'push_devices', $id);

            return $id;
        });

        return Domain::response(['id' => $id, 'push_configured' => config('school.push_driver') === 'fcm']);
    }

    public function revoke(Request $r, string $device)
    {
        $q = DB::table('push_devices')->where('id', $device)->where('user_id', $r->user()->id)->where('authentication_session_id', $r->attributes->get('session_id'));
        $q->firstOrFail();
        $q->update(['revoked_at' => now(), 'updated_at' => now()]);
        Audit::record('push_device_revoked', $r->user()->id, 'push_devices', $device);

        return Domain::response(['revoked' => true]);
    }
}

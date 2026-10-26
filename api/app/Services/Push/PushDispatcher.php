<?php

namespace App\Services\Push;

use App\Contracts\PushProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PushDispatcher
{
    public function __construct(private PushProvider $provider) {}

    private function active()
    {
        return DB::table('push_devices as d')->join('authentication_sessions as s', 's.id', '=', 'd.authentication_session_id')->join('users as u', 'u.id', '=', 'd.user_id')->whereNull('d.revoked_at')->whereNull('s.revoked_at')->where('s.expires_at', '>', now())->where('u.is_active', true);
    }

    public function dispatch(int $limit = 100): array
    {
        $counts = ['sent' => 0, 'retry' => 0, 'disabled' => 0, 'invalid_token' => 0, 'cancelled' => 0];
        // History stays available without FCM; disabled mode never claims an external delivery.
        if (config('school.push_driver') !== 'fcm') {
            $counts['disabled'] = 1;

            return $counts;
        }
        DB::table('school_notifications')->where('created_at', '>=', now()->subDays(7))->orderBy('id')->chunkById(100, function ($notes) {
            foreach ($notes as $n) {
                foreach ($this->active()->where('d.user_id', $n->user_id)->where('d.created_at', '<=', $n->created_at)->pluck('d.id') as $device) {
                    DB::table('push_deliveries')->insertOrIgnore(['id' => (string) Str::uuid(), 'school_notification_id' => $n->id, 'push_device_id' => $device, 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
        for ($i = 0; $i < $limit; $i++) {
            $delivery = DB::transaction(function () {
                $d = DB::table('push_deliveries')->whereIn('status', ['pending', 'retry', 'sending'])->where('attempts', '<', 5)->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<', now()))->orderBy('created_at')->lock('FOR UPDATE SKIP LOCKED')->first();
                if (! $d) {
                    return null;
                }DB::table('push_deliveries')->where('id', $d->id)->update(['status' => 'sending', 'attempts' => $d->attempts + 1, 'lease_until' => now()->addMinutes(2), 'updated_at' => now()]);

                return $d;
            });
            if (! $delivery) {
                break;
            }
            $device = $this->active()->where('d.id', $delivery->push_device_id)->first(['d.*']);
            $note = DB::table('school_notifications')->find($delivery->school_notification_id);
            $status = 'cancelled';
            if ($device && $note && $note->user_id === $device->user_id) {
                $status = $this->provider->send(Crypt::decryptString($device->token_ciphertext), $note->id);
            }
            if ($status === 'invalid_token') {
                DB::table('push_devices')->where('id', $device->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            }
            DB::table('push_deliveries')->where('id', $delivery->id)->update(['status' => $status === 'disabled' ? 'retry' : $status, 'failure_code' => $status === 'sent' ? null : $status, 'next_attempt_at' => in_array($status, ['retry', 'disabled']) ? now()->addMinutes(2 ** ($delivery->attempts + 1)) : null, 'lease_until' => null, 'updated_at' => now()]);
            $counts[$status]++;
        }

        return $counts;
    }
}

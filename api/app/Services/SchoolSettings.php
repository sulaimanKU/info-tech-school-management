<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Deliberately excludes provider secrets and arbitrary settings from API output. */
final class SchoolSettings
{
    public const PROFILE = ['school_name', 'campus', 'location', 'address', 'phone', 'email', 'website', 'current_academic_session_id'];

    public function information(): array
    {
        $values = DB::table('school_settings')->whereIn('key', [...self::PROFILE, 'timezone', 'currency'])
            ->get()->mapWithKeys(fn ($s) => [$s->key => json_decode($s->value, true)])->all();

        $session = DB::table('academic_sessions')->when($values['current_academic_session_id'] ?? null,
            fn ($q, $id) => $q->where('id', $id),
            fn ($q) => $q->where('starts_on', '<=', now()->toDateString())->where('ends_on', '>=', now()->toDateString()))->orderByDesc('starts_on')->first(['name']);
        $values['effective_academic_session_name'] = $session?->name;

        return $values + ['school_name' => 'INFO-TECH High School & Educational Campus', 'campus' => 'Sangao Campus', 'location' => 'Mardan, Khyber Pakhtunkhwa, Pakistan', 'address' => null, 'phone' => null, 'email' => null, 'website' => null, 'current_academic_session_id' => null, 'timezone' => 'Asia/Karachi', 'currency' => 'PKR'];
    }

    public function currentSession(): ?string
    {
        $configured = json_decode(DB::table('school_settings')->where('key', 'current_academic_session_id')->value('value') ?? 'null', true);
        if (is_string($configured) && DB::table('academic_sessions')->where('id', $configured)->exists()) {
            return $configured;
        }

        return DB::table('academic_sessions')->where('starts_on', '<=', now()->toDateString())->where('ends_on', '>=', now()->toDateString())->orderByDesc('starts_on')->value('id');
    }
}

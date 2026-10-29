<?php

namespace App\Services;

use App\Support\Domain;
use Illuminate\Support\Facades\DB;

final class GradingRules
{
    public static function rules(): array
    {
        return ['bands' => 'required|array|min:1|max:20', 'bands.*.minimum_percent' => 'required|numeric|min:0|max:100|distinct', 'bands.*.grade' => 'required|string|max:20'];
    }

    public function validate(array $bands): void
    {
        if (! collect($bands)->contains(fn ($b) => (float) $b['minimum_percent'] === 0.0)) {
            Domain::fail('bands', 'Include a zero-percent band.');
        }
    }

    /** Caller owns the transaction. Published result grades are never rewritten. */
    public function replace(string $session, array $bands): void
    {
        $this->validate($bands);
        DB::table('academic_sessions')->where('id', $session)->lockForUpdate()->firstOrFail();
        DB::table('grading_rules')->where('academic_session_id', $session)->delete();
        foreach ($bands as $band) {
            Domain::insert('grading_rules', ['academic_session_id' => $session, ...$band]);
        }
    }
}

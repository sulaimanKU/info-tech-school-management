<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;

final class EffectiveConfiguration
{
    public function save(User $user, string $table, array $key, array $values): string
    {
        return DB::transaction(function () use ($user, $table, $key, $values) {
            app(FinancialLock::class)->request($table, json_encode($key));
            $old = DB::table($table)->where($key)->lockForUpdate()->first();
            if ($old) {
                if ($table === 'staff_compensation') {
                    if ((int) $old->base_salary_minor !== $values['base_salary_minor']) {
                        Domain::fail('effective_on', 'Approved compensation is immutable. Choose a new effective date for salary changes.');
                    }

                    return $old->id;
                }
                DB::table($table)->where('id', $old->id)->update([...$values, 'updated_at' => now()]);
                $id = $old->id;
            } else {
                $id = Domain::insert($table, [...$key, ...$values]);
            }
            Audit::record('finance_configuration_saved', $user->id, $table, $id);

            return $id;
        });
    }
}

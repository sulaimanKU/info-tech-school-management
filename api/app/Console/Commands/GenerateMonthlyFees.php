<?php

namespace App\Console\Commands;

use App\Http\Controllers\FeeController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class GenerateMonthlyFees extends Command
{
    protected $signature = 'school:generate-monthly-fees {--actor= : Authorized existing staff UUID} {--student=} {--session=} {--class=} {--through=} {--current-only} {--dry-run : Preview without posting}';

    protected $description = 'Preview or post missing monthly tuition using existing vouchers and accounting services';

    public function handle(): int
    {
        $actor = User::find($this->option('actor'));
        if (! $actor) {
            $this->error('An authorized existing --actor is required.');

            return self::FAILURE;
        }
        $request = Request::create('/console', 'POST', array_filter(['student_id' => $this->option('student'), 'academic_session_id' => $this->option('session'), 'class_id' => $this->option('class'), 'through' => $this->option('through') ?: now()->format('Y-m'), 'current_only' => (bool) $this->option('current-only'), 'preview' => (bool) $this->option('dry-run')], fn ($v) => $v !== null));
        $request->setUserResolver(fn () => $actor);
        try {
            $result = app(FeeController::class)->monthly($request)->getData(true)['data'];
            $this->info('Students: '.$result['student_count'].'; missing months: '.$result['missing_months'].'; generated: '.count($result['generated_ids']));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Billing refused. Check permissions, scope, cutoff and historical fee configuration. No partial batch was committed.');

            return self::FAILURE;
        }
    }
}

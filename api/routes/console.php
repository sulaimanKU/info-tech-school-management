<?php

use App\Http\Controllers\AnnouncementController;
use App\Models\Role;
use App\Models\User;
use App\Services\Push\PushDispatcher;
use App\Services\Sms\LocalSmsProvider;
use Database\Seeders\FinanceConfigurationSeeder;
use Database\Seeders\MobilePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\MobileValidationSeeder;

Artisan::command('school:create-dev-staff', function () {
    if (! app()->environment('local')) {
        $this->error('Local environment only.');

        return 1;
    }
    $email = strtolower($this->ask('Development email (use example.test)'));
    $password = $this->secret('Password (minimum 4 characters)');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! str_ends_with($email, '@example.test') || strlen($password) < 4) {
        $this->error('Invalid development email or short password.');

        return 1;
    }
    DB::transaction(function () use ($email, $password) {
        $role = Role::where('code', 'school_administrator')->firstOrFail();
        $user = User::create(['name' => 'Development Administrator', 'email' => $email, 'password' => $password]);
        $user->roles()->attach($role);
    });
    $this->info('Development staff created.');
});

Artisan::command('school:seed-mobile', function () {
    $this->call('db:seed', ['--class' => MobilePermissionSeeder::class]);
    $this->call('db:seed', ['--class' => FinanceConfigurationSeeder::class]);
});
Artisan::command('school:local-otp {challenge?}', function () {
    if (! app()->environment('local') || config('school.sms_driver') !== 'local') {
        $this->error('Local simulator only.');

        return 1;
    }
    $challenge = $this->argument('challenge') ?: DB::table('otp_challenges')->whereNotNull('parent_guardian_id')->whereNull('consumed_at')->where('expires_at', '>', now())->orderByDesc('created_at')->value('id');
    if (! $challenge || ! Str::isUuid($challenge)) {
        return 1;
    }
    $value = (new LocalSmsProvider)->consumeForDevelopment($challenge);
    if (! $value) {
        $this->error('No unconsumed local delivery.');

        return 1;
    }
    $this->line($value);
});

Artisan::command('school:dispatch-notifications', function () {
    DB::table('announcements')->where('status', 'published')->where('publish_at', '<=', now())->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderBy('id')->chunkById(100, function ($items) {
        foreach ($items as $a) {
            app(AnnouncementController::class)->notify($a->id);
        }
    });
    $counts = app(PushDispatcher::class)->dispatch();
    $this->line(json_encode($counts));
});
Artisan::command('school:purge-expired-imports', function () {
    $count = DB::table('import_batches')->where('status', '!=', 'confirmed')->where('expires_at', '<', now())->whereNotNull('source_rows')->update(['source_rows' => null, 'status' => 'expired', 'updated_at' => now()]);
    $this->info('Expired staging batches cleared: '.$count);
});
Schedule::command('school:dispatch-notifications')->everyMinute()->withoutOverlapping();
Schedule::command('school:purge-expired-imports')->hourly()->withoutOverlapping();

Artisan::command('school:seed-validation-fixtures', function () {
    if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'infotech_school_test') {
        $this->error('Isolated local validation database only.');

        return 1;
    }
    $this->call('db:seed', ['--class' => MobileValidationSeeder::class]);
});

Artisan::command('school:validation-inject-otp {--adb=}', function () {
    if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'infotech_school_test' || config('school.sms_driver') !== 'local') {
        return 1;
    }
    $start = now();
    $adb = $this->option('adb');
    if (! $adb || ! is_file($adb)) {
        return 1;
    }
    for ($i = 0; $i < 300; $i++) {
        $challenge = DB::table('otp_challenges as o')->join('parent_guardians as g', 'g.id', '=', 'o.parent_guardian_id')->join('users as u', 'u.id', '=', 'g.user_id')->where('u.name', 'Fictional Validation Guardian')->where('o.created_at', '>=', $start)->whereNull('o.consumed_at')->orderByDesc('o.created_at')->value('o.id');
        if ($challenge) {
            $code = (new LocalSmsProvider)->consumeForDevelopment($challenge);
            if ($code) {
                sleep(2);
                $process = new Process([$adb, '-s', 'emulator-5554', 'shell', 'input', 'text', $code]);
                $process->run();
                $this->info('Local validation code entered on emulator (value withheld).');

                return $process->isSuccessful() ? 0 : 1;
            }
        }
        sleep(1);
    }

    return 1;
});

<?php

// Destructive validation is deliberately confined to the named disposable loopback DB.
require __DIR__.'/assert-validation-database.php';

use App\Console\Commands\CreateSuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Tester\CommandTester;

try {
    if (! app()->environment('testing')) {
        throw new RuntimeException('Testing environment required.');
    }
    if (Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]) !== 0) {
        throw new RuntimeException('Fresh migration/seeding failed.');
    }
    foreach (['roles', 'permissions', 'school_settings', 'classes'] as $table) {
        if (DB::table($table)->count() === 0) {
            throw new RuntimeException('Missing base reference configuration: '.$table);
        }
    }
    echo "migrate:fresh --seed: PASS (minimal base references; no default password).\n";
    if (Artisan::call('db:wipe', ['--force' => true]) !== 0) {
        throw new RuntimeException('Disposable database could not be emptied.');
    }
    $command = app(Kernel::class)->all()['school:bootstrap'];
    // Windows' native hidden-input executable requires an actual console. Stub only
    // that terminal boundary; the real bootstrap/account validation and DB writes run.
    $fixture = new class extends CreateSuperAdmin
    {
        public function secret($question, $fallback = true)
        {
            if ($fallback !== false) {
                throw new RuntimeException('Hidden input must never fall back to visible input.');
            }

            return '1234';
        }
    };
    $fixture->setLaravel(app());
    $command->getApplication()->add($fixture);
    $tester = new CommandTester($command);
    $tester->setInputs(['Fictional Bootstrap Administrator', 'bootstrap@example.test']);
    if ($tester->execute([], ['interactive' => true]) !== 0) {
        // Do not print interactive output, which could contain sensitive input in another context.
        throw new RuntimeException('Empty database bootstrap failed.');
    }
    foreach (['roles', 'permissions', 'accounts', 'payment_methods', 'school_settings', 'academic_sessions', 'fee_schedules'] as $table) {
        if (DB::table($table)->count() === 0) {
            throw new RuntimeException('Missing installation configuration: '.$table);
        }
    }
    $user = User::where('email', 'bootstrap@example.test')->sole();
    if (! $user->isSuperAdmin() || ! Hash::check('1234', $user->password) || ! preg_match('/^ACC-[0-9]+$/', $user->account_code)) {
        throw new RuntimeException('Bootstrap account security/identifier validation failed.');
    }
    if (str_contains($tester->getDisplay(), '1234')) {
        throw new RuntimeException('Password was exposed by bootstrap output.');
    }
    $hash = $user->password;
    if ($tester->execute([], ['interactive' => true]) === 0 || $user->fresh()->password !== $hash || User::count() !== 1) {
        throw new RuntimeException('Repeated bootstrap changed the installed school.');
    }
    echo "Empty database bootstrap, hashed 4-character credential, roles/defaults, safe repeat: PASS.\n";

} catch (Throwable $e) {
    fwrite(STDERR, 'Bootstrap validation failed: '.get_class($e).PHP_EOL);
    exit(1);
}

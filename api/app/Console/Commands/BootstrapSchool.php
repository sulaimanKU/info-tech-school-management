<?php

namespace App\Console\Commands;

use Database\Seeders\SchoolInstallationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\StreamableInputInterface;

class BootstrapSchool extends Command
{
    protected $signature = 'school:bootstrap';

    protected $description = 'Install an empty local/testing database, then securely prompt for its first Super Admin';

    protected function createInputFromArguments(array $arguments)
    {
        $input = parent::createInputFromArguments($arguments);
        if ($this->input instanceof StreamableInputInterface) {
            $input->setStream($this->input->getStream());
        }

        return $input;
    }

    public function handle(): int
    {
        // No reset option, force override, default credential, or password CLI argument.
        if (! app()->environment(['local', 'testing']) || ! $this->input->isInteractive()) {
            $this->error('Interactive local/testing installation only. Production is refused.');

            return self::FAILURE;
        }
        if (Schema::hasTable('users') && DB::table('users')->exists()) {
            $this->error('Existing accounts found. Bootstrap will not reset an installed school.');

            return self::FAILURE;
        }
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS
            || $this->call('db:seed', ['--class' => SchoolInstallationSeeder::class, '--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('school:create-super-admin');
    }
}

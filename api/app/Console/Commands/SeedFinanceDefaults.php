<?php

namespace App\Console\Commands;

use Database\Seeders\ManualPaymentMethodSeeder;
use Illuminate\Console\Command;

class SeedFinanceDefaults extends Command
{
    protected $signature = 'school:seed-finance-defaults';

    protected $description = 'Insert missing ledger accounts and manual payment labels; preserve existing settings and records';

    public function handle(): int
    {
        app(ManualPaymentMethodSeeder::class)->run();
        $this->info('Missing finance defaults inserted. Existing accounts, payment methods, fees and permissions were not overwritten.');

        return self::SUCCESS;
    }
}

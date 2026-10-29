<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Explicit school installation defaults; no operational records or default credentials. */
class SchoolInstallationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DatabaseSeeder::class, MobilePermissionSeeder::class, FinanceConfigurationSeeder::class]);
    }
}

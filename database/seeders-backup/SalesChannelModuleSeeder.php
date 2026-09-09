<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SalesChannelModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SalesChannelSeeder::class,
            SalesChannelPermissionSeeder::class,
        ]);
    }
}

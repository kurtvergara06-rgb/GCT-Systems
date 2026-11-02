<?php

namespace Database\Seeders;

use App\Models\Admin\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')
            || \Illuminate\Support\Facades\DB::getDefaultConnection() === 'gct_account_target') {
            throw new \RuntimeException('Production and account-only target seeding is disabled.');
        }

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        if (config('demo-data.enabled', false)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}

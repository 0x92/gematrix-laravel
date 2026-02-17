<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PhraseSeeder::class,
        ]);

        if (Schema::hasTable('users')) {
            try {
                User::query()->updateOrCreate(
                    ['email' => 'admin@gematrix.local'],
                    [
                        'name' => 'Admin',
                        'password' => 'admin12345',
                        'is_admin' => true,
                        'locale_preference' => 'en',
                        'theme' => 'dark',
                        'preferred_ciphers' => config('gematria.default_enabled'),
                    ]
                );
            } catch (\Throwable) {
                // Keep seeding resilient on partially migrated environments.
            }
        }

        if (Schema::hasTable('app_settings')) {
            AppSetting::query()->updateOrCreate(
                ['key' => 'enabled_ciphers'],
                ['value' => config('gematria.default_enabled')]
            );
        }
    }
}

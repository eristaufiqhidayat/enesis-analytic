<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['username' => env('SEED_USER_USERNAME', 'user')],
            [
                'name' => 'User',
                'email' => env('SEED_USER_EMAIL', 'user@example.com'),
                'password' => env('SEED_USER_PASSWORD', 'password'),
            ],
        );

        $this->call(MotorisSnapshotSeeder::class);
    }
}

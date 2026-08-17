<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@smki.test',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'unit_id' => null,
            ],
            [
                'name' => 'Admin Kepatuhan',
                'email' => 'admin@smki.test',
                'password' => Hash::make('password'),
                'role' => 'admin_kepatuhan',
                'unit_id' => null,
            ],
            [
                'name' => 'Koordinator SMKI',
                'email' => 'koordinator@smki.test',
                'password' => Hash::make('password'),
                'role' => 'koordinator_smki',
                'unit_id' => null,
            ],
            [
                'name' => 'Auditor',
                'email' => 'auditor@smki.test',
                'password' => Hash::make('password'),
                'role' => 'auditor',
                'unit_id' => null,
            ],
            [
                'name' => 'PIC Biro Hukum',
                'email' => 'pic@smki.test',
                'password' => Hash::make('password'),
                'role' => 'pic',
                'unit_id' => null, // akan di-update setelah unit kerja dibuat
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user + ['email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('Berhasil menyemai '.count($users).' user test.');
    }
}

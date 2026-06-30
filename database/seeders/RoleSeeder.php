<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'super agent', 'agent'];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role]);
        }

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@rocksnet.org',
            'password' => Hash::make('password123'),
            'role_id' => 1, // adjust as needed
        ]);
    }
}

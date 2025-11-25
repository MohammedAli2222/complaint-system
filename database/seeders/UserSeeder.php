<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // تأكد من أن الأدوار موجودة
        $employeeRole = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $citizenRole  = Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

        $usersData = [
            // 2 موظفين
            ['name' => 'Employee 1', 'email' => 'employee1@example.com', 'role' => 'employee','entity_id' => 1],
            ['name' => 'Employee 2', 'email' => 'employee2@example.com', 'role' => 'employee','entity_id' => 6],

            // 8 مواطنين
            ['name' => 'Citizen 1', 'email' => 'citizen1@example.com', 'role' => 'citizen'],
            ['name' => 'Citizen 2', 'email' => 'citizen2@example.com', 'role' => 'citizen'],
            ['name' => 'Citizen 3', 'email' => 'citizen3@example.com', 'role' => 'citizen'],
            ['name' => 'Citizen 4', 'email' => 'citizen4@example.com', 'role' => 'citizen'],
            ['name' => 'Citizen 5', 'email' => 'citizen5@example.com', 'role' => 'citizen'],
            ['name' => 'Citizen 6', 'email' => 'citizen6@example.com', 'role' => 'citizen'],
            ['name' => 'Citizen 7', 'email' => 'citizen7@example.com', 'role' => 'citizen'],
            ['name' => 'Citizen 8', 'email' => 'citizen8@example.com', 'role' => 'citizen'],
        ];

        foreach ($usersData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password123'), // كلمة مرور افتراضية
                ]
            );

            // تعيين الدور
            $user->syncRoles($data['role']);
        }
    }
}

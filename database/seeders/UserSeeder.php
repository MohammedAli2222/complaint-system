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
        // تأكد من الأدوار
        $employeeRole = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $citizenRole  = Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

        // 2 موظفين (كما في الكود الأصلي)
        $usersData = [
            ['name' => 'Employee 1', 'email' => 'employee1@example.com', 'role' => 'employee', 'entity_id' => 1],
            ['name' => 'Employee 2', 'email' => 'employee2@example.com', 'role' => 'employee', 'entity_id' => 2],
        ];

        // إضافة 48 مواطنًا تلقائيًا
        for ($i = 1; $i <= 47; $i++) {
            $usersData[] = [
                'name' => "Citizen $i",
                'email' => "citizen$i@example.com",
                'role' => 'citizen'
            ];
        }

        foreach ($usersData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password123'),
                    'entity_id' => $data['entity_id'] ?? null
                ]
            );

            // تعيين الدور
            $user->syncRoles($data['role']);
        }
    }
}

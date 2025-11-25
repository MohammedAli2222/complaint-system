<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        // تم تعيين الباسورد بشكل صريح ومؤكد هنا لتجاوز مشكلة قراءة .env
        $email = env('ADMIN_EMAIL', 'admin@system.gov.sy');
        $password = env('ADMIN_PASSWORD', 'password'); // سيعود لقراءة الـ .env

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );


        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            // Complaints
            'complaints.view-any',
            'complaints.view',
            'complaints.handle',
            'update_complaint_status',
            'assign_complaints',
            'requestMoreInfo',
            'addNote',

            // Employee Management
            'create_employee',
            'update_employee',
            'delete_employee',
            'edit_employee_permissions',
            'view_assigned_complaints',

            // User Management
            'view_users',
            'update_user',
            'delete_user',
            'block_user',

            // System
            'view_system_logs',
            'view_statistics',
            'export_reports',
        ];

        foreach ($permissions as $permission)
            Permission::firstOrCreate(['name' => $permission]);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $employee = Role::firstOrCreate(['name' => 'employee']);
        $citizen = Role::firstOrCreate(['name' => 'citizen']);

        // admin = كل الصلاحيات
        $admin->givePermissionTo(Permission::all());

        // employee = صلاحيات محدودة
        $employee->givePermissionTo([
            'view_assigned_complaints',
            'update_complaint_status',
            'complaints.view',
            'complaints.handle',
            'requestMoreInfo',
            'addNote',

        ]);

        $citizen->givePermissionTo([
            'complaints.view',
        ]);
    }
}

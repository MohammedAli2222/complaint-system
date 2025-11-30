<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض قائمة الموظفين (index)
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_users');
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء موظف جديد (store)
     */
    public function create(User $user): bool
    {
        // الصلاحية المستخدمة: 'create_employee'
        return $user->hasRole('admin') || $user->can('create_employee');
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث موظف موجود (update)
     */
    public function update(User $user, User $model): bool
    {
        // الصلاحية المستخدمة: 'update_employee' (أو 'update_user' حسب الحاجة)
        // إذا كان الموظف ضمن مجموعة الموظفين (employee management)، نستخدم 'update_employee'
        return $user->hasRole('admin') || $user->can('update_employee');
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث صلاحيات موظف
     */
    public function updatePermissions(User $user, User $model): bool
    {
        // الصلاحية المستخدمة: 'edit_employee_permissions'
        return $user->hasRole('admin') || $user->can('edit_employee_permissions');
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف موظف (destroy)
     */
    public function delete(User $user, User $model): bool
    {
        // منع المستخدم من حذف حسابه الخاص
        if ($user->id === $model->id) {
            return false;
        }

        // الصلاحية المستخدمة: 'delete_employee'
        return $user->hasRole('admin') || $user->can('delete_employee');
    }

    public function viewCitizen(User $user): bool
    {
        return $user->hasRole(['admin', 'employee']) || $user->can('view_users');
    }
}

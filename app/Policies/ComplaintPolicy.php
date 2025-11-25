<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    /**
     * الصلاحيات الخاصة برؤية الشكوى
     */
    public function view(User $user, Complaint $complaint)
    {
        if ($user->hasRole('admin') || $user->can('complaints.view-any')) {
            return true;
        }

        if ($user->hasRole('employee') && $user->entity_id === $complaint->entity_id) {
            return true;
        }
        if ($user->id === $complaint->user_id) {
            return true;
        }

        return false;
    }

    /**
     * تحديد ما إذا كان المستخدم يستطيع تحديث حالة الشكوى (Status Update)
     */
    public function update(User $user, Complaint $complaint): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (
            $user->can('complaints.handle') &&
            (
                $complaint->assigned_to === $user->id || // إذا كانت الشكوى معينة له
                $complaint->entity_id === $user->entity_id // إذا كانت الشكوى تابعة لجهته
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * تحديد ما إذا كان المستخدم يستطيع قفل الشكوى (Lock)
     */
    public function lock(User $user, Complaint $complaint)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if (
            $user->can('complaints.handle') &&
            (
                $complaint->assigned_to === $user->id ||      // معينة له
                $complaint->entity_id === $user->entity_id   // تابعة لجهته
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * تحديد ما إذا كان المستخدم يستطيع تعيين (Assign) الشكوى
     */
    public function assign(User $user, Complaint $complaint): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return false;
    }


    public function addNote(User $user, Complaint $complaint)
    {
        return $user->entity_id === $complaint->entity_id;
    }

    public function requestMoreInfo(User $user, Complaint $complaint)
    {
        return $user->entity_id === $complaint->entity_id;
    }
}

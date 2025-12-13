<?php

namespace App\Traits;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait HasComplaintLocking
{
    // غيّر الاسم لتجنب التضارب
    protected function performLock(Complaint $complaint, User $user): void
    {
        DB::transaction(function () use ($complaint, $user) {
            if ($complaint->locked_by && $complaint->locked_by !== $user->id) {
                throw new \Exception('الشكوى مقفلة بواسطة موظف آخر.', 409);
            }

            $complaint->update([
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);

            // افتراض أن $this->audit موجود
            if (property_exists($this, 'audit')) {
                $this->audit->logAction($user->id, 'complaint.locked', [
                    'complaint_id' => $complaint->id,
                ]);
            }
        });
    }

    protected function performUnlock(Complaint $complaint, User $user): void
    {
        DB::transaction(function () use ($complaint, $user) {
            if ($complaint->locked_by === null) {
                return;
            }

            if (!$user->hasRole('admin') && $complaint->locked_by !== $user->id) {
                throw new \Exception('لا يمكنك فك قفل شكوى قفلها موظف آخر.', 403);
            }

            $complaint->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);

            if (property_exists($this, 'audit')) {
                $this->audit->logAction($user->id, 'complaint.unlocked', [
                    'complaint_id' => $complaint->id,
                ]);
            }
        });
    }
}

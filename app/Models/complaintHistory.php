<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'user_id',
        'action',
        'description',
        'old_data',
        'new_data'
    ];

    // 🛠️ هام: تحديد الأعمدة التي يجب فك تشفيرها كـ JSON
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    // العلاقة مع الموظف الذي قام بالتغيير
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    // دالة للحصول على وصف مقروء للإجراء
    public function getActionDescriptionAttribute(): string
    {
        $actions = [
            'created' => 'تم إنشاء الشكوى',
            'status_changed' => 'تم تغيير حالة الشكوى',
            'note_added' => 'تم إضافة ملاحظة',
            'assigned' => 'تم تعيين الشكوى',
            'file_attached' => 'تم إرفاق ملف',
        ];

        return $actions[$this->action] ?? $this->action;
    }
}

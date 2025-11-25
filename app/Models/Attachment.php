<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'mime_type'
    ];

    protected $casts = [
        'file_size' => 'integer'
    ];

    // العلاقة مع الشكوى
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    // دالة للحصول على رابط الملف
    public function getFileUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    // دالة للتحقق من نوع الملف (صورة)
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // دالة للتحقق من نوع الملف (وثيقة)
    public function isDocument(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]);
    }

    // يمكن إضافة علاقة لمن رفع الملف
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

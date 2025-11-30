<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'reference_number',
        'user_id',
        'entity_id',
        'type',
        'location',
        'description',
        'status',
        'locked_by',
        'locked_at',
        'assigned_to'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'complaint_id', 'id');  // تأكد من اسم الموديل Attachment
    }

    public function history()
    {
        return $this->hasMany(ComplaintHistory::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

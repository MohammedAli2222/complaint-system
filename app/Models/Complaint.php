<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Complaint extends Model implements Auditable
{
    use AuditableTrait;

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

    protected $auditInclude = [
        'status',
        'assigned_to',
        'locked_by',
        'locked_at',
        'entity_id',
    ];

    // تخصيص events لتكون أكثر دقة (بدلاً من 'updated' دائمًا)
    protected $auditEvents = [
        'created',
        'updated',
        'deleted',
        'restored',
    ];

    public function transformAudit(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['ip_address'] = request()->ip();
        $data['user_agent'] = request()->userAgent();

        return $data;
    }

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
        return $this->hasMany(Attachment::class, 'complaint_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // إزالة علاقة history تمامًا
}

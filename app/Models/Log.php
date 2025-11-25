<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = ['user_id', 'action', 'details', 'ip_address', 'user_agent', 'timestamp'];

    protected $casts = [
        'details' => 'array',
        'timestamp' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    protected $fillable = ['name', 'description', 'contact_email', 'contact_phone', 'is_active'];

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}

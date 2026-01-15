<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'target_user_id',
        'action',
        'details',
        'ip_address',
        'user_agent'
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
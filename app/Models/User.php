<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable; // PENTING: Pakai Authenticatable, bukan Model biasa
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $fillable = ['email', 'password', 'role', 'dinas_id', 'is_active'];

    protected $hidden = [
        'password',
    ];

    public function dinas()
    {
        return $this->belongsTo(Dinas::class, 'dinas_id');
    }
}
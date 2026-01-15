<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'context_type',
        'year',
        'stage',
        'document_type',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Relasi ke User (Actor)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi Polymorphic ke Subject (Target Aksi)
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Scope untuk filter Admin logs
     */
    public function scopeAdminActions($query)
    {
        return $query->where('context_type', 'admin')
                    ->orWhereHas('user', function($q) {
                        $q->where('role', 'admin');
                    });
    }

    /**
     * Scope untuk filter Pusdatin logs
     */
    public function scopePusdatinActions($query)
    {
        return $query->where('context_type', 'pusdatin')
                    ->orWhereHas('user', function($q) {
                        $q->where('role', 'pusdatin');
                    });
    }

    /**
     * Scope untuk filter by year
     */
    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope untuk filter by stage
     */
    public function scopeForStage($query, $stage)
    {
        return $query->where('stage', $stage);
    }

    /**
     * Helper Static untuk mencatat log
     */
    public static function record($action, $description, $subject = null, $properties = null)
    {
        $userId = Auth::check() ? Auth::id() : null;
        $user = Auth::user();
        
        // Tentukan context_type dari role user
        $contextType = null;
        if ($user) {
            if ($user->role === 'admin') {
                $contextType = 'admin';
            } elseif ($user->role === 'pusdatin') {
                $contextType = 'pusdatin';
            } elseif (in_array($user->role, ['provinsi', 'kabupaten/kota'])) {
                $contextType = 'dinas';
            }
        }

        // Extract year dari properties atau subject jika ada
        $year = null;
        if (isset($properties['year'])) {
            $year = $properties['year'];
        } elseif ($subject && method_exists($subject, 'getYear')) {
            $year = $subject->getYear();
        }

        return self::create([
            'user_id'      => $userId,
            'action'       => $action,
            'description'  => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject ? $subject->id : null,
            'context_type' => $contextType,
            'year'         => $year,
            'stage'        => $properties['stage'] ?? null,
            'document_type'=> $properties['document_type'] ?? null,
            'properties'   => $properties,
            'ip_address'   => Request::ip(),
            'user_agent'   => Request::header('User-Agent'),
        ]);
    }

    /**
     * Record Admin Activity
     */
    public static function recordAdminAction($action, $description, $subject = null, $properties = null)
    {
        $properties = $properties ?? [];
        $properties['context'] = 'admin';
        
        return self::record($action, $description, $subject, $properties);
    }

    /**
     * Record Pusdatin Activity
     */
    public static function recordPusdatinAction($action, $description, $subject = null, $properties = null)
    {
        $properties = $properties ?? [];
        $properties['context'] = 'pusdatin';
        
        return self::record($action, $description, $subject, $properties);
    }
}
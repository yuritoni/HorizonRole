<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'kind',
        'title',
        'summary',
        'body',
        'category',
        'audience',
        'source_department',
        'priority',
        'scope_module',
        'is_published',
        'is_pinned',
        'requires_acknowledgement',
        'publish_at',
        'expires_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_pinned' => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'publish_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads()
    {
        return $this->hasMany(EmployeeAnnouncementRead::class);
    }

    public function readFor(User $user): ?EmployeeAnnouncementRead
    {
        return $this->reads->firstWhere('user_id', $user->id);
    }

    public function scopePublished($query)
    {
        return $query
            ->where('is_published', true)
            ->where(function ($inner) {
                $inner->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            })
            ->where(function ($inner) {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeVisibleToUser($query, User $user)
    {
        if ($user->hasPrivilege('announcements.manage')) {
            return $query;
        }

        $audiences = ['all_systems', $user->role];

        if ($user->role === 'employee' || $user->employeeProfile) {
            $audiences[] = 'all';
            $audiences[] = 'employee';
        }

        if ($user->employeeProfile?->department) {
            $audiences[] = $user->employeeProfile->department;
        }

        return $query->whereIn('audience', array_unique($audiences));
    }

    public function audienceLabel(): string
    {
        return match ($this->audience) {
            'all' => 'Semua Pegawai',
            'all_systems' => 'Semua User Sistem',
            'employee' => 'Employee',
            default => $this->audience,
        };
    }
}

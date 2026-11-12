<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_name',
        'email',
        'phone',
        'department',
        'position',
        'stage',
        'meeting_at',
        'meeting_url',
        'interviewer',
        'test_score',
        'onboarding_start_date',
        'assigned_user_id',
        'hris_record_id',
        'payload',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'meeting_at' => 'datetime',
        'onboarding_start_date' => 'date',
        'test_score' => 'decimal:2',
        'payload' => 'array',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function hrisRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'hris_record_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

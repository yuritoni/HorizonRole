<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'user_id',
        'marketing_branch_id',
        'tracked_at',
        'device_id',
        'phone_number',
        'latitude',
        'longitude',
        'address',
        'activity_type',
        'customer_name',
        'pipeline_amount',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'tracked_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'pipeline_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function profile()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(MarketingBranch::class, 'marketing_branch_id');
    }
}

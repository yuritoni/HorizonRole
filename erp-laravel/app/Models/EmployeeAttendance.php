<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'user_id',
        'source',
        'device_id',
        'phone_number',
        'attendance_date',
        'clock_in_at',
        'clock_in_lat',
        'clock_in_lng',
        'clock_in_address',
        'clock_out_at',
        'clock_out_lat',
        'clock_out_lng',
        'clock_out_address',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'approved_at' => 'datetime',
        'clock_in_lat' => 'decimal:7',
        'clock_in_lng' => 'decimal:7',
        'clock_out_lat' => 'decimal:7',
        'clock_out_lng' => 'decimal:7',
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

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAnnouncementRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_announcement_id',
        'user_id',
        'read_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(EmployeeAnnouncement::class, 'employee_announcement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

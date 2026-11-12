<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_id',
        'subject_user_id',
        'erp_record_id',
        'payment_id',
        'type',
        'severity',
        'module',
        'title',
        'body',
        'action_url',
        'data',
        'read_at',
        'fcm_sent_at',
        'fcm_status',
        'fcm_response',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'fcm_sent_at' => 'datetime',
        'fcm_response' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subjectUser()
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function record()
    {
        return $this->belongsTo(ErpRecord::class, 'erp_record_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markAsRead(): void
    {
        if ($this->isUnread()) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'erp_record_id',
        'user_id',
        'provider',
        'order_id',
        'amount',
        'status',
        'snap_token',
        'redirect_url',
        'payment_type',
        'transaction_id',
        'fraud_status',
        'paid_at',
        'raw_response',
        'raw_notification',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_response' => 'array',
        'raw_notification' => 'array',
    ];

    public function record()
    {
        return $this->belongsTo(ErpRecord::class, 'erp_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifications()
    {
        return $this->hasMany(ErpNotification::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}

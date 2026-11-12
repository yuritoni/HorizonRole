<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_shipment_id',
        'event_code',
        'status',
        'description',
        'location',
        'event_at',
        'source',
        'external_id',
        'payload',
        'created_by',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'payload' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(SalesShipment::class, 'sales_shipment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

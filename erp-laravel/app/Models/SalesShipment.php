<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_no',
        'sales_record_id',
        'courier_provider_id',
        'customer_charge_finance_record_id',
        'courier_cost_finance_record_id',
        'service_code',
        'tracking_number',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'origin',
        'destination',
        'weight_kg',
        'package_count',
        'customer_shipping_charge',
        'courier_cost',
        'insurance_cost',
        'payment_policy',
        'customer_charge_payment_status',
        'courier_cost_payment_status',
        'settlement_status',
        'status',
        'pickup_scheduled_at',
        'estimated_delivery_at',
        'shipped_at',
        'delivered_at',
        'last_tracked_at',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'customer_shipping_charge' => 'decimal:2',
        'courier_cost' => 'decimal:2',
        'insurance_cost' => 'decimal:2',
        'pickup_scheduled_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'last_tracked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function salesRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'sales_record_id');
    }

    public function courierProvider()
    {
        return $this->belongsTo(CourierProvider::class);
    }

    public function customerChargeFinanceRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'customer_charge_finance_record_id');
    }

    public function courierCostFinanceRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'courier_cost_finance_record_id');
    }

    public function trackingEvents()
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderByDesc('event_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function externalTrackingUrl(): ?string
    {
        $template = $this->courierProvider?->tracking_url_template;

        if (! $template || ! $this->tracking_number) {
            return null;
        }

        return str_replace('{tracking_number}', rawurlencode($this->tracking_number), $template);
    }
}

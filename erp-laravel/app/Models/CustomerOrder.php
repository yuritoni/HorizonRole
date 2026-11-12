<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'erp_record_id',
        'finance_record_id',
        'customer_address_id',
        'order_number',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'payment_status',
        'fulfillment_status',
        'shipping_snapshot',
        'customer_note',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipping_snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function salesRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'erp_record_id');
    }

    public function financeRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'finance_record_id');
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }
}

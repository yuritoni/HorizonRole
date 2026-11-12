<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpRecordProductLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'erp_record_id',
        'product_id',
        'line_type',
        'line_no',
        'quantity',
        'unit_price',
        'tax_rate',
        'line_amount',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function record()
    {
        return $this->belongsTo(ErpRecord::class, 'erp_record_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

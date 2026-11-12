<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'erp_record_id',
        'movement_type',
        'direction',
        'quantity',
        'unit_cost',
        'status',
        'reference',
        'occurred_at',
        'notes',
        'extra',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'occurred_at' => 'datetime',
        'extra' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function record()
    {
        return $this->belongsTo(ErpRecord::class, 'erp_record_id');
    }
}

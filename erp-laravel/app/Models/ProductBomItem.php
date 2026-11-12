<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'component_product_id',
        'quantity_per_unit',
        'waste_percent',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity_per_unit' => 'decimal:4',
        'waste_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function component()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}

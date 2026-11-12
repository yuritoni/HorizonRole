<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'type',
        'category',
        'uom',
        'barcode',
        'sales_price',
        'standard_cost',
        'tax_rate',
        'min_stock',
        'stock_on_hand',
        'reserved_stock',
        'warehouse',
        'is_sellable',
        'is_manufacturable',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sales_price' => 'decimal:2',
        'standard_cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'min_stock' => 'decimal:3',
        'stock_on_hand' => 'decimal:3',
        'reserved_stock' => 'decimal:3',
        'is_sellable' => 'boolean',
        'is_manufacturable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function bomItems()
    {
        return $this->hasMany(ProductBomItem::class)->orderBy('id');
    }

    public function usedInBomItems()
    {
        return $this->hasMany(ProductBomItem::class, 'component_product_id');
    }

    public function recordLines()
    {
        return $this->hasMany(ErpRecordProductLine::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(ProductStockMovement::class)->latest('occurred_at');
    }

    public function availableStock(): float
    {
        return (float) $this->stock_on_hand - (float) $this->reserved_stock;
    }
}

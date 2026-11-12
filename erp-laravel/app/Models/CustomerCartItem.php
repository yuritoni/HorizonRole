<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCartItem extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

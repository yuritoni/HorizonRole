<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterDataGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'type',
        'module',
        'description',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(MasterDataItem::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeItems()
    {
        return $this->items()->where('is_active', true);
    }
}

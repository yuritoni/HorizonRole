<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterDataItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_data_group_id',
        'parent_id',
        'code',
        'name',
        'value',
        'description',
        'extra',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'extra' => 'array',
        'is_active' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(MasterDataGroup::class, 'master_data_group_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function displayValue(): string
    {
        return $this->value ?: $this->name;
    }
}

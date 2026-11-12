<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'region',
        'phone',
        'address',
        'manager_name',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function trackings()
    {
        return $this->hasMany(MarketingTracking::class);
    }
}

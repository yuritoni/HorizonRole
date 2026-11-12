<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'integration_mode',
        'services',
        'tracking_url_template',
        'api_base_url',
        'webhook_secret',
        'supports_cod',
        'is_active',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'services' => 'array',
        'supports_cod' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected $hidden = [
        'webhook_secret',
    ];

    public function shipments()
    {
        return $this->hasMany(SalesShipment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

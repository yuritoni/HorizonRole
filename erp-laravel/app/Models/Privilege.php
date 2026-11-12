<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Privilege extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'group',
        'label',
        'description',
        'module',
        'action',
        'menu_key',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_privileges')
            ->withPivot('effect', 'notes')
            ->withTimestamps();
    }
}

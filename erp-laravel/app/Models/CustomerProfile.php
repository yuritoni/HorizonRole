<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'google_subject',
        'registration_channel',
        'birth_date',
        'gender',
        'marketing_opt_in',
        'preferences',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'marketing_opt_in' => 'boolean',
        'preferences' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

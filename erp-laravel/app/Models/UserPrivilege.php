<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPrivilege extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'privilege_id',
        'effect',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function privilege()
    {
        return $this->belongsTo(Privilege::class);
    }
}

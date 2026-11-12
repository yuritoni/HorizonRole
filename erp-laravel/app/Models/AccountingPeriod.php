<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_code',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function journalEntries()
    {
        return $this->hasMany(AccountingJournalEntry::class, 'period_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}

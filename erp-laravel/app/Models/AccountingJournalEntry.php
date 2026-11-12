<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingJournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_no',
        'source_type',
        'source_id',
        'period_id',
        'entry_date',
        'reference_no',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'posted_at',
        'posted_by',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function lines()
    {
        return $this->hasMany(AccountingJournalLine::class, 'journal_entry_id')->orderBy('line_no');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function isBalanced(): bool
    {
        return round((float) $this->total_debit, 2) === round((float) $this->total_credit, 2)
            && (float) $this->total_debit > 0;
    }
}

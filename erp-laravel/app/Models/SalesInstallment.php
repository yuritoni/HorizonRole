<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_installment_plan_id',
        'finance_record_id',
        'installment_no',
        'amount',
        'due_date',
        'status',
        'invoice_no',
        'virtual_account',
        'paid_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(SalesInstallmentPlan::class, 'sales_installment_plan_id');
    }

    public function financeRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'finance_record_id');
    }
}

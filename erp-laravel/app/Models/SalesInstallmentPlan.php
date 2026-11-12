<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInstallmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'erp_record_id',
        'customer',
        'net_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'down_payment',
        'installment_count',
        'interval_months',
        'first_due_date',
        'status',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'net_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'first_due_date' => 'date',
        'metadata' => 'array',
    ];

    public function salesRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'erp_record_id');
    }

    public function installments()
    {
        return $this->hasMany(SalesInstallment::class, 'sales_installment_plan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

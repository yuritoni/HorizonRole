<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeReimbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'user_id',
        'category',
        'title',
        'amount',
        'expense_date',
        'description',
        'receipt_reference',
        'status',
        'payment_method',
        'employee_payroll_id',
        'paid_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'paid_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payroll()
    {
        return $this->belongsTo(EmployeePayroll::class, 'employee_payroll_id');
    }
}

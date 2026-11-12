<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'user_id',
        'period_month',
        'basic_salary',
        'allowance',
        'deduction',
        'reimbursement',
        'gross_salary',
        'overtime_amount',
        'bonus',
        'thr',
        'tax',
        'marketing_commission',
        'production_incentive',
        'attendance_allowance',
        'attendance_deduction',
        'net_salary',
        'status',
        'paid_at',
        'components',
        'notes',
    ];

    protected $casts = [
        'period_month' => 'date',
        'basic_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction' => 'decimal:2',
        'reimbursement' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'bonus' => 'decimal:2',
        'thr' => 'decimal:2',
        'tax' => 'decimal:2',
        'marketing_commission' => 'decimal:2',
        'production_incentive' => 'decimal:2',
        'attendance_allowance' => 'decimal:2',
        'attendance_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'paid_at' => 'datetime',
        'components' => 'array',
    ];

    public function profile()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

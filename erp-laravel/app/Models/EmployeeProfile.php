<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'erp_record_id',
        'manager_profile_id',
        'employee_no',
        'department',
        'position',
        'job_level',
        'grade',
        'employment_status',
        'join_date',
        'manager_name',
        'work_location',
        'branch',
        'phone',
        'birth_place',
        'birth_date',
        'gender',
        'address',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'base_salary',
        'fixed_allowance',
        'tax_status',
        'marketing_target',
        'commission_rate',
        'production_kpi_rate',
        'personal_data',
        'education_data',
        'emergency_contact',
        'job_data',
        'experience_data',
        'settings',
    ];

    protected $casts = [
        'join_date' => 'date',
        'birth_date' => 'date',
        'base_salary' => 'decimal:2',
        'fixed_allowance' => 'decimal:2',
        'marketing_target' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'production_kpi_rate' => 'decimal:4',
        'personal_data' => 'array',
        'education_data' => 'array',
        'emergency_contact' => 'array',
        'job_data' => 'array',
        'experience_data' => 'array',
        'settings' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hrRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'erp_record_id');
    }

    public function managerProfile()
    {
        return $this->belongsTo(self::class, 'manager_profile_id');
    }

    public function directReports()
    {
        return $this->hasMany(self::class, 'manager_profile_id')->orderBy('department')->orderBy('position');
    }

    public function attendances()
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function requests()
    {
        return $this->hasMany(EmployeeRequest::class);
    }

    public function reimbursements()
    {
        return $this->hasMany(EmployeeReimbursement::class);
    }

    public function payrolls()
    {
        return $this->hasMany(EmployeePayroll::class);
    }

    public function marketingTrackings()
    {
        return $this->hasMany(MarketingTracking::class);
    }
}

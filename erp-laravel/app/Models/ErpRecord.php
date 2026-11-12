<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'code',
        'title',
        'party',
        'category',
        'amount',
        'quantity',
        'min_quantity',
        'progress',
        'status',
        'owner',
        'due_date',
        'notes',
        'extra',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'extra' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'erp_record_id');
    }

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class, 'erp_record_id');
    }

    public function outgoingLinks()
    {
        return $this->hasMany(ErpRecordLink::class, 'source_record_id');
    }

    public function incomingLinks()
    {
        return $this->hasMany(ErpRecordLink::class, 'target_record_id');
    }

    public function notifications()
    {
        return $this->hasMany(ErpNotification::class, 'erp_record_id');
    }

    public function salesShipments()
    {
        return $this->hasMany(SalesShipment::class, 'sales_record_id');
    }

    public function productLines()
    {
        return $this->hasMany(ErpRecordProductLine::class, 'erp_record_id')->orderBy('line_type')->orderBy('line_no');
    }
}

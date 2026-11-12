<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpRecordLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_record_id',
        'target_record_id',
        'relation_type',
        'status',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function sourceRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'source_record_id');
    }

    public function targetRecord()
    {
        return $this->belongsTo(ErpRecord::class, 'target_record_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

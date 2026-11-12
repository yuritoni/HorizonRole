<?php

use App\Models\EmployeeProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('employee_profiles', 'erp_record_id')) {
            return;
        }

        EmployeeProfile::with(['user', 'hrRecord'])
            ->whereNotNull('erp_record_id')
            ->get()
            ->each(function (EmployeeProfile $profile) {
                if (! $profile->user || $profile->user->role === 'employee') {
                    return;
                }

                $record = $profile->hrRecord;

                if ($record
                    && $record->module === 'hr'
                    && $record->code === $profile->employee_no
                    && ($record->extra['source'] ?? null) === 'employee_management') {
                    $record->delete();
                }

                $profile->forceFill(['erp_record_id' => null])->save();
            });
    }

    public function down()
    {
        //
    }
};

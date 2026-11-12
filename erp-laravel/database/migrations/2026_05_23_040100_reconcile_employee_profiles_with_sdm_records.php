<?php

use App\Services\EmployeeIntegrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('employee_profiles', 'erp_record_id')) {
            return;
        }

        $integrator = app(EmployeeIntegrationService::class);
        $integrator->syncAllHrRecords();
        $integrator->syncAllEmployeeProfiles();
    }

    public function down()
    {
        //
    }
};

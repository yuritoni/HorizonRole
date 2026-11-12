<?php

use App\Models\ErpRecord;
use App\Models\Privilege;
use App\Services\EmployeeIntegrationService;
use App\Support\PrivilegeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('erp_records') && Schema::hasTable('employee_profiles')) {
            app(EmployeeIntegrationService::class)->syncAllHrRecords();
            app(EmployeeIntegrationService::class)->syncAllEmployeeProfiles();

            ErpRecord::where('module', 'hr')->delete();

            if (Schema::hasColumn('employee_profiles', 'erp_record_id')) {
                DB::table('employee_profiles')
                    ->whereNotNull('erp_record_id')
                    ->update(['erp_record_id' => null, 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('privileges')) {
            Privilege::where('key', 'like', 'modules.hr.%')->delete();
            PrivilegeRegistry::syncDefaults();
        }
    }

    public function down()
    {
        PrivilegeRegistry::syncDefaults();
    }
};

<?php

use App\Services\ErpModuleIntegrationService;
use App\Services\EmployeePayrollService;
use App\Services\HrisEmployeeIntegrationService;
use App\Support\MasterDataRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('erp_records') || ! Schema::hasTable('employee_profiles')) {
            return;
        }

        app(EmployeePayrollService::class)->consolidateLegacyHrisPayrollRecords();
        app(EmployeePayrollService::class)->syncAllPayrolls();
        app(HrisEmployeeIntegrationService::class)->syncExistingRecords();
        app(ErpModuleIntegrationService::class)->syncAll();
        MasterDataRegistry::syncDefaults();
    }

    public function down()
    {
        //
    }
};

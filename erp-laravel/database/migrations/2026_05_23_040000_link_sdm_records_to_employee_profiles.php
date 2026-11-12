<?php

use App\Services\EmployeeIntegrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('employee_profiles', 'erp_record_id')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->foreignId('erp_record_id')
                    ->nullable()
                    ->after('user_id')
                    ->unique()
                    ->constrained('erp_records')
                    ->nullOnDelete();
            });
        }

        app(EmployeeIntegrationService::class)->syncAllHrRecords();
    }

    public function down()
    {
        if (Schema::hasColumn('employee_profiles', 'erp_record_id')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->dropForeign(['erp_record_id']);
                $table->dropUnique('employee_profiles_erp_record_id_unique');
                $table->dropColumn('erp_record_id');
            });
        }
    }
};

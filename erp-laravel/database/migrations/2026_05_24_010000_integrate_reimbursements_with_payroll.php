<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_reimbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_reimbursements', 'payment_method')) {
                $table->string('payment_method')->default('payroll')->after('status')->index();
            }

            if (! Schema::hasColumn('employee_reimbursements', 'employee_payroll_id')) {
                $table->foreignId('employee_payroll_id')->nullable()->after('payment_method')->constrained('employee_payrolls')->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_reimbursements', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('employee_payroll_id');
            }
        });

        if (Schema::hasTable('employee_payrolls') && class_exists(\App\Services\EmployeePayrollService::class)) {
            $service = app(\App\Services\EmployeePayrollService::class);
            $service->syncAllPayrolls();
            $service->prunePayrollModeReimbursementPayables();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_reimbursements', 'employee_payroll_id')) {
            Schema::table('employee_reimbursements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('employee_payroll_id');
            });
        }

        Schema::table('employee_reimbursements', function (Blueprint $table) {
            if (Schema::hasColumn('employee_reimbursements', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            if (Schema::hasColumn('employee_reimbursements', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales_installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('erp_record_id')->unique()->constrained('erp_records')->cascadeOnDelete();
            $table->string('customer')->nullable();
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('down_payment', 18, 2)->default(0);
            $table->unsignedSmallInteger('installment_count')->default(1);
            $table->unsignedSmallInteger('interval_months')->default(1);
            $table->date('first_due_date');
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_installment_plan_id')->constrained('sales_installment_plans')->cascadeOnDelete();
            $table->foreignId('finance_record_id')->nullable()->constrained('erp_records')->nullOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('due_date');
            $table->string('status')->default('open')->index();
            $table->string('invoice_no')->nullable()->unique();
            $table->string('virtual_account')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['sales_installment_plan_id', 'installment_no'], 'sales_installments_plan_no_unique');
            $table->index(['due_date', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales_installments');
        Schema::dropIfExists('sales_installment_plans');
    }
};

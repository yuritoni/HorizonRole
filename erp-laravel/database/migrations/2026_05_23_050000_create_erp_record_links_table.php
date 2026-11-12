<?php

use App\Services\ErpModuleIntegrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('erp_record_links')) {
            Schema::create('erp_record_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_record_id')->constrained('erp_records')->cascadeOnDelete();
                $table->foreignId('target_record_id')->constrained('erp_records')->cascadeOnDelete();
                $table->string('relation_type')->index();
                $table->string('status')->default('active')->index();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['source_record_id', 'target_record_id', 'relation_type'], 'erp_record_links_unique');
                $table->index(['source_record_id', 'relation_type']);
                $table->index(['target_record_id', 'relation_type']);
            });
        }

        app(ErpModuleIntegrationService::class)->syncAll();
    }

    public function down()
    {
        Schema::dropIfExists('erp_record_links');
    }
};

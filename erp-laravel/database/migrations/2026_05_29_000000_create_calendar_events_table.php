<?php

use App\Support\PrivilegeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('event')->index();
            $table->timestamp('start_at')->index();
            $table->timestamp('end_at')->nullable()->index();
            $table->boolean('all_day')->default(false)->index();
            $table->string('location')->nullable();
            $table->string('audience')->default('all_systems')->index();
            $table->string('color', 32)->nullable();
            $table->string('source')->default('manual')->index();
            $table->nullableMorphs('related');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        PrivilegeRegistry::syncDefaults();
    }

    public function down()
    {
        Schema::dropIfExists('calendar_events');
    }
};

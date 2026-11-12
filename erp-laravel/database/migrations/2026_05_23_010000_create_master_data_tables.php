<?php

use App\Support\MasterDataRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('master_data_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('type')->default('general')->index();
            $table->string('module')->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('master_data_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_data_group_id')->constrained('master_data_groups')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('master_data_items')->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('value')->nullable();
            $table->text('description')->nullable();
            $table->json('extra')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['master_data_group_id', 'code']);
        });

        MasterDataRegistry::syncDefaults();
    }

    public function down()
    {
        Schema::dropIfExists('master_data_items');
        Schema::dropIfExists('master_data_groups');
    }
};

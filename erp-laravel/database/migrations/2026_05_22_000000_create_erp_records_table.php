<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('erp_records', function (Blueprint $table) {
            $table->id();
            $table->string('module')->index();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('party')->nullable();
            $table->string('category')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->integer('quantity')->nullable();
            $table->integer('min_quantity')->nullable();
            $table->unsignedTinyInteger('progress')->nullable();
            $table->string('status')->index();
            $table->string('owner')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('extra')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('erp_records');
    }
};

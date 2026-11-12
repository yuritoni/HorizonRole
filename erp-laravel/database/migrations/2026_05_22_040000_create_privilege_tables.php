<?php

use App\Support\PrivilegeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('privileges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->index();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('module')->nullable()->index();
            $table->string('action')->nullable()->index();
            $table->string('menu_key')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('role_privileges', function (Blueprint $table) {
            $table->id();
            $table->string('role')->index();
            $table->foreignId('privilege_id')->constrained('privileges')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role', 'privilege_id']);
        });

        Schema::create('user_privileges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('privilege_id')->constrained('privileges')->cascadeOnDelete();
            $table->string('effect')->default('grant')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'privilege_id']);
        });

        PrivilegeRegistry::syncDefaults(true);
    }

    public function down()
    {
        Schema::dropIfExists('user_privileges');
        Schema::dropIfExists('role_privileges');
        Schema::dropIfExists('privileges');
    }
};

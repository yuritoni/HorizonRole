<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'account_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('account_status')->default('pending')->after('role')->index();
            });
        }

        if (! Schema::hasColumn('users', 'activated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('activated_at')->nullable()->after('email_verified_at');
            });
        }

        DB::table('users')->update([
            'account_status' => 'active',
            'email_verified_at' => DB::raw('COALESCE(email_verified_at, NOW())'),
            'activated_at' => DB::raw('COALESCE(activated_at, NOW())'),
        ]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'account_status')) {
                $table->dropColumn('account_status');
            }

            if (Schema::hasColumn('users', 'activated_at')) {
                $table->dropColumn('activated_at');
            }
        });
    }
};

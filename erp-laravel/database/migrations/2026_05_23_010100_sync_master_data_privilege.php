<?php

use App\Support\PrivilegeRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        PrivilegeRegistry::syncDefaults();
    }

    public function down()
    {
        // Privilege data is managed from the app and can remain safely.
    }
};

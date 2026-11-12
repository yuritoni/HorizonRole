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
        // The report privilege can stay in place; permissions are managed from the UI.
    }
};

<?php

use App\Support\MasterDataRegistry;
use App\Support\PrivilegeRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        PrivilegeRegistry::syncDefaults();
        MasterDataRegistry::syncDefaults();
    }

    public function down()
    {
        //
    }
};

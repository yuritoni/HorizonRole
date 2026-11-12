<?php

use App\Models\Privilege;
use App\Support\PrivilegeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        PrivilegeRegistry::syncDefaults();

        if (! Schema::hasTable('privileges')) {
            return;
        }

        $stalePayPrivilegeIds = Privilege::where('action', 'pay')
            ->where('module', '!=', 'finance')
            ->pluck('id');

        if ($stalePayPrivilegeIds->isNotEmpty()) {
            DB::table('role_privileges')->whereIn('privilege_id', $stalePayPrivilegeIds)->delete();
            DB::table('user_privileges')->whereIn('privilege_id', $stalePayPrivilegeIds)->delete();
            Privilege::whereIn('id', $stalePayPrivilegeIds)->delete();
        }

        $financePaymentKeys = ['payments.view', 'payments.create', 'modules.finance.view', 'modules.finance.pay'];
        $financePaymentPrivilegeIds = Privilege::whereIn('key', $financePaymentKeys)->pluck('id');

        foreach (['admin', 'member'] as $role) {
            foreach ($financePaymentPrivilegeIds as $privilegeId) {
                DB::table('role_privileges')->updateOrInsert(
                    [
                        'role' => $role,
                        'privilege_id' => $privilegeId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down()
    {
        PrivilegeRegistry::syncDefaults(true);
    }
};

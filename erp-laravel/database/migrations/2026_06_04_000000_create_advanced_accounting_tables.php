<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('accounting_accounts')) {
            Schema::create('accounting_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('type')->index();
                $table->string('normal_balance')->index();
                $table->foreignId('parent_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
                $table->boolean('is_cash')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('period_code')->unique();
                $table->string('name');
                $table->date('start_date')->index();
                $table->date('end_date')->index();
                $table->string('status')->default('open')->index();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_journal_entries')) {
            Schema::create('accounting_journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('journal_no')->unique();
                $table->nullableMorphs('source');
                $table->foreignId('period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
                $table->date('entry_date')->index();
                $table->string('reference_no')->nullable()->index();
                $table->text('description');
                $table->string('status')->default('draft')->index();
                $table->decimal('total_debit', 18, 2)->default(0);
                $table->decimal('total_credit', 18, 2)->default(0);
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_journal_lines')) {
            Schema::create('accounting_journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
                $table->unsignedSmallInteger('line_no')->default(1);
                $table->text('description')->nullable();
                $table->decimal('debit', 18, 2)->default(0);
                $table->decimal('credit', 18, 2)->default(0);
                $table->string('partner')->nullable()->index();
                $table->string('cost_center')->nullable()->index();
                $table->foreignId('erp_record_id')->nullable()->constrained('erp_records')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        $this->seedDefaults();
    }

    public function down()
    {
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_accounts');
    }

    private function seedDefaults(): void
    {
        $now = now();
        $accounts = [
            ['code' => '1101', 'name' => 'Kas dan Bank', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => true],
            ['code' => '1102', 'name' => 'Piutang Usaha', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
            ['code' => '1199', 'name' => 'Suspense / Clearing', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
            ['code' => '1301', 'name' => 'Persediaan', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
            ['code' => '1501', 'name' => 'Aset Tetap', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
            ['code' => '2101', 'name' => 'Utang Vendor', 'type' => 'liability', 'normal_balance' => 'credit', 'is_cash' => false],
            ['code' => '2201', 'name' => 'PPN Keluaran', 'type' => 'liability', 'normal_balance' => 'credit', 'is_cash' => false],
            ['code' => '2202', 'name' => 'Accrued Expense', 'type' => 'liability', 'normal_balance' => 'credit', 'is_cash' => false],
            ['code' => '3101', 'name' => 'Modal / Ekuitas', 'type' => 'equity', 'normal_balance' => 'credit', 'is_cash' => false],
            ['code' => '4100', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_cash' => false],
            ['code' => '5100', 'name' => 'Beban Operasional', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
            ['code' => '5200', 'name' => 'Beban Gaji dan Benefit', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
            ['code' => '5300', 'name' => 'Beban Pengiriman', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
            ['code' => '5400', 'name' => 'Beban Pelatihan dan Dinas', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
        ];

        foreach ($accounts as $account) {
            DB::table('accounting_accounts')->updateOrInsert(
                ['code' => $account['code']],
                $account + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $start = now()->startOfMonth();
        DB::table('accounting_periods')->updateOrInsert(
            ['period_code' => $start->format('Y-m')],
            [
                'name' => $start->translatedFormat('F Y'),
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};

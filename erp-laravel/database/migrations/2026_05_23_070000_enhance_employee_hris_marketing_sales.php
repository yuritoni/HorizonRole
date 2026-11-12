<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Support\MasterDataRegistry;
use App\Support\PrivilegeRegistry;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('email_verified_at');
            }
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_profiles', 'manager_profile_id')) {
                $table->foreignId('manager_profile_id')->nullable()->after('user_id')->constrained('employee_profiles')->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_profiles', 'branch')) {
                $table->string('branch')->nullable()->index()->after('work_location');
            }

            if (! Schema::hasColumn('employee_profiles', 'grade')) {
                $table->string('grade')->nullable()->after('job_level');
            }

            if (! Schema::hasColumn('employee_profiles', 'base_salary')) {
                $table->decimal('base_salary', 16, 2)->default(0)->after('bank_account_name');
            }

            if (! Schema::hasColumn('employee_profiles', 'fixed_allowance')) {
                $table->decimal('fixed_allowance', 16, 2)->default(0)->after('base_salary');
            }

            if (! Schema::hasColumn('employee_profiles', 'tax_status')) {
                $table->string('tax_status')->nullable()->after('fixed_allowance');
            }

            if (! Schema::hasColumn('employee_profiles', 'marketing_target')) {
                $table->decimal('marketing_target', 16, 2)->default(0)->after('tax_status');
            }

            if (! Schema::hasColumn('employee_profiles', 'commission_rate')) {
                $table->decimal('commission_rate', 8, 4)->default(0)->after('marketing_target');
            }

            if (! Schema::hasColumn('employee_profiles', 'production_kpi_rate')) {
                $table->decimal('production_kpi_rate', 8, 4)->default(0)->after('commission_rate');
            }
        });

        Schema::table('employee_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_attendances', 'source')) {
                $table->string('source')->default('device')->index()->after('user_id');
            }

            if (! Schema::hasColumn('employee_attendances', 'approval_status')) {
                $table->string('approval_status')->default('approved')->index()->after('status');
            }

            if (! Schema::hasColumn('employee_attendances', 'device_id')) {
                $table->string('device_id')->nullable()->after('source');
            }

            if (! Schema::hasColumn('employee_attendances', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('device_id');
            }

            if (! Schema::hasColumn('employee_attendances', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_attendances', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('employee_attendances', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }
        });

        Schema::table('employee_payrolls', function (Blueprint $table) {
            $columns = [
                'gross_salary' => ['decimal', 16, 2],
                'overtime_amount' => ['decimal', 16, 2],
                'bonus' => ['decimal', 16, 2],
                'thr' => ['decimal', 16, 2],
                'tax' => ['decimal', 16, 2],
                'marketing_commission' => ['decimal', 16, 2],
                'production_incentive' => ['decimal', 16, 2],
                'attendance_allowance' => ['decimal', 16, 2],
                'attendance_deduction' => ['decimal', 16, 2],
            ];

            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn('employee_payrolls', $column)) {
                    $table->{$definition[0]}($column, $definition[1], $definition[2])->default(0)->after('reimbursement');
                }
            }
        });

        if (! Schema::hasTable('recruitment_candidates')) {
            Schema::create('recruitment_candidates', function (Blueprint $table) {
                $table->id();
                $table->string('candidate_name');
                $table->string('email')->nullable()->index();
                $table->string('phone')->nullable();
                $table->string('department')->nullable()->index();
                $table->string('position')->nullable();
                $table->string('stage')->default('applied')->index();
                $table->timestamp('meeting_at')->nullable();
                $table->string('meeting_url')->nullable();
                $table->string('interviewer')->nullable();
                $table->decimal('test_score', 8, 2)->nullable();
                $table->date('onboarding_start_date')->nullable();
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('hris_record_id')->nullable()->constrained('erp_records')->nullOnDelete();
                $table->json('payload')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_branches')) {
            Schema::create('marketing_branches', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('region')->nullable()->index();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('manager_name')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_trackings')) {
            Schema::create('marketing_trackings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_profile_id')->nullable()->constrained('employee_profiles')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('marketing_branch_id')->nullable()->constrained('marketing_branches')->nullOnDelete();
                $table->timestamp('tracked_at')->index();
                $table->string('device_id')->nullable()->index();
                $table->string('phone_number')->nullable()->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('address')->nullable();
                $table->string('activity_type')->default('visit')->index();
                $table->string('customer_name')->nullable();
                $table->decimal('pipeline_amount', 16, 2)->default(0);
                $table->string('status')->default('reported')->index();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        $this->seedMarketingFoundation();
        PrivilegeRegistry::syncDefaults();
        MasterDataRegistry::syncDefaults();
    }

    public function down()
    {
        Schema::dropIfExists('marketing_trackings');
        Schema::dropIfExists('marketing_branches');
        Schema::dropIfExists('recruitment_candidates');
    }

    private function seedMarketingFoundation(): void
    {
        $now = now();

        DB::table('marketing_branches')->updateOrInsert(
            ['code' => 'MKT-JKT'],
            [
                'name' => 'Cabang Marketing Jakarta',
                'region' => 'Jabodetabek',
                'phone' => '021-5550101',
                'address' => 'Jakarta Pusat',
                'manager_name' => 'Nadia Putri',
                'is_active' => true,
                'settings' => json_encode(['target_monthly' => 500000000, 'tracking_required' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $userId = DB::table('users')->where('email', 'marketing@horizon.test')->value('id');

        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Marketing Demo',
                'email' => 'marketing@horizon.test',
                'email_verified_at' => $now,
                'role' => 'employee',
                'account_status' => 'active',
                'activated_at' => $now,
                'password' => Hash::make('password123'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $profileId = DB::table('employee_profiles')->where('user_id', $userId)->value('id');

        if (! $profileId) {
            $profileId = DB::table('employee_profiles')->insertGetId([
                'user_id' => $userId,
                'employee_no' => 'MKT-2605-001',
                'department' => 'Marketing',
                'position' => 'Marketing Executive',
                'job_level' => 'Staff',
                'grade' => 'MKT-1',
                'employment_status' => 'active',
                'join_date' => $now->copy()->subMonths(3)->toDateString(),
                'manager_name' => 'Nadia Putri',
                'work_location' => 'Jakarta',
                'branch' => 'Cabang Marketing Jakarta',
                'phone' => '081300001111',
                'base_salary' => 6500000,
                'fixed_allowance' => 1000000,
                'tax_status' => 'TK/0',
                'marketing_target' => 250000000,
                'commission_rate' => 0.01,
                'production_kpi_rate' => 0,
                'bank_account_name' => 'Marketing Demo',
                'settings' => json_encode(['device_id' => 'MKT-DEVICE-001', 'tracking_phone' => '081300001111']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $branchId = DB::table('marketing_branches')->where('code', 'MKT-JKT')->value('id');

        DB::table('marketing_trackings')->updateOrInsert(
            ['employee_profile_id' => $profileId, 'tracked_at' => $now->copy()->startOfDay()->addHours(9)],
            [
                'user_id' => $userId,
                'marketing_branch_id' => $branchId,
                'device_id' => 'MKT-DEVICE-001',
                'phone_number' => '081300001111',
                'latitude' => -6.1753924,
                'longitude' => 106.8271528,
                'address' => 'Jakarta Pusat',
                'activity_type' => 'customer_visit',
                'customer_name' => 'PT Sinar Utama',
                'pipeline_amount' => 85000000,
                'status' => 'verified',
                'notes' => 'Visit demo tracking marketing.',
                'metadata' => json_encode(['source' => 'migration_demo']),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};

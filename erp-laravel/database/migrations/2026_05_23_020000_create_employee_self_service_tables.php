<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('employee_no')->unique();
            $table->string('department')->nullable()->index();
            $table->string('position')->nullable();
            $table->string('job_level')->nullable();
            $table->string('employment_status')->default('active')->index();
            $table->date('join_date')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('work_location')->nullable();
            $table->string('phone')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->json('personal_data')->nullable();
            $table->json('education_data')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->json('job_data')->nullable();
            $table->json('experience_data')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date')->index();
            $table->timestamp('clock_in_at')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->text('clock_in_address')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->text('clock_out_address')->nullable();
            $table->string('status')->default('present')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'attendance_date']);
        });

        Schema::create('employee_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('submitted')->index();
            $table->string('title');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('total_days', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_reimbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category')->index();
            $table->string('title');
            $table->decimal('amount', 16, 2)->default(0);
            $table->date('expense_date')->nullable();
            $table->text('description')->nullable();
            $table->string('receipt_reference')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_month')->index();
            $table->decimal('basic_salary', 16, 2)->default(0);
            $table->decimal('allowance', 16, 2)->default(0);
            $table->decimal('deduction', 16, 2)->default(0);
            $table->decimal('reimbursement', 16, 2)->default(0);
            $table->decimal('net_salary', 16, 2)->default(0);
            $table->string('status')->default('draft')->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('components')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_month']);
        });

        Schema::create('employee_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('category')->default('info')->index();
            $table->string('audience')->default('all')->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->seedDemoEmployee();
    }

    public function down()
    {
        Schema::dropIfExists('employee_announcements');
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('employee_reimbursements');
        Schema::dropIfExists('employee_requests');
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('employee_profiles');
    }

    private function seedDemoEmployee(): void
    {
        $now = now();
        $email = 'employee@horizon.test';

        $userId = DB::table('users')->where('email', $email)->value('id');

        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Employee Demo',
                'email' => $email,
                'email_verified_at' => $now,
                'role' => 'employee',
                'account_status' => 'active',
                'activated_at' => $now,
                'password' => Hash::make('password123'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('users')->where('id', $userId)->update([
                'role' => 'employee',
                'account_status' => 'active',
                'email_verified_at' => DB::raw('COALESCE(email_verified_at, NOW())'),
                'activated_at' => DB::raw('COALESCE(activated_at, NOW())'),
                'updated_at' => $now,
            ]);
        }

        $profileId = DB::table('employee_profiles')->where('user_id', $userId)->value('id');

        if (! $profileId) {
            $profileId = DB::table('employee_profiles')->insertGetId([
                'user_id' => $userId,
                'employee_no' => 'EMP-2605-006',
                'department' => 'IT',
                'position' => 'Employee Self Service Specialist',
                'job_level' => 'Staff',
                'employment_status' => 'active',
                'join_date' => $now->copy()->subMonths(9)->toDateString(),
                'manager_name' => 'Nadia Putri',
                'work_location' => 'Jakarta',
                'phone' => '081234567890',
                'birth_place' => 'Jakarta',
                'birth_date' => '1996-05-12',
                'gender' => 'Pria',
                'address' => 'Jakarta Selatan',
                'bank_name' => 'Mandiri',
                'bank_account_number' => '1234567890',
                'bank_account_name' => 'Employee Demo',
                'personal_data' => json_encode(['identity_no' => '3174000000000001', 'marital_status' => 'Single']),
                'education_data' => json_encode(['level' => 'S1', 'institution' => 'Universitas Indonesia', 'major' => 'Sistem Informasi', 'graduation_year' => '2018']),
                'emergency_contact' => json_encode(['name' => 'Dewi', 'relation' => 'Keluarga', 'phone' => '081299998888']),
                'job_data' => json_encode(['contract_type' => 'Permanent', 'shift' => 'Office Hour']),
                'experience_data' => json_encode(['summary' => 'ERP operations, HRIS, dan support business process.']),
                'settings' => json_encode(['preferred_channel' => 'fcm']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('employee_payrolls')->updateOrInsert(
            ['user_id' => $userId, 'period_month' => $now->copy()->startOfMonth()->toDateString()],
            [
                'employee_profile_id' => $profileId,
                'basic_salary' => 7500000,
                'allowance' => 1250000,
                'deduction' => 250000,
                'reimbursement' => 350000,
                'net_salary' => 8850000,
                'status' => 'released',
                'components' => json_encode(['bpjs' => 150000, 'tax' => 100000, 'meal_allowance' => 500000]),
                'notes' => 'Payroll demo untuk slip gaji employee.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('employee_announcements')->updateOrInsert(
            ['title' => 'Portal Employee Self Service Aktif'],
            [
                'body' => 'Karyawan dapat melakukan presensi lokasi, pengajuan cuti, reimburse, melihat slip gaji, dan memperbarui data pribadi melalui portal ini.',
                'category' => 'info',
                'audience' => 'all',
                'is_published' => true,
                'publish_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};

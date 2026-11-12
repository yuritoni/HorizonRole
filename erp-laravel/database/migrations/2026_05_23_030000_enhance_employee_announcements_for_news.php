<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employee_announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_announcements', 'kind')) {
                $table->string('kind')->default('announcement')->after('id')->index();
            }

            if (! Schema::hasColumn('employee_announcements', 'summary')) {
                $table->string('summary')->nullable()->after('title');
            }

            if (! Schema::hasColumn('employee_announcements', 'source_department')) {
                $table->string('source_department')->default('HR')->after('audience')->index();
            }

            if (! Schema::hasColumn('employee_announcements', 'priority')) {
                $table->string('priority')->default('normal')->after('source_department')->index();
            }

            if (! Schema::hasColumn('employee_announcements', 'scope_module')) {
                $table->string('scope_module')->default('all')->after('priority')->index();
            }

            if (! Schema::hasColumn('employee_announcements', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('is_published')->index();
            }

            if (! Schema::hasColumn('employee_announcements', 'requires_acknowledgement')) {
                $table->boolean('requires_acknowledgement')->default(false)->after('is_pinned')->index();
            }

            if (! Schema::hasColumn('employee_announcements', 'metadata')) {
                $table->json('metadata')->nullable()->after('created_by');
            }
        });

        Schema::create('employee_announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_announcement_id')->constrained('employee_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_announcement_id', 'user_id'], 'announcement_user_unique');
        });

        DB::table('employee_announcements')->whereNull('kind')->update(['kind' => 'announcement']);
        DB::table('employee_announcements')->whereNull('source_department')->update(['source_department' => 'HR']);
        DB::table('employee_announcements')->whereNull('priority')->update(['priority' => 'normal']);
        DB::table('employee_announcements')->whereNull('scope_module')->update(['scope_module' => 'all']);

        $now = now();
        DB::table('employee_announcements')->updateOrInsert(
            ['title' => 'Berita HR: Kalender payroll dan cuti bersama'],
            [
                'kind' => 'news',
                'summary' => 'HR menerbitkan update jadwal payroll, cuti bersama, dan kanal pengajuan employee.',
                'body' => 'Mulai periode ini, seluruh informasi HR, berita perusahaan, jadwal payroll, dan cuti bersama akan dipublikasikan melalui menu Pengumuman & Berita. Pegawai akan menerima notifikasi otomatis saat berita HR diterbitkan.',
                'category' => 'HR',
                'audience' => 'all',
                'source_department' => 'HR',
                'priority' => 'important',
                'scope_module' => 'employee',
                'is_published' => true,
                'is_pinned' => true,
                'requires_acknowledgement' => true,
                'publish_at' => $now,
                'metadata' => json_encode(['seed' => true, 'origin' => 'migration']),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down()
    {
        Schema::dropIfExists('employee_announcement_reads');

        Schema::table('employee_announcements', function (Blueprint $table) {
            foreach (['kind', 'summary', 'source_department', 'priority', 'scope_module', 'is_pinned', 'requires_acknowledgement', 'metadata'] as $column) {
                if (Schema::hasColumn('employee_announcements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

namespace App\Support;

use App\Models\Privilege;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrivilegeRegistry
{
    public static function roles(): array
    {
        return [
            'admin' => 'Administrator',
            'member' => 'Member',
            'user' => 'User Viewer',
            'employee' => 'Employee Self Service',
        ];
    }

    public static function all(): array
    {
        $privileges = [
            [
                'key' => 'dashboard.view',
                'group' => 'Aplikasi',
                'label' => 'Lihat Dashboard',
                'description' => 'Membuka executive dashboard.',
                'module' => null,
                'action' => 'view',
                'menu_key' => 'dashboard',
            ],
            [
                'key' => 'notifications.view',
                'group' => 'Aplikasi',
                'label' => 'Lihat Notifikasi',
                'description' => 'Membuka inbox notifikasi dan mendaftarkan FCM token.',
                'module' => null,
                'action' => 'view',
                'menu_key' => 'notifications',
            ],
            [
                'key' => 'reports.view',
                'group' => 'Aplikasi',
                'label' => 'Report Center',
                'description' => 'Membuka report interaktif untuk modul ERP dan tabel sistem.',
                'module' => null,
                'action' => 'view',
                'menu_key' => 'reports',
            ],
            [
                'key' => 'announcements.view',
                'group' => 'Pengumuman & Berita',
                'label' => 'Lihat Pengumuman & Berita',
                'description' => 'Membuka berita HR, pengumuman perusahaan, dan informasi lintas sistem.',
                'module' => null,
                'action' => 'view',
                'menu_key' => 'announcements',
            ],
            [
                'key' => 'announcements.manage',
                'group' => 'Pengumuman & Berita',
                'label' => 'Kelola Pengumuman & Berita',
                'description' => 'HR membuat, mempublikasikan, dan memantau pengumuman atau berita untuk pegawai.',
                'module' => null,
                'action' => 'manage',
                'menu_key' => 'announcement-management',
            ],
            [
                'key' => 'calendar.view',
                'group' => 'Kalender',
                'label' => 'Lihat Kalender ERP',
                'description' => 'Membuka kalender terintegrasi berisi event, berita, pengumuman, cuti, lembur, dan ulang tahun pegawai.',
                'module' => null,
                'action' => 'view',
                'menu_key' => 'calendar',
            ],
            [
                'key' => 'calendar.manage',
                'group' => 'Kalender',
                'label' => 'Kelola Agenda Kalender',
                'description' => 'Membuat dan menghapus agenda manual pada kalender terpusat ERP.',
                'module' => null,
                'action' => 'manage',
                'menu_key' => 'calendar-management',
            ],
            [
                'key' => 'payments.view',
                'group' => 'Pembayaran',
                'label' => 'Lihat Pembayaran',
                'description' => 'Melihat riwayat payment Midtrans.',
                'module' => null,
                'action' => 'view',
                'menu_key' => 'payments',
            ],
            [
                'key' => 'payments.create',
                'group' => 'Pembayaran',
                'label' => 'Buat Pembayaran',
                'description' => 'Memulai checkout pembayaran Midtrans.',
                'module' => null,
                'action' => 'create',
                'menu_key' => null,
            ],
            [
                'key' => 'users.manage',
                'group' => 'Administrasi',
                'label' => 'Management User',
                'description' => 'Mengubah role dan status aktivasi user.',
                'module' => null,
                'action' => 'manage',
                'menu_key' => 'users',
            ],
            [
                'key' => 'privileges.manage',
                'group' => 'Administrasi',
                'label' => 'Management Privilege',
                'description' => 'Mengatur akses menu, modul, aksi, dan override user.',
                'module' => null,
                'action' => 'manage',
                'menu_key' => 'privileges',
            ],
            [
                'key' => 'master-data.manage',
                'group' => 'Administrasi',
                'label' => 'Management Master Data',
                'description' => 'Mengatur master data user reference, role, menu, type, employer, rekening, account, product, dan opsi modul.',
                'module' => null,
                'action' => 'manage',
                'menu_key' => 'master-data',
            ],
            [
                'key' => 'employee.portal',
                'group' => 'Employee Self Service',
                'label' => 'Portal Employee',
                'description' => 'Akses presensi, profil pekerja, cuti, payroll, slip gaji, reimburse, dan informasi karyawan.',
                'module' => null,
                'action' => 'view',
                'menu_key' => 'employee',
            ],
            [
                'key' => 'employee.manage',
                'group' => 'Employee Self Service',
                'label' => 'Management Employee',
                'description' => 'Mengelola user employee, approval pengajuan, reimburse, payroll, dan informasi pekerja.',
                'module' => null,
                'action' => 'manage',
                'menu_key' => 'employee-management',
            ],
            [
                'key' => 'marketing.manage',
                'group' => 'Marketing',
                'label' => 'Management Marketing',
                'description' => 'Mengelola cabang marketing, pegawai marketing, device/no telepon, monitoring tracking, dan pipeline ke sales.',
                'module' => 'marketing',
                'action' => 'manage',
                'menu_key' => 'marketing-management',
            ],
        ];

        foreach (ErpModules::all() as $module => $config) {
            if ($module === 'hr') {
                continue;
            }

            foreach (self::moduleActions() as $action => $label) {
                if ($action === 'pay' && $module !== 'finance') {
                    continue;
                }

                $privileges[] = [
                    'key' => "modules.{$module}.{$action}",
                    'group' => 'Modul: '.$config['label'],
                    'label' => $label.' '.$config['label'],
                    'description' => self::moduleActionDescription($action, $config['label']),
                    'module' => $module,
                    'action' => $action,
                    'menu_key' => $action === 'view' ? 'module:'.$module : null,
                ];
            }
        }

        return $privileges;
    }

    public static function moduleActions(): array
    {
        return [
            'view' => 'Lihat',
            'create' => 'Tambah',
            'edit' => 'Edit',
            'advance' => 'Advance Workflow',
            'pay' => 'Proses Payment',
            'delete' => 'Hapus',
        ];
    }

    public static function defaultKeysForRole(string $role): array
    {
        $base = ['dashboard.view', 'notifications.view', 'reports.view', 'announcements.view', 'calendar.view'];

        if ($role === 'admin') {
            return collect(self::all())->pluck('key')->values()->all();
        }

        if ($role === 'member') {
            $keys = array_merge($base, ['payments.view', 'payments.create', 'employee.portal', 'employee.manage', 'marketing.manage', 'announcements.manage', 'calendar.manage']);

            foreach (array_keys(ErpModules::all()) as $module) {
                if ($module === 'hr') {
                    continue;
                }

                foreach (['view', 'create', 'edit', 'advance'] as $action) {
                    $keys[] = "modules.{$module}.{$action}";
                }
            }

            $keys[] = 'modules.finance.pay';

            return $keys;
        }

        if ($role === 'employee') {
            return ['notifications.view', 'employee.portal', 'announcements.view', 'calendar.view'];
        }

        $keys = array_merge($base, ['payments.view']);

        foreach (array_keys(ErpModules::all()) as $module) {
            if ($module === 'hr') {
                continue;
            }

            $keys[] = "modules.{$module}.view";
        }

        return $keys;
    }

    public static function isKnown(string $key): bool
    {
        return collect(self::all())->contains('key', $key);
    }

    public static function syncDefaults(bool $forceRoleSync = false): void
    {
        if (! Schema::hasTable('privileges')) {
            return;
        }

        foreach (self::all() as $privilege) {
            Privilege::updateOrCreate(['key' => $privilege['key']], $privilege);
        }

        if (! Schema::hasTable('role_privileges')) {
            return;
        }

        foreach (array_keys(self::roles()) as $role) {
            $hasExisting = DB::table('role_privileges')->where('role', $role)->exists();

            if ($hasExisting && ! $forceRoleSync) {
                $existingIds = DB::table('role_privileges')
                    ->where('role', $role)
                    ->pluck('privilege_id');
                $missingIds = Privilege::whereIn('key', self::defaultKeysForRole($role))
                    ->whereNotIn('id', $existingIds)
                    ->pluck('id');
                $rows = $missingIds->map(fn ($id) => [
                    'role' => $role,
                    'privilege_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                if ($rows) {
                    DB::table('role_privileges')->insert($rows);
                }

                continue;
            }

            DB::table('role_privileges')->where('role', $role)->delete();

            $privilegeIds = Privilege::whereIn('key', self::defaultKeysForRole($role))->pluck('id');
            $rows = $privilegeIds->map(fn ($id) => [
                'role' => $role,
                'privilege_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($rows) {
                DB::table('role_privileges')->insert($rows);
            }
        }
    }

    private static function moduleActionDescription(string $action, string $label): string
    {
        return match ($action) {
            'view' => 'Menampilkan menu dan data modul '.$label.'.',
            'create' => 'Menambahkan record baru pada modul '.$label.'.',
            'edit' => 'Mengubah record pada modul '.$label.'.',
            'advance' => 'Memindahkan status workflow pada modul '.$label.'.',
            'pay' => 'Memulai proses pembayaran untuk record modul '.$label.'.',
            'delete' => 'Menghapus record pada modul '.$label.'.',
            default => 'Akses modul '.$label.'.',
        };
    }
}

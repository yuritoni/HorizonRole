<?php

namespace App\Support;

use App\Models\MasterDataGroup;
use App\Models\MasterDataItem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class MasterDataRegistry
{
    public static function groupTypes(): array
    {
        return [
            'system' => 'System',
            'menu' => 'Menu',
            'type' => 'Type',
            'module' => 'Module Option',
            'employee' => 'Employer / Employee',
            'rekening' => 'Rekening / Bank',
            'account' => 'Account / COA',
            'product' => 'Product',
            'partner' => 'Partner',
            'location' => 'Location',
            'communication' => 'Pengumuman / Berita',
        ];
    }

    public static function groups(): array
    {
        $groups = [
            [
                'key' => 'system.roles',
                'name' => 'Role',
                'type' => 'system',
                'description' => 'Master role login: admin, member, user, employee.',
                'items' => [
                    ['code' => 'admin', 'name' => 'Administrator', 'value' => 'admin'],
                    ['code' => 'member', 'name' => 'Member Operasional', 'value' => 'member'],
                    ['code' => 'user', 'name' => 'User Viewer', 'value' => 'user'],
                    ['code' => 'employee', 'name' => 'Employee Self Service', 'value' => 'employee'],
                ],
            ],
            [
                'key' => 'system.menus',
                'name' => 'Menu Aplikasi',
                'type' => 'menu',
                'description' => 'Referensi menu utama dan menu pengaturan sistem.',
                'items' => [
                    ['code' => 'dashboard', 'name' => 'Dashboard', 'value' => 'dashboard'],
                    ['code' => 'payments', 'name' => 'Pembayaran', 'value' => 'payments'],
                    ['code' => 'reports', 'name' => 'Report Center', 'value' => 'reports'],
                    ['code' => 'notifications', 'name' => 'Notifikasi', 'value' => 'notifications'],
                    ['code' => 'announcements', 'name' => 'Pengumuman & Berita', 'value' => 'announcements'],
                    ['code' => 'announcement-management', 'name' => 'Kelola Pengumuman & Berita', 'value' => 'announcement-management'],
                    ['code' => 'employee', 'name' => 'Employee Portal', 'value' => 'employee'],
                    ['code' => 'employee-management', 'name' => 'Employee Management', 'value' => 'employee-management'],
                    ['code' => 'marketing-management', 'name' => 'Marketing Management', 'value' => 'marketing-management'],
                    ['code' => 'sales-shipping', 'name' => 'Pengiriman Penjualan', 'value' => 'sales-shipping'],
                    ['code' => 'product-management', 'name' => 'Product Management', 'value' => 'product-management'],
                    ['code' => 'users', 'name' => 'Pengaturan User', 'value' => 'users'],
                    ['code' => 'privileges', 'name' => 'Privilege', 'value' => 'privileges'],
                    ['code' => 'master-data', 'name' => 'Master Data', 'value' => 'master-data'],
                ],
            ],
            [
                'key' => 'system.record_types',
                'name' => 'Tipe Data Sistem',
                'type' => 'type',
                'description' => 'Klasifikasi master data lintas modul.',
                'items' => [
                    ['code' => 'customer', 'name' => 'Customer', 'value' => 'Customer'],
                    ['code' => 'vendor', 'name' => 'Vendor', 'value' => 'Vendor'],
                    ['code' => 'employee', 'name' => 'Employee', 'value' => 'Employee'],
                    ['code' => 'product', 'name' => 'Product', 'value' => 'Product'],
                    ['code' => 'account', 'name' => 'Account', 'value' => 'Account'],
                    ['code' => 'rekening', 'name' => 'Rekening', 'value' => 'Rekening'],
                ],
            ],
            [
                'key' => 'master.employees',
                'name' => 'Employer / Employee',
                'type' => 'employee',
                'description' => 'Pegawai, owner proses, buyer, HR owner, dan PIC.',
                'items' => [
                    ['code' => 'nadia', 'name' => 'Nadia Putri', 'value' => 'Nadia'],
                    ['code' => 'arman', 'name' => 'Arman Pratama', 'value' => 'Arman'],
                    ['code' => 'tari', 'name' => 'Tari Ananda', 'value' => 'Tari'],
                    ['code' => 'mira', 'name' => 'Mira Kartika', 'value' => 'Mira'],
                    ['code' => 'fadil', 'name' => 'Fadil Maulana', 'value' => 'Fadil'],
                    ['code' => 'bayu', 'name' => 'Bayu', 'value' => 'Bayu'],
                ],
            ],
            [
                'key' => 'master.bank_accounts',
                'name' => 'Rekening Bank',
                'type' => 'rekening',
                'description' => 'Rekening pembayaran, collection, payroll, dan settlement.',
                'items' => [
                    ['code' => 'bca-operasional', 'name' => 'BCA Operasional', 'value' => '1001 Kas Operasional'],
                    ['code' => 'mandiri-payroll', 'name' => 'Mandiri Payroll', 'value' => '1002 Bank Payroll'],
                    ['code' => 'bni-vendor', 'name' => 'BNI Vendor Payment', 'value' => '1003 Bank Vendor'],
                ],
            ],
            [
                'key' => 'master.accounts',
                'name' => 'Chart of Account',
                'type' => 'account',
                'description' => 'Master rekening akuntansi dan ledger.',
                'items' => [
                    ['code' => '1001', 'name' => '1001 Kas Operasional', 'value' => '1001 Kas Operasional'],
                    ['code' => '1102', 'name' => '1102 Piutang Usaha', 'value' => '1102 Piutang Usaha'],
                    ['code' => '2101', 'name' => '2101 Utang Vendor', 'value' => '2101 Utang Vendor'],
                    ['code' => '5101', 'name' => '5101 COGS', 'value' => '5101 COGS'],
                ],
            ],
            [
                'key' => 'master.products',
                'name' => 'Product',
                'type' => 'product',
                'description' => 'Produk, material, SKU, dan item jasa.',
                'items' => [
                    ['code' => 'finished-product-a', 'name' => 'Finished Product A', 'value' => 'Finished Product A'],
                    ['code' => 'finished-product-b', 'name' => 'Finished Product B', 'value' => 'Finished Product B'],
                    ['code' => 'raw-material-steel', 'name' => 'Raw Material Steel', 'value' => 'Raw Material Steel'],
                    ['code' => 'facility-service', 'name' => 'Facility Service', 'value' => 'Facility Service'],
                ],
            ],
            [
                'key' => 'master.customers',
                'name' => 'Customer',
                'type' => 'partner',
                'description' => 'Customer dan prospek sales/CRM.',
                'items' => [
                    ['code' => 'pt-sinar-utama', 'name' => 'PT Sinar Utama', 'value' => 'PT Sinar Utama'],
                    ['code' => 'cv-karya-logistik', 'name' => 'CV Karya Logistik', 'value' => 'CV Karya Logistik'],
                    ['code' => 'retail-nusantara', 'name' => 'Retail Nusantara', 'value' => 'Retail Nusantara'],
                ],
            ],
            [
                'key' => 'master.vendors',
                'name' => 'Vendor',
                'type' => 'partner',
                'description' => 'Vendor procurement dan AP.',
                'items' => [
                    ['code' => 'pt-baja-sentosa', 'name' => 'PT Baja Sentosa', 'value' => 'PT Baja Sentosa'],
                    ['code' => 'global-it-supply', 'name' => 'Global IT Supply', 'value' => 'Global IT Supply'],
                    ['code' => 'multi-teknik', 'name' => 'Multi Teknik', 'value' => 'Multi Teknik'],
                ],
            ],
            [
                'key' => 'master.departments',
                'name' => 'Departemen',
                'type' => 'employee',
                'description' => 'Departemen HR, payroll, dan approval.',
                'items' => [
                    ['code' => 'finance', 'name' => 'Finance', 'value' => 'Finance'],
                    ['code' => 'accounting', 'name' => 'Accounting', 'value' => 'Accounting'],
                    ['code' => 'sales', 'name' => 'Sales', 'value' => 'Sales'],
                    ['code' => 'marketing', 'name' => 'Marketing', 'value' => 'Marketing'],
                    ['code' => 'operations', 'name' => 'Operations', 'value' => 'Operations'],
                    ['code' => 'hr', 'name' => 'HR', 'value' => 'HR'],
                    ['code' => 'it', 'name' => 'IT', 'value' => 'IT'],
                ],
            ],
            [
                'key' => 'master.locations',
                'name' => 'Lokasi',
                'type' => 'location',
                'description' => 'Lokasi gudang, cabang, aset, dan site.',
                'items' => [
                    ['code' => 'jakarta', 'name' => 'Jakarta', 'value' => 'Jakarta'],
                    ['code' => 'surabaya', 'name' => 'Surabaya', 'value' => 'Surabaya'],
                    ['code' => 'bandung', 'name' => 'Bandung', 'value' => 'Bandung'],
                    ['code' => 'medan', 'name' => 'Medan', 'value' => 'Medan'],
                    ['code' => 'data-center', 'name' => 'Data Center', 'value' => 'Data Center'],
                ],
            ],
            [
                'key' => 'employee.request_types',
                'name' => 'Tipe Pengajuan Employee',
                'type' => 'employee',
                'description' => 'Tipe pengajuan di portal employee.',
                'items' => [
                    ['code' => 'leave', 'name' => 'Cuti', 'value' => 'leave'],
                    ['code' => 'permit', 'name' => 'Izin', 'value' => 'permit'],
                    ['code' => 'sick', 'name' => 'Sakit', 'value' => 'sick'],
                    ['code' => 'overtime', 'name' => 'Lembur', 'value' => 'overtime'],
                    ['code' => 'manual-attendance', 'name' => 'Presensi Manual', 'value' => 'manual_attendance'],
                    ['code' => 'training', 'name' => 'Pelatihan', 'value' => 'training'],
                    ['code' => 'business-trip', 'name' => 'Perjalanan Dinas', 'value' => 'business_trip'],
                    ['code' => 'data-change', 'name' => 'Perubahan Data', 'value' => 'data_change'],
                    ['code' => 'information', 'name' => 'Informasi', 'value' => 'information'],
                ],
            ],
            [
                'key' => 'employee.leave_types',
                'name' => 'Tipe Cuti',
                'type' => 'employee',
                'description' => 'Referensi cuti dan izin HRIS.',
                'items' => [
                    ['code' => 'annual-leave', 'name' => 'Annual Leave', 'value' => 'Annual Leave'],
                    ['code' => 'sick-leave', 'name' => 'Sick Leave', 'value' => 'Sick Leave'],
                    ['code' => 'special-permit', 'name' => 'Special Permit', 'value' => 'Special Permit'],
                    ['code' => 'unpaid-leave', 'name' => 'Unpaid Leave', 'value' => 'Unpaid Leave'],
                ],
            ],
            [
                'key' => 'employee.reimbursement_categories',
                'name' => 'Kategori Reimburse',
                'type' => 'employee',
                'description' => 'Kategori reimburse employee dan payroll.',
                'items' => [
                    ['code' => 'transport', 'name' => 'Transport', 'value' => 'Transport'],
                    ['code' => 'medical', 'name' => 'Medical', 'value' => 'Medical'],
                    ['code' => 'meal', 'name' => 'Meal', 'value' => 'Meal'],
                    ['code' => 'office-supplies', 'name' => 'Office Supplies', 'value' => 'Office Supplies'],
                    ['code' => 'travel', 'name' => 'Travel', 'value' => 'Travel'],
                ],
            ],
            [
                'key' => 'employee.payroll_statuses',
                'name' => 'Status Payroll',
                'type' => 'employee',
                'description' => 'Status proses payroll dan slip gaji.',
                'items' => [
                    ['code' => 'draft', 'name' => 'Draft', 'value' => 'draft'],
                    ['code' => 'released', 'name' => 'Released', 'value' => 'released'],
                    ['code' => 'paid', 'name' => 'Paid', 'value' => 'paid'],
                ],
            ],
            [
                'key' => 'employee.employment_statuses',
                'name' => 'Status Pekerja',
                'type' => 'employee',
                'description' => 'Status pekerja pada profil HRIS.',
                'items' => [
                    ['code' => 'active', 'name' => 'Active', 'value' => 'active'],
                    ['code' => 'on-leave', 'name' => 'On Leave', 'value' => 'on_leave'],
                    ['code' => 'recruiting', 'name' => 'Recruiting', 'value' => 'recruiting'],
                    ['code' => 'probation', 'name' => 'Probation', 'value' => 'probation'],
                    ['code' => 'contract', 'name' => 'Contract', 'value' => 'contract'],
                    ['code' => 'inactive', 'name' => 'Inactive', 'value' => 'inactive'],
                ],
            ],
            [
                'key' => 'announcement.kinds',
                'name' => 'Jenis Pengumuman',
                'type' => 'communication',
                'description' => 'Jenis konten komunikasi internal dari HR.',
                'items' => [
                    ['code' => 'announcement', 'name' => 'Pengumuman', 'value' => 'announcement'],
                    ['code' => 'news', 'name' => 'Berita', 'value' => 'news'],
                ],
            ],
            [
                'key' => 'announcement.categories',
                'name' => 'Kategori Pengumuman & Berita',
                'type' => 'communication',
                'description' => 'Kategori berita HR dan pengumuman perusahaan.',
                'items' => [
                    ['code' => 'hr', 'name' => 'HR', 'value' => 'HR'],
                    ['code' => 'policy', 'name' => 'Kebijakan', 'value' => 'Kebijakan'],
                    ['code' => 'payroll', 'name' => 'Payroll', 'value' => 'Payroll'],
                    ['code' => 'benefit', 'name' => 'Benefit', 'value' => 'Benefit'],
                    ['code' => 'operations', 'name' => 'Operasional', 'value' => 'Operasional'],
                    ['code' => 'event', 'name' => 'Event', 'value' => 'Event'],
                ],
            ],
            [
                'key' => 'announcement.priorities',
                'name' => 'Prioritas Pengumuman',
                'type' => 'communication',
                'description' => 'Level prioritas pengumuman dan berita internal.',
                'items' => [
                    ['code' => 'normal', 'name' => 'Normal', 'value' => 'normal'],
                    ['code' => 'important', 'name' => 'Penting', 'value' => 'important'],
                    ['code' => 'urgent', 'name' => 'Mendesak', 'value' => 'urgent'],
                ],
            ],
        ];

        foreach (ErpModules::all() as $module => $config) {
            $groups[] = [
                'key' => self::moduleGroupKey($module, 'status'),
                'name' => 'Status '.$config['label'],
                'type' => 'module',
                'module' => $module,
                'description' => 'Status workflow modul '.$config['label'].'.',
                'items' => collect($config['statuses'])->map(fn ($status, $index) => [
                    'code' => Str::slug($status),
                    'name' => $status,
                    'value' => $status,
                    'sort_order' => $index + 1,
                ])->all(),
            ];

            foreach ($config['fields'] as $field) {
                if (($field['type'] ?? null) !== 'select' || empty($field['options'])) {
                    continue;
                }

                $groups[] = [
                    'key' => self::moduleGroupKey($module, $field['name']),
                    'name' => $config['label'].' - '.$field['label'],
                    'type' => 'module',
                    'module' => $module,
                    'description' => 'Opsi '.$field['label'].' untuk modul '.$config['label'].'.',
                    'items' => collect($field['options'])->map(fn ($option, $index) => [
                        'code' => Str::slug($option),
                        'name' => $option,
                        'value' => $option,
                        'sort_order' => $index + 1,
                    ])->all(),
                ];
            }
        }

        return $groups;
    }

    public static function syncDefaults(): void
    {
        if (! Schema::hasTable('master_data_groups') || ! Schema::hasTable('master_data_items')) {
            return;
        }

        foreach (self::groups() as $index => $definition) {
            $group = MasterDataGroup::updateOrCreate(
                ['key' => $definition['key']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'] ?? 'general',
                    'module' => $definition['module'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );

            foreach (($definition['items'] ?? []) as $itemIndex => $item) {
                MasterDataItem::updateOrCreate(
                    [
                        'master_data_group_id' => $group->id,
                        'code' => $item['code'] ?? Str::slug($item['name']),
                    ],
                    [
                        'name' => $item['name'],
                        'value' => $item['value'] ?? $item['name'],
                        'description' => $item['description'] ?? null,
                        'extra' => $item['extra'] ?? null,
                        'is_active' => true,
                        'sort_order' => $item['sort_order'] ?? ($itemIndex + 1),
                    ]
                );
            }
        }
    }

    public static function applyToModule(string $module, array $config): array
    {
        $config['statuses'] = self::optionsForGroup(self::moduleGroupKey($module, 'status'), $config['statuses'] ?? []);

        foreach ($config['fields'] as $index => $field) {
            if (($field['type'] ?? null) !== 'select') {
                continue;
            }

            $config['fields'][$index]['options'] = $field['name'] === 'status'
                ? $config['statuses']
                : self::optionsForGroup(self::moduleGroupKey($module, $field['name']), $field['options'] ?? []);
        }

        return $config;
    }

    public static function optionsForGroup(string $key, array $fallback = []): array
    {
        if (! Schema::hasTable('master_data_groups') || ! Schema::hasTable('master_data_items')) {
            return $fallback;
        }

        $group = MasterDataGroup::where('key', $key)->where('is_active', true)->first();

        if (! $group) {
            return $fallback;
        }

        $options = $group->activeItems()
            ->get()
            ->map(fn (MasterDataItem $item) => $item->displayValue())
            ->filter()
            ->values()
            ->all();

        return $options ?: $fallback;
    }

    public static function moduleGroupKey(string $module, string $field): string
    {
        return 'module.'.$module.'.'.$field;
    }
}

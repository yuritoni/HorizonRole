<?php

namespace App\Support;

use App\Models\User;

class ReportCatalog
{
    public static function forUser(User $user): array
    {
        $datasets = [];

        $accessibleModules = collect(ErpModules::all())
            ->filter(fn ($config, $module) => $module !== 'hr' && $user->hasPrivilege("modules.{$module}.view"));

        if ($accessibleModules->isNotEmpty()) {
            $datasets['erp:all'] = self::erpDataset(
                'erp:all',
                'Semua Modul ERP',
                'Konsolidasi lintas modul ERP sesuai akses user.',
                null,
                $accessibleModules->keys()->all()
            );

            $datasets['erp:record_links'] = [
                'key' => 'erp:record_links',
                'label' => 'Relasi Lintas Modul',
                'group' => 'Modul ERP',
                'description' => 'Pemetaan data yang tersinkron antar modul ERP.',
                'table' => 'erp_record_links',
                'columns' => [
                    'source_record_id' => 'Source ID',
                    'target_record_id' => 'Target ID',
                    'relation_type' => 'Jenis Relasi',
                    'status' => 'Status',
                    'created_by' => 'Creator',
                    'created_at' => 'Dibuat',
                    'updated_at' => 'Diupdate',
                ],
                'searchable' => ['relation_type', 'status'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'status_column' => 'status',
                'groupable' => ['relation_type' => 'Jenis Relasi', 'status' => 'Status', 'source_record_id' => 'Source', 'target_record_id' => 'Target'],
                'default_group' => 'relation_type',
                'default_sort' => 'created_at',
            ];
        }

        foreach ($accessibleModules as $module => $config) {
            $datasets["module:{$module}"] = self::erpDataset(
                "module:{$module}",
                $config['label'],
                $config['description'],
                $module,
                [$module]
            );
        }

        if ($user->hasPrivilege('payments.view')) {
            $datasets['system:payments'] = [
                'key' => 'system:payments',
                'label' => 'Pembayaran',
                'group' => 'Tabel Sistem',
                'description' => 'Riwayat transaksi Midtrans dan status settlement.',
                'table' => 'payments',
                'columns' => [
                    'order_id' => 'Order ID',
                    'provider' => 'Provider',
                    'amount' => 'Amount',
                    'status' => 'Status',
                    'payment_type' => 'Metode',
                    'fraud_status' => 'Fraud',
                    'paid_at' => 'Paid At',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['order_id', 'provider', 'status', 'payment_type', 'transaction_id'],
                'date_columns' => ['created_at' => 'Dibuat', 'paid_at' => 'Paid At'],
                'amount_column' => 'amount',
                'status_column' => 'status',
                'groupable' => ['status' => 'Status', 'provider' => 'Provider', 'payment_type' => 'Metode', 'fraud_status' => 'Fraud'],
                'default_group' => 'status',
                'default_sort' => 'created_at',
            ];
        }

        if ($user->hasPrivilege('modules.sales.view')) {
            $datasets['sales:shipments'] = [
                'key' => 'sales:shipments',
                'label' => 'Pengiriman Penjualan',
                'group' => 'Order to Cash',
                'description' => 'Shipment sales, kurir, resi, ongkir, biaya kurir, settlement, dan status delivery.',
                'table' => 'sales_shipments',
                'columns' => [
                    'shipment_no' => 'Shipment',
                    'sales_record_id' => 'Sales ID',
                    'courier_provider_id' => 'Kurir ID',
                    'service_code' => 'Layanan',
                    'tracking_number' => 'Resi',
                    'recipient_name' => 'Penerima',
                    'destination' => 'Tujuan',
                    'customer_shipping_charge' => 'Ongkir Customer',
                    'courier_cost' => 'Biaya Kurir',
                    'insurance_cost' => 'Asuransi',
                    'payment_policy' => 'Policy',
                    'settlement_status' => 'Settlement',
                    'status' => 'Status',
                    'delivered_at' => 'Delivered',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['shipment_no', 'service_code', 'tracking_number', 'recipient_name', 'destination', 'payment_policy', 'settlement_status', 'status'],
                'date_columns' => ['created_at' => 'Dibuat', 'pickup_scheduled_at' => 'Pickup', 'estimated_delivery_at' => 'Estimasi', 'delivered_at' => 'Delivered'],
                'amount_column' => 'customer_shipping_charge',
                'status_column' => 'status',
                'groupable' => ['status' => 'Status', 'settlement_status' => 'Settlement', 'payment_policy' => 'Policy', 'courier_provider_id' => 'Kurir'],
                'default_group' => 'status',
                'default_sort' => 'created_at',
            ];

            $datasets['sales:shipment_tracking'] = [
                'key' => 'sales:shipment_tracking',
                'label' => 'Tracking Pengiriman',
                'group' => 'Order to Cash',
                'description' => 'Timeline scan, webhook kurir, lokasi, dan perkembangan status shipment.',
                'table' => 'shipment_tracking_events',
                'columns' => [
                    'sales_shipment_id' => 'Shipment ID',
                    'event_code' => 'Event',
                    'status' => 'Status',
                    'description' => 'Keterangan',
                    'location' => 'Lokasi',
                    'event_at' => 'Waktu Event',
                    'source' => 'Sumber',
                    'external_id' => 'External ID',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['event_code', 'status', 'description', 'location', 'source', 'external_id'],
                'date_columns' => ['event_at' => 'Waktu Event', 'created_at' => 'Dibuat'],
                'status_column' => 'status',
                'groupable' => ['status' => 'Status', 'source' => 'Sumber', 'sales_shipment_id' => 'Shipment'],
                'default_group' => 'status',
                'default_sort' => 'event_at',
            ];

            $datasets['sales:courier_providers'] = [
                'key' => 'sales:courier_providers',
                'label' => 'Master Jasa Kurir',
                'group' => 'Order to Cash',
                'description' => 'Provider pengiriman, mode integrasi, COD, dan status aktivasi.',
                'table' => 'courier_providers',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Kurir',
                    'integration_mode' => 'Integrasi',
                    'supports_cod' => 'COD',
                    'is_active' => 'Aktif',
                    'api_base_url' => 'API Base URL',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['code', 'name', 'integration_mode', 'api_base_url'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'status_column' => 'is_active',
                'groupable' => ['integration_mode' => 'Integrasi', 'supports_cod' => 'COD', 'is_active' => 'Aktif'],
                'default_group' => 'integration_mode',
                'default_sort' => 'created_at',
            ];
        }

        if ($user->hasPrivilege('modules.sales.view') || $user->hasPrivilege('modules.manufacturing.view') || $user->hasPrivilege('master-data.manage')) {
            $datasets['product:catalog'] = [
                'key' => 'product:catalog',
                'label' => 'Katalog Produk',
                'group' => 'Product Management',
                'description' => 'Master produk penjualan dan produksi, harga, standard cost, minimum stok, serta saldo tersedia.',
                'table' => 'products',
                'columns' => [
                    'sku' => 'SKU',
                    'name' => 'Produk',
                    'type' => 'Tipe',
                    'category' => 'Kategori',
                    'uom' => 'Satuan',
                    'sales_price' => 'Harga Jual',
                    'standard_cost' => 'Standard Cost',
                    'tax_rate' => 'PPN %',
                    'stock_on_hand' => 'On Hand',
                    'reserved_stock' => 'Reserved',
                    'min_stock' => 'Minimum',
                    'warehouse' => 'Gudang',
                    'is_active' => 'Aktif',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['sku', 'name', 'type', 'category', 'uom', 'barcode', 'warehouse'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'amount_column' => 'sales_price',
                'status_column' => 'is_active',
                'groupable' => ['type' => 'Tipe', 'category' => 'Kategori', 'warehouse' => 'Gudang', 'is_active' => 'Aktif', 'is_sellable' => 'Dijual'],
                'default_group' => 'type',
                'default_sort' => 'created_at',
            ];

            $datasets['product:bom_items'] = [
                'key' => 'product:bom_items',
                'label' => 'Bill of Materials',
                'group' => 'Product Management',
                'description' => 'Komponen dan kebutuhan per unit untuk produk yang diproduksi.',
                'table' => 'product_bom_items',
                'columns' => [
                    'product_id' => 'Produk Output ID',
                    'component_product_id' => 'Komponen ID',
                    'quantity_per_unit' => 'Qty / Unit',
                    'waste_percent' => 'Waste %',
                    'is_active' => 'Aktif',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['notes'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'status_column' => 'is_active',
                'groupable' => ['product_id' => 'Produk Output', 'component_product_id' => 'Komponen', 'is_active' => 'Aktif'],
                'default_group' => 'product_id',
                'default_sort' => 'created_at',
            ];

            $datasets['product:record_lines'] = [
                'key' => 'product:record_lines',
                'label' => 'Produk pada Transaksi',
                'group' => 'Product Management',
                'description' => 'Relasi item Sales, output Produksi, dan material requirement ke dokumen ERP.',
                'table' => 'erp_record_product_lines',
                'columns' => [
                    'erp_record_id' => 'Record ID',
                    'product_id' => 'Produk ID',
                    'line_type' => 'Jenis Baris',
                    'line_no' => 'No',
                    'quantity' => 'Qty',
                    'unit_price' => 'Harga / Cost',
                    'tax_rate' => 'PPN %',
                    'line_amount' => 'Nilai Baris',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['line_type'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'amount_column' => 'line_amount',
                'status_column' => 'line_type',
                'groupable' => ['line_type' => 'Jenis Baris', 'product_id' => 'Produk', 'erp_record_id' => 'Record'],
                'default_group' => 'line_type',
                'default_sort' => 'created_at',
            ];

            $datasets['product:stock_movements'] = [
                'key' => 'product:stock_movements',
                'label' => 'Ledger Pergerakan Stok',
                'group' => 'Product Management',
                'description' => 'Saldo awal, reservasi sales, barang keluar, konsumsi bahan, penerimaan produksi, dan adjustment.',
                'table' => 'product_stock_movements',
                'columns' => [
                    'product_id' => 'Produk ID',
                    'erp_record_id' => 'Record ID',
                    'movement_type' => 'Jenis Gerakan',
                    'direction' => 'Arah',
                    'quantity' => 'Qty',
                    'unit_cost' => 'Unit Cost',
                    'status' => 'Status',
                    'reference' => 'Referensi',
                    'occurred_at' => 'Waktu',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['movement_type', 'direction', 'status', 'reference', 'notes'],
                'date_columns' => ['occurred_at' => 'Waktu', 'created_at' => 'Dibuat'],
                'amount_column' => 'unit_cost',
                'status_column' => 'status',
                'groupable' => ['movement_type' => 'Jenis Gerakan', 'direction' => 'Arah', 'status' => 'Status', 'product_id' => 'Produk'],
                'default_group' => 'movement_type',
                'default_sort' => 'occurred_at',
            ];
        }

        if ($user->hasPrivilege('users.manage')) {
            $datasets['system:users'] = [
                'key' => 'system:users',
                'label' => 'User',
                'group' => 'Tabel Sistem',
                'description' => 'Akun, role, status aktivasi, dan verifikasi email.',
                'table' => 'users',
                'columns' => [
                    'name' => 'Nama',
                    'email' => 'Email',
                    'role' => 'Role',
                    'account_status' => 'Status Akun',
                    'email_verified_at' => 'Email Verified',
                    'activated_at' => 'Aktif',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['name', 'email', 'role', 'account_status'],
                'date_columns' => ['created_at' => 'Dibuat', 'email_verified_at' => 'Email Verified', 'activated_at' => 'Aktif'],
                'status_column' => 'account_status',
                'groupable' => ['role' => 'Role', 'account_status' => 'Status Akun'],
                'default_group' => 'role',
                'default_sort' => 'created_at',
            ];
        }

        if ($user->hasPrivilege('notifications.view')) {
            $datasets['system:notifications'] = [
                'key' => 'system:notifications',
                'label' => 'Notifikasi',
                'group' => 'Tabel Sistem',
                'description' => $user->hasPrivilege('users.manage') ? 'Semua notifikasi sistem.' : 'Notifikasi milik user login.',
                'table' => 'erp_notifications',
                'base' => $user->hasPrivilege('users.manage') ? [] : ['user_id' => $user->id],
                'columns' => [
                    'title' => 'Judul',
                    'type' => 'Tipe',
                    'severity' => 'Severity',
                    'module' => 'Modul',
                    'fcm_status' => 'FCM',
                    'read_at' => 'Dibaca',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['title', 'body', 'type', 'severity', 'module', 'fcm_status'],
                'date_columns' => ['created_at' => 'Dibuat', 'read_at' => 'Dibaca', 'fcm_sent_at' => 'FCM Sent'],
                'status_column' => 'severity',
                'groupable' => ['type' => 'Tipe', 'severity' => 'Severity', 'module' => 'Modul', 'fcm_status' => 'FCM'],
                'default_group' => 'severity',
                'default_sort' => 'created_at',
            ];

            $datasets['system:fcm_tokens'] = [
                'key' => 'system:fcm_tokens',
                'label' => 'Device FCM',
                'group' => 'Tabel Sistem',
                'description' => $user->hasPrivilege('users.manage') ? 'Token device FCM semua user.' : 'Token device FCM milik user login.',
                'table' => 'fcm_device_tokens',
                'base' => $user->hasPrivilege('users.manage') ? [] : ['user_id' => $user->id],
                'columns' => [
                    'user_id' => 'User ID',
                    'platform' => 'Platform',
                    'device_name' => 'Device',
                    'last_seen_at' => 'Last Seen',
                    'revoked_at' => 'Revoked',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['platform', 'device_name', 'user_agent'],
                'date_columns' => ['created_at' => 'Dibuat', 'last_seen_at' => 'Last Seen', 'revoked_at' => 'Revoked'],
                'groupable' => ['platform' => 'Platform', 'device_name' => 'Device'],
                'default_group' => 'platform',
                'default_sort' => 'last_seen_at',
            ];
        }

        if ($user->hasPrivilege('privileges.manage')) {
            $datasets['system:privileges'] = [
                'key' => 'system:privileges',
                'label' => 'Privilege',
                'group' => 'Tabel Sistem',
                'description' => 'Daftar permission, menu key, modul, dan aksi RBAC.',
                'table' => 'privileges',
                'columns' => [
                    'key' => 'Key',
                    'group' => 'Group',
                    'label' => 'Label',
                    'module' => 'Modul',
                    'action' => 'Aksi',
                    'menu_key' => 'Menu Key',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['key', 'group', 'label', 'description', 'module', 'action', 'menu_key'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'groupable' => ['group' => 'Group', 'module' => 'Modul', 'action' => 'Aksi', 'menu_key' => 'Menu Key'],
                'default_group' => 'group',
                'default_sort' => 'group',
            ];

            $datasets['system:role_privileges'] = [
                'key' => 'system:role_privileges',
                'label' => 'Role Privilege',
                'group' => 'Tabel Sistem',
                'description' => 'Mapping privilege default setiap role.',
                'table' => 'role_privileges',
                'columns' => [
                    'role' => 'Role',
                    'privilege_id' => 'Privilege ID',
                    'created_at' => 'Dibuat',
                    'updated_at' => 'Diupdate',
                ],
                'searchable' => ['role'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'groupable' => ['role' => 'Role', 'privilege_id' => 'Privilege ID'],
                'default_group' => 'role',
                'default_sort' => 'created_at',
            ];

            $datasets['system:user_privileges'] = [
                'key' => 'system:user_privileges',
                'label' => 'User Override',
                'group' => 'Tabel Sistem',
                'description' => 'Grant dan deny privilege khusus per user.',
                'table' => 'user_privileges',
                'columns' => [
                    'user_id' => 'User ID',
                    'privilege_id' => 'Privilege ID',
                    'effect' => 'Effect',
                    'notes' => 'Notes',
                    'created_at' => 'Dibuat',
                    'updated_at' => 'Diupdate',
                ],
                'searchable' => ['effect', 'notes'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'status_column' => 'effect',
                'groupable' => ['effect' => 'Effect', 'user_id' => 'User ID', 'privilege_id' => 'Privilege ID'],
                'default_group' => 'effect',
                'default_sort' => 'created_at',
            ];
        }

        if ($user->hasPrivilege('master-data.manage')) {
            $datasets['system:master_data_groups'] = [
                'key' => 'system:master_data_groups',
                'label' => 'Master Data Group',
                'group' => 'Tabel Sistem',
                'description' => 'Group master data untuk type, menu, module option, employer, rekening, account, product, partner, dan lokasi.',
                'table' => 'master_data_groups',
                'columns' => [
                    'key' => 'Key',
                    'name' => 'Nama',
                    'type' => 'Tipe',
                    'module' => 'Modul',
                    'is_system' => 'System',
                    'is_active' => 'Aktif',
                    'sort_order' => 'Urutan',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['key', 'name', 'type', 'module', 'description'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'status_column' => 'type',
                'groupable' => ['type' => 'Tipe', 'module' => 'Modul', 'is_active' => 'Aktif'],
                'default_group' => 'type',
                'default_sort' => 'sort_order',
            ];

            $datasets['system:master_data_items'] = [
                'key' => 'system:master_data_items',
                'label' => 'Master Data Item',
                'group' => 'Tabel Sistem',
                'description' => 'Item master data yang dipakai oleh form modul dan setting sistem.',
                'table' => 'master_data_items',
                'columns' => [
                    'master_data_group_id' => 'Group ID',
                    'parent_id' => 'Parent ID',
                    'code' => 'Code',
                    'name' => 'Nama',
                    'value' => 'Value',
                    'is_active' => 'Aktif',
                    'sort_order' => 'Urutan',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['code', 'name', 'value', 'description'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'status_column' => 'is_active',
                'groupable' => ['master_data_group_id' => 'Group', 'is_active' => 'Aktif'],
                'default_group' => 'master_data_group_id',
                'default_sort' => 'sort_order',
            ];
        }

        if ($user->hasPrivilege('employee.manage')) {
            $datasets['employee:profiles'] = [
                'key' => 'employee:profiles',
                'label' => 'SDM / Employee Profile',
                'group' => 'SDM & HRIS Employee',
                'description' => 'Satu master data pegawai untuk SDM, employee account, departemen, jabatan, rekening, payroll, dan status kerja.',
                'table' => 'employee_profiles',
                'columns' => [
                    'employee_no' => 'NIP',
                    'user_id' => 'User ID',
                    'manager_profile_id' => 'Atasan ID',
                    'department' => 'Departemen',
                    'position' => 'Jabatan',
                    'job_level' => 'Level',
                    'grade' => 'Grade',
                    'employment_status' => 'Status Kerja',
                    'join_date' => 'Tanggal Masuk',
                    'manager_name' => 'Atasan',
                    'work_location' => 'Lokasi',
                    'branch' => 'Cabang',
                    'base_salary' => 'Gaji Pokok',
                    'fixed_allowance' => 'Tunjangan Tetap',
                    'tax_status' => 'Status Pajak',
                    'bank_name' => 'Bank',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['employee_no', 'department', 'position', 'job_level', 'grade', 'employment_status', 'manager_name', 'work_location', 'branch', 'bank_name'],
                'date_columns' => ['created_at' => 'Dibuat', 'join_date' => 'Tanggal Masuk', 'updated_at' => 'Diupdate'],
                'amount_column' => 'base_salary',
                'status_column' => 'employment_status',
                'groupable' => ['department' => 'Departemen', 'position' => 'Jabatan', 'employment_status' => 'Status Kerja', 'work_location' => 'Lokasi', 'branch' => 'Cabang', 'manager_profile_id' => 'Atasan'],
                'default_group' => 'department',
                'default_sort' => 'created_at',
            ];

            $datasets['employee:attendances'] = [
                'key' => 'employee:attendances',
                'label' => 'Presensi Employee',
                'group' => 'SDM & HRIS Employee',
                'description' => 'Riwayat clock in/out beserta koordinat lokasi.',
                'table' => 'employee_attendances',
                'columns' => [
                    'user_id' => 'User ID',
                    'attendance_date' => 'Tanggal',
                    'source' => 'Source',
                    'device_id' => 'Device',
                    'phone_number' => 'No Telp',
                    'clock_in_at' => 'Clock In',
                    'clock_in_lat' => 'Lat In',
                    'clock_in_lng' => 'Lng In',
                    'clock_out_at' => 'Clock Out',
                    'clock_out_lat' => 'Lat Out',
                    'clock_out_lng' => 'Lng Out',
                    'status' => 'Status',
                    'approval_status' => 'Approval',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['source', 'device_id', 'phone_number', 'status', 'approval_status', 'clock_in_address', 'clock_out_address', 'notes'],
                'date_columns' => ['attendance_date' => 'Tanggal', 'clock_in_at' => 'Clock In', 'clock_out_at' => 'Clock Out', 'created_at' => 'Dibuat'],
                'status_column' => 'status',
                'groupable' => ['status' => 'Status', 'approval_status' => 'Approval', 'source' => 'Source', 'user_id' => 'User ID', 'attendance_date' => 'Tanggal'],
                'default_group' => 'status',
                'default_sort' => 'attendance_date',
            ];

            $datasets['employee:requests'] = [
                'key' => 'employee:requests',
                'label' => 'Pengajuan Employee',
                'group' => 'SDM & HRIS Employee',
                'description' => 'Pengajuan cuti, izin, lembur, informasi, dan perubahan data.',
                'table' => 'employee_requests',
                'columns' => [
                    'user_id' => 'User ID',
                    'type' => 'Tipe',
                    'status' => 'Status',
                    'title' => 'Judul',
                    'start_date' => 'Mulai',
                    'end_date' => 'Selesai',
                    'total_days' => 'Hari',
                    'reviewed_by' => 'Reviewer',
                    'reviewed_at' => 'Reviewed',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['type', 'status', 'title', 'description', 'review_note'],
                'date_columns' => ['created_at' => 'Dibuat', 'start_date' => 'Mulai', 'end_date' => 'Selesai', 'reviewed_at' => 'Reviewed'],
                'status_column' => 'status',
                'groupable' => ['type' => 'Tipe', 'status' => 'Status', 'user_id' => 'User ID', 'reviewed_by' => 'Reviewer'],
                'default_group' => 'status',
                'default_sort' => 'created_at',
            ];

            $datasets['employee:reimbursements'] = [
                'key' => 'employee:reimbursements',
                'label' => 'Reimburse Employee',
                'group' => 'SDM & HRIS Employee',
                'description' => 'Pengajuan reimburse, approval, dan status pembayaran.',
                'table' => 'employee_reimbursements',
                'columns' => [
                    'user_id' => 'User ID',
                    'category' => 'Kategori',
                    'title' => 'Judul',
                    'amount' => 'Nominal',
                    'expense_date' => 'Tanggal Biaya',
                    'status' => 'Status',
                    'reviewed_by' => 'Reviewer',
                    'reviewed_at' => 'Reviewed',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['category', 'title', 'description', 'receipt_reference', 'status', 'review_note'],
                'date_columns' => ['created_at' => 'Dibuat', 'expense_date' => 'Tanggal Biaya', 'reviewed_at' => 'Reviewed'],
                'amount_column' => 'amount',
                'status_column' => 'status',
                'groupable' => ['category' => 'Kategori', 'status' => 'Status', 'user_id' => 'User ID', 'reviewed_by' => 'Reviewer'],
                'default_group' => 'status',
                'default_sort' => 'created_at',
            ];

            $datasets['employee:payrolls'] = [
                'key' => 'employee:payrolls',
                'label' => 'Payroll Employee',
                'group' => 'SDM & HRIS Employee',
                'description' => 'Payroll, slip gaji, reimbursement payroll, dan status pembayaran.',
                'table' => 'employee_payrolls',
                'columns' => [
                    'user_id' => 'User ID',
                    'period_month' => 'Periode',
                    'basic_salary' => 'Gaji Pokok',
                    'allowance' => 'Tunjangan',
                    'gross_salary' => 'Gross Salary',
                    'overtime_amount' => 'Lembur',
                    'bonus' => 'Bonus',
                    'thr' => 'THR',
                    'tax' => 'Pajak',
                    'marketing_commission' => 'Komisi Marketing',
                    'production_incentive' => 'Insentif Produksi',
                    'deduction' => 'Potongan',
                    'reimbursement' => 'Reimburse',
                    'net_salary' => 'Net Salary',
                    'status' => 'Status',
                    'paid_at' => 'Paid At',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['status', 'notes'],
                'date_columns' => ['period_month' => 'Periode', 'paid_at' => 'Paid At', 'created_at' => 'Dibuat'],
                'amount_column' => 'net_salary',
                'status_column' => 'status',
                'groupable' => ['status' => 'Status', 'user_id' => 'User ID', 'period_month' => 'Periode'],
                'default_group' => 'status',
                'default_sort' => 'period_month',
            ];

            $datasets['employee:recruitment_candidates'] = [
                'key' => 'employee:recruitment_candidates',
                'label' => 'Recruitment & Onboarding',
                'group' => 'SDM & HRIS Employee',
                'description' => 'Kandidat, tahap meeting, wawancara, test, offering, onboarding, dan relasi record HRIS.',
                'table' => 'recruitment_candidates',
                'columns' => [
                    'candidate_name' => 'Kandidat',
                    'email' => 'Email',
                    'phone' => 'Telepon',
                    'department' => 'Departemen',
                    'position' => 'Jabatan',
                    'stage' => 'Tahap',
                    'meeting_at' => 'Meeting',
                    'interviewer' => 'Interviewer',
                    'test_score' => 'Nilai Test',
                    'onboarding_start_date' => 'Onboarding',
                    'hris_record_id' => 'HRIS ID',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['candidate_name', 'email', 'phone', 'department', 'position', 'stage', 'interviewer', 'notes'],
                'date_columns' => ['created_at' => 'Dibuat', 'meeting_at' => 'Meeting', 'onboarding_start_date' => 'Onboarding'],
                'status_column' => 'stage',
                'groupable' => ['stage' => 'Tahap', 'department' => 'Departemen', 'position' => 'Jabatan', 'interviewer' => 'Interviewer'],
                'default_group' => 'stage',
                'default_sort' => 'created_at',
            ];

        }

        if ($user->hasPrivilege('marketing.manage')) {
            $datasets['marketing:branches'] = [
                'key' => 'marketing:branches',
                'label' => 'Cabang Marketing',
                'group' => 'Marketing Management',
                'description' => 'Cabang, region, manager, dan status aktif marketing.',
                'table' => 'marketing_branches',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Cabang',
                    'region' => 'Region',
                    'phone' => 'Telepon',
                    'manager_name' => 'Manager',
                    'is_active' => 'Aktif',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['code', 'name', 'region', 'phone', 'manager_name', 'address'],
                'date_columns' => ['created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'status_column' => 'is_active',
                'groupable' => ['region' => 'Region', 'is_active' => 'Aktif', 'manager_name' => 'Manager'],
                'default_group' => 'region',
                'default_sort' => 'created_at',
            ];

            $datasets['marketing:trackings'] = [
                'key' => 'marketing:trackings',
                'label' => 'Tracking Marketing',
                'group' => 'Marketing Management',
                'description' => 'Monitoring pegawai marketing berdasarkan device/no telepon, lokasi, aktivitas, pipeline, dan status.',
                'table' => 'marketing_trackings',
                'columns' => [
                    'employee_profile_id' => 'Profile ID',
                    'user_id' => 'User ID',
                    'marketing_branch_id' => 'Cabang ID',
                    'tracked_at' => 'Waktu',
                    'device_id' => 'Device',
                    'phone_number' => 'No Telp',
                    'activity_type' => 'Aktivitas',
                    'customer_name' => 'Customer',
                    'pipeline_amount' => 'Pipeline',
                    'status' => 'Status',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['device_id', 'phone_number', 'activity_type', 'customer_name', 'status', 'address', 'notes'],
                'date_columns' => ['tracked_at' => 'Waktu', 'created_at' => 'Dibuat', 'updated_at' => 'Diupdate'],
                'amount_column' => 'pipeline_amount',
                'status_column' => 'status',
                'groupable' => ['status' => 'Status', 'activity_type' => 'Aktivitas', 'marketing_branch_id' => 'Cabang', 'employee_profile_id' => 'Pegawai'],
                'default_group' => 'status',
                'default_sort' => 'tracked_at',
            ];
        }

        if ($user->hasPrivilege('announcements.manage')) {
            $datasets['employee:announcements'] = [
                'key' => 'employee:announcements',
                'label' => 'Pengumuman & Berita',
                'group' => 'Pengumuman & Berita',
                'description' => 'Publikasi berita HR, pengumuman, audience, prioritas, dan status publish.',
                'table' => 'employee_announcements',
                'columns' => [
                    'kind' => 'Jenis',
                    'title' => 'Judul',
                    'summary' => 'Ringkasan',
                    'category' => 'Kategori',
                    'audience' => 'Audience',
                    'source_department' => 'Sumber',
                    'priority' => 'Prioritas',
                    'scope_module' => 'Scope Modul',
                    'is_published' => 'Published',
                    'is_pinned' => 'Pin',
                    'requires_acknowledgement' => 'Wajib Konfirmasi',
                    'publish_at' => 'Publish',
                    'expires_at' => 'Expired',
                    'created_by' => 'Creator',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => ['kind', 'title', 'summary', 'body', 'category', 'audience', 'source_department', 'priority', 'scope_module'],
                'date_columns' => ['created_at' => 'Dibuat', 'publish_at' => 'Publish', 'expires_at' => 'Expired'],
                'status_column' => 'priority',
                'groupable' => ['kind' => 'Jenis', 'category' => 'Kategori', 'audience' => 'Audience', 'priority' => 'Prioritas', 'source_department' => 'Sumber', 'scope_module' => 'Modul', 'is_published' => 'Published'],
                'default_group' => 'kind',
                'default_sort' => 'created_at',
            ];

            $datasets['employee:announcement_reads'] = [
                'key' => 'employee:announcement_reads',
                'label' => 'Status Baca Pengumuman',
                'group' => 'Pengumuman & Berita',
                'description' => 'Monitoring user yang sudah membaca atau acknowledge berita HR.',
                'table' => 'employee_announcement_reads',
                'columns' => [
                    'employee_announcement_id' => 'Pengumuman ID',
                    'user_id' => 'User ID',
                    'read_at' => 'Dibaca',
                    'acknowledged_at' => 'Konfirmasi',
                    'created_at' => 'Dibuat',
                ],
                'searchable' => [],
                'date_columns' => ['created_at' => 'Dibuat', 'read_at' => 'Dibaca', 'acknowledged_at' => 'Konfirmasi'],
                'groupable' => ['employee_announcement_id' => 'Pengumuman ID', 'user_id' => 'User ID'],
                'default_group' => 'employee_announcement_id',
                'default_sort' => 'created_at',
            ];
        }

        return $datasets;
    }

    private static function erpDataset(string $key, string $label, string $description, ?string $module, array $modules): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'group' => 'Modul ERP',
            'description' => $description,
            'table' => 'erp_records',
            'base' => $module ? ['module' => $module] : [],
            'allowed_modules' => $modules,
            'columns' => [
                'module' => 'Modul',
                'code' => 'Kode',
                'title' => 'Nama',
                'party' => 'Partner/Lokasi',
                'category' => 'Kategori',
                'amount' => 'Nilai',
                'quantity' => 'Qty',
                'progress' => 'Progress',
                'status' => 'Status',
                'owner' => 'Owner',
                'due_date' => 'Tanggal',
                'created_at' => 'Dibuat',
            ],
            'searchable' => ['code', 'title', 'party', 'category', 'status', 'owner', 'notes'],
            'date_columns' => ['created_at' => 'Dibuat', 'due_date' => 'Tanggal', 'updated_at' => 'Diupdate'],
            'amount_column' => 'amount',
            'status_column' => 'status',
            'groupable' => [
                'module' => 'Modul',
                'status' => 'Status',
                'category' => 'Kategori',
                'party' => 'Partner/Lokasi',
                'owner' => 'Owner',
            ],
            'default_group' => $module ? 'status' : 'module',
            'default_sort' => 'created_at',
        ];
    }
}

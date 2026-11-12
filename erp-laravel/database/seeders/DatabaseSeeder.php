<?php

namespace Database\Seeders;

use App\Models\ErpRecord;
use App\Models\User;
use App\Services\EmployeeIntegrationService;
use App\Services\EmployeePayrollService;
use App\Services\ErpModuleIntegrationService;
use App\Services\HrisEmployeeIntegrationService;
use App\Support\MasterDataRegistry;
use App\Support\PrivilegeRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        PrivilegeRegistry::syncDefaults(true);
        MasterDataRegistry::syncDefaults();

        ErpRecord::query()->delete();
        User::query()->delete();

        $admin = User::create([
            'name' => 'Administrator ERP',
            'email' => 'admin@horizon.test',
            'role' => 'admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'activated_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'Member Operasional',
            'email' => 'member@horizon.test',
            'role' => 'member',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'activated_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'User Viewer',
            'email' => 'user@horizon.test',
            'role' => 'user',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'activated_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $records = [
            ['sales', 'SO-2605-001', 'PT Sinar Utama', 'B2B', null, 128000000, null, null, null, 'Confirmed', 'Nadia', '2026-05-28', 'Kontrak tahunan'],
            ['sales', 'SO-2605-002', 'CV Karya Logistik', 'Project', null, 94000000, null, null, null, 'Invoiced', 'Raka', '2026-06-04', 'Termin 2'],
            ['sales', 'SO-2605-003', 'Retail Nusantara', 'Retail', null, 36500000, null, null, null, 'Paid', 'Maya', '2026-05-18', 'Repeat order'],
            ['sales', 'SO-2605-004', 'PT Prima Energi', 'B2B', null, 184000000, null, null, null, 'Draft', 'Dimas', '2026-06-12', 'Menunggu approval harga'],

            ['procurement', 'PO-2605-001', 'PT Baja Sentosa', 'Purchase Order', 'Raw Material', 87500000, null, null, null, 'Waiting Approval', 'Tari', '2026-05-27', 'Harga kontrak Q2'],
            ['procurement', 'PO-2605-002', 'Global IT Supply', 'Purchase Order', 'IT', 42500000, null, null, null, 'Ordered', 'Eka', '2026-06-02', 'Laptop sales team'],
            ['procurement', 'PO-2605-003', 'Multi Teknik', 'Goods Receipt', 'Spare Part', 18500000, null, null, null, 'Received', 'Fadil', '2026-05-20', 'Emergency stock'],
            ['procurement', 'PO-2605-004', 'Bersih Abadi', 'Vendor Bill', 'Service', 9600000, null, null, null, 'Closed', 'Tari', '2026-05-16', 'Facility service'],
            ['procurement', 'PRC-2605-001', 'Request Material Line B', 'Purchase Request', 'Raw Material', 64000000, null, null, null, 'Purchase Request', 'Bayu', '2026-06-07', 'Kebutuhan material produksi Line B'],
            ['procurement', 'PRC-2605-002', 'RFQ Mesin Scanner Gudang', 'RFQ', 'Asset', 78000000, null, null, null, 'RFQ', 'Eka', '2026-06-11', 'Perbandingan 3 vendor scanner barcode'],
            ['procurement', 'PRC-2605-003', 'Vendor Bill Baja Sentosa', 'Vendor Bill', 'Raw Material', 87500000, null, null, null, 'Vendor Bill', 'Mira', '2026-06-14', 'Tagihan vendor menunggu matching PO-GRN'],

            ['inventory', 'SKU-2605-001', 'Finished Product A', 'Jakarta', 'Finished Goods', 256000000, 1280, 700, null, 'Available', null, null, null],
            ['inventory', 'SKU-2605-002', 'Raw Material Steel', 'Surabaya', 'Raw Material', 93000000, 310, 500, null, 'Low Stock', null, null, null],
            ['inventory', 'SKU-2605-003', 'Carton Premium', 'Bandung', 'Packaging', 24600000, 820, 600, null, 'Available', null, null, null],
            ['inventory', 'SKU-2605-004', 'Bearing MRO', 'Medan', 'MRO', 16800000, 42, 90, null, 'Low Stock', null, null, null],
            ['inventory', 'SKU-2605-005', 'Finished Product B', 'Jakarta', 'Finished Goods', 99000000, 220, 200, null, 'Reserved', null, null, null],

            ['finance', 'FIN-2605-001', '1102 Piutang Usaha', 'PT Sinar Utama', 'AR Invoice', 128000000, null, null, null, 'Open', null, '2026-06-11', 'Invoice SO-2605-001'],
            ['finance', 'FIN-2605-002', '2101 Utang Vendor', 'PT Baja Sentosa', 'AP Bill', 87500000, null, null, null, 'Approved', null, '2026-06-03', 'PO raw material'],
            ['finance', 'FIN-2605-003', '5101 COGS', 'Internal', 'Journal', 68400000, null, null, null, 'Posted', null, '2026-05-21', 'Posting produksi'],
            ['finance', 'FIN-2605-004', '1102 Piutang Usaha', 'CV Karya Logistik', 'AR Invoice', 93400000, null, null, null, 'Overdue', null, '2026-05-10', 'Follow up collection'],

            ['accounting', 'ACC-2605-001', '1001 Kas Operasional', 'General Ledger', 'Chart of Account', 0, null, null, null, 'Closed', 'Mira', '2026-05-01', 'Akun kas utama operasional'],
            ['accounting', 'ACC-2605-002', 'Jurnal Penyusutan Aset', 'Mei 2026', 'Journal Entry', 28600000, null, null, null, 'Posted', 'Mira', '2026-05-31', 'Posting depresiasi aset tetap'],
            ['accounting', 'ACC-2605-003', 'Rekonsiliasi Bank BCA', 'Mei 2026', 'Reconciliation', 412000000, null, null, null, 'Reviewed', 'Reno', '2026-06-03', 'Selisih bank masih direview'],
            ['accounting', 'ACC-2605-004', 'Month End Closing', 'Mei 2026', 'Closing', 0, null, null, null, 'Draft', 'Mira', '2026-06-05', 'Checklist closing periode'],

            ['crm', 'CRM-2605-001', 'Ayu Lestari', 'PT Atlas Retail', null, 158000000, null, null, null, 'Qualified', 'Nadia', '2026-05-25', 'Demo onsite'],
            ['crm', 'CRM-2605-002', 'Bima Hartono', 'Cipta Properti', null, 310000000, null, null, null, 'Proposal', 'Raka', '2026-05-29', 'Proposal teknis'],
            ['crm', 'CRM-2605-003', 'Citra Dewi', 'Makmur Distribusi', null, 74000000, null, null, null, 'Negotiation', 'Maya', '2026-06-01', 'Negosiasi termin'],
            ['crm', 'CRM-2605-004', 'Doni Saputra', 'Nusantara Farma', null, 42000000, null, null, null, 'New', 'Dimas', '2026-05-24', 'Inbound website'],

            ['manufacturing', 'WO-2605-001', 'Finished Product A', 'Line A', null, 0, 900, null, 82, 'Running', null, '2026-05-26', 'Shift tambahan'],
            ['manufacturing', 'WO-2605-002', 'Finished Product B', 'Line B', null, 0, 420, null, 45, 'Released', null, '2026-06-02', 'Material lengkap'],
            ['manufacturing', 'WO-2605-003', 'Custom Project C', 'Outsource', null, 0, 120, null, 66, 'Inspection', null, '2026-05-30', 'QC batch 2'],
            ['manufacturing', 'WO-2605-004', 'Finished Product D', 'Line C', null, 0, 680, null, 28, 'Delayed', null, '2026-05-24', 'Menunggu spare part'],

            ['projects', 'PRJ-2605-001', 'Implementasi WMS', 'PT Sinar Utama', null, 420000000, null, null, 72, 'On Track', 'Laras', '2026-07-15', null],
            ['projects', 'PRJ-2605-002', 'Integrasi E-Invoice', 'Internal', null, 185000000, null, null, 48, 'At Risk', 'Arman', '2026-06-20', null],
            ['projects', 'PRJ-2605-003', 'Rollout Cabang Medan', 'Internal', null, 265000000, null, null, 35, 'Planning', 'Mira', '2026-08-01', null],
            ['projects', 'PRJ-2605-004', 'Upgrade Mesin Line B', 'Operations', null, 145000000, null, null, 100, 'Completed', 'Bayu', '2026-05-14', null],

            ['hr', 'EMP-2605-001', 'Nadia Putri', 'Sales', 'Sales Manager', 18500000, null, null, 96, 'Active', null, '2021-03-12', null],
            ['hr', 'EMP-2605-002', 'Arman Pratama', 'IT', 'ERP Lead', 21000000, null, null, 93, 'Active', null, '2020-08-17', null],
            ['hr', 'EMP-2605-003', 'Tari Ananda', 'Operations', 'Procurement Lead', 16800000, null, null, 89, 'On Leave', null, '2019-11-04', null],
            ['hr', 'EMP-2605-004', 'Mira Kartika', 'Finance', 'Controller', 19800000, null, null, 94, 'Active', null, '2018-02-22', null],
            ['hr', 'EMP-2605-005', 'Fadil Maulana', 'Operations', 'Warehouse Supervisor', 14200000, null, null, 91, 'Probation', null, '2026-02-01', null],

            ['hris', 'HRS-2605-001', 'Tari Ananda - Annual Leave', 'Operations', 'Leave Request', 0, 3, null, 40, 'Pending Approval', 'Intan', '2026-06-10', 'Cuti tahunan 3 hari'],
            ['hris', 'HRS-2605-002', 'Nadia Putri - Attendance Sync', 'Sales', 'Attendance', 0, 22, null, 96, 'Processed', 'Intan', '2026-05-31', 'Sinkronisasi fingerprint selesai'],
            ['hris', 'HRS-2605-003', 'Fadil Maulana - Overtime', 'Operations', 'Overtime', 1250000, 12, null, 100, 'Approved', 'Intan', '2026-05-24', 'Lembur stock opname'],
            ['hris', 'HRS-2605-004', 'Payroll Mei 2026', 'HR', 'Payroll Event', 90300000, null, null, 72, 'Draft', 'Mira', '2026-05-28', 'Payroll run periode Mei'],

            ['assets', 'AST-2605-001', 'Forklift Toyota 3T', 'Gudang Jakarta', 'Good', 280000000, null, null, null, 'In Use', 'Fadil', '2026-06-18', null],
            ['assets', 'AST-2605-002', 'Server ERP Node 01', 'Data Center', 'Excellent', 145000000, null, null, null, 'In Use', 'Arman', '2026-07-02', null],
            ['assets', 'AST-2605-003', 'Mesin Packing Line B', 'Pabrik Surabaya', 'Fair', 520000000, null, null, null, 'Maintenance', 'Bayu', '2026-05-26', null],
            ['assets', 'AST-2605-004', 'Kendaraan Operasional', 'Kantor Pusat', 'Good', 188000000, null, null, null, 'Available', 'Tari', '2026-06-12', null],
        ];

        foreach ($records as $record) {
            ErpRecord::create([
                'module' => $record[0],
                'code' => $record[1],
                'title' => $record[2],
                'party' => $record[3],
                'category' => $record[4],
                'amount' => $record[5],
                'quantity' => $record[6],
                'min_quantity' => $record[7],
                'progress' => $record[8],
                'status' => $record[9],
                'owner' => $record[10],
                'due_date' => $record[11],
                'notes' => $record[12],
                'created_by' => $admin->id,
            ]);
        }

        $integrator = app(EmployeeIntegrationService::class);
        $integrator->syncAllHrRecords($admin);
        $integrator->syncAllEmployeeProfiles($admin);
        app(EmployeePayrollService::class)->consolidateLegacyHrisPayrollRecords($admin);
        app(EmployeePayrollService::class)->syncAllPayrolls($admin);
        app(HrisEmployeeIntegrationService::class)->syncExistingRecords($admin);
        app(ErpModuleIntegrationService::class)->syncAll($admin);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\EmployeePayroll;
use App\Models\ErpRecord;
use App\Support\ErpViewData;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $salesRevenue = $user->hasPrivilege('modules.sales.view') ? ErpRecord::where('module', 'sales')
            ->whereIn('status', ['Confirmed', 'Invoiced', 'Paid'])
            ->sum('amount') : 0;

        $openReceivable = $user->hasPrivilege('modules.finance.view') ? ErpRecord::where('module', 'finance')
            ->where('category', 'AR Invoice')
            ->whereIn('status', ['Open', 'Overdue'])
            ->sum('amount') : 0;

        $inventoryValue = $user->hasPrivilege('modules.inventory.view') ? ErpRecord::where('module', 'inventory')->sum('amount') : 0;
        $payroll = $user->hasPrivilege('employee.manage') || $user->hasPrivilege('employee.portal')
            ? EmployeePayroll::whereIn('status', ['released', 'paid'])->sum('net_salary')
            : 0;
        $lowStock = $user->hasPrivilege('modules.inventory.view') ? ErpRecord::where('module', 'inventory')->where('status', 'Low Stock')->count() : 0;
        $pendingApprovals = $user->hasPrivilege('modules.procurement.view')
            ? ErpRecord::where('module', 'procurement')->where('status', 'Waiting Approval')->latest()->take(5)->get()
            : collect();

        $layout = ErpViewData::layout();
        $moduleCards = collect($layout['accessibleModules'])->map(function ($config, $module) {
            return [
                'module' => $module,
                'label' => $config['label'],
                'group' => $config['group'],
                'count' => ErpRecord::where('module', $module)->count(),
                'amount' => ErpRecord::where('module', $module)->sum('amount'),
            ];
        })->values();

        return view('dashboard', array_merge($layout, [
            'metrics' => [
                ['label' => 'Revenue', 'value' => $salesRevenue, 'note' => 'Order confirmed sampai paid'],
                ['label' => 'Piutang Terbuka', 'value' => $openReceivable, 'note' => 'AR invoice perlu follow up'],
                ['label' => 'Nilai Stok', 'value' => $inventoryValue, 'note' => $lowStock.' SKU low stock'],
                ['label' => 'Payroll', 'value' => $payroll, 'note' => 'Estimasi payroll bulanan'],
            ],
            'pendingApprovals' => $pendingApprovals,
            'moduleCards' => $moduleCards,
        ]));
    }
}

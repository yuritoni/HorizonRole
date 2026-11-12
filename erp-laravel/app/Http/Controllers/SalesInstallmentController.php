<?php

namespace App\Http\Controllers;

use App\Models\ErpRecord;
use App\Models\SalesInstallmentPlan;
use App\Services\SalesInstallmentService;
use App\Support\ErpViewData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesInstallmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPrivilege('modules.sales.view'), 403, 'Anda tidak memiliki akses modul Penjualan.');

        $query = SalesInstallmentPlan::with(['salesRecord', 'installments.financeRecord.payments'])
            ->latest();

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($inner) use ($search) {
                $inner->where('customer', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('salesRecord', function ($salesQuery) use ($search) {
                        $salesQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    });
            });
        }

        $plans = $query->paginate(8)->withQueryString();
        $allPlans = SalesInstallmentPlan::with('installments')->get();
        $availableSales = ErpRecord::where('module', 'sales')
            ->where('amount', '>', 0)
            ->whereNotIn('status', ['Paid'])
            ->whereDoesntHave('outgoingLinks', fn ($linkQuery) => $linkQuery->where('relation_type', 'sales_installment_ar_invoice'))
            ->latest()
            ->get();

        return view('sales.installments.index', array_merge(ErpViewData::layout(), [
            'plans' => $plans,
            'allPlans' => $allPlans,
            'availableSales' => $availableSales,
            'selectedSalesId' => (int) $request->get('sales_id'),
            'search' => $request->get('q', ''),
            'canManage' => $request->user()?->hasPrivilege('modules.sales.edit'),
        ]));
    }

    public function store(Request $request, SalesInstallmentService $service)
    {
        abort_unless($request->user()?->hasPrivilege('modules.sales.edit'), 403, 'Anda tidak memiliki akses edit Penjualan.');

        $validated = $request->validate([
            'sales_record_id' => ['required', 'integer', 'exists:erp_records,id'],
            'total_amount' => ['nullable', 'numeric', 'min:1'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'installment_count' => ['required', 'integer', 'min:2', 'max:36'],
            'interval_months' => ['required', 'integer', 'min:1', 'max:12'],
            'first_due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $salesRecord = ErpRecord::findOrFail($validated['sales_record_id']);

        if ($salesRecord->module !== 'sales') {
            throw ValidationException::withMessages([
                'sales_record_id' => 'Pilih record dari modul Penjualan.',
            ]);
        }

        $plan = $service->createPlan($salesRecord, $validated, $request->user());

        return redirect()
            ->route('sales.installments.index', ['q' => $salesRecord->code])
            ->with('success', 'Jadwal cicilan '.$salesRecord->code.' berhasil dibuat: '.$plan->installment_count.' termin dan invoice Finance otomatis.');
    }
}

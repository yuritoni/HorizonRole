<?php

namespace App\Http\Controllers;

use App\Models\CourierProvider;
use App\Models\ErpRecord;
use App\Models\SalesShipment;
use App\Services\SalesShipmentService;
use App\Support\ErpViewData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesShipmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPrivilege('modules.sales.view'), 403, 'Anda tidak memiliki akses modul Penjualan.');

        $query = SalesShipment::with([
            'salesRecord',
            'courierProvider',
            'customerChargeFinanceRecord.payments',
            'courierCostFinanceRecord.payments',
            'trackingEvents',
        ])->latest();

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($inner) use ($search) {
                $inner->where('shipment_no', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhereHas('salesRecord', fn ($sales) => $sales->where('code', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"))
                    ->orWhereHas('courierProvider', fn ($provider) => $provider->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return view('sales.shipments.index', array_merge(ErpViewData::layout(), [
            'shipments' => $query->paginate(8)->withQueryString(),
            'allShipments' => SalesShipment::all(),
            'providers' => CourierProvider::orderBy('name')->get(),
            'salesRecords' => ErpRecord::where('module', 'sales')
                ->whereIn('status', ['Confirmed', 'Invoiced', 'Paid'])
                ->latest()
                ->get(),
            'selectedSalesId' => (int) $request->get('sales_id'),
            'search' => $request->get('q', ''),
            'activeStatus' => $request->get('status', 'all'),
            'statuses' => SalesShipmentService::STATUSES,
            'canManage' => $request->user()?->hasPrivilege('modules.sales.edit'),
        ]));
    }

    public function store(Request $request, SalesShipmentService $service)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'sales_record_id' => ['required', 'integer', 'exists:erp_records,id'],
            'courier_provider_id' => ['required', 'integer', 'exists:courier_providers,id'],
            'service_code' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'recipient_name' => ['required', 'string', 'max:180'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_address' => ['required', 'string', 'max:2000'],
            'origin' => ['nullable', 'string', 'max:180'],
            'destination' => ['nullable', 'string', 'max:180'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'package_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'customer_shipping_charge' => ['nullable', 'numeric', 'min:0'],
            'courier_cost' => ['nullable', 'numeric', 'min:0'],
            'insurance_cost' => ['nullable', 'numeric', 'min:0'],
            'payment_policy' => ['required', Rule::in(SalesShipmentService::PAYMENT_POLICIES)],
            'pickup_scheduled_at' => ['nullable', 'date'],
            'estimated_delivery_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $salesRecord = ErpRecord::findOrFail($data['sales_record_id']);
        $provider = CourierProvider::where('is_active', true)->findOrFail($data['courier_provider_id']);

        if (! empty($data['service_code']) && ! in_array($data['service_code'], $provider->services ?? [], true)) {
            throw ValidationException::withMessages([
                'service_code' => 'Pilih layanan yang tersedia pada kurir terpilih.',
            ]);
        }

        $shipment = $service->createShipment($salesRecord, $provider, $data, $request->user());

        return redirect()
            ->route('sales.shipments.index', ['q' => $shipment->shipment_no])
            ->with('success', 'Shipment '.$shipment->shipment_no.' dibuat dan biaya terkait sudah masuk ke Finance/Accounting.');
    }

    public function storeTrackingEvent(Request $request, SalesShipment $shipment, SalesShipmentService $service)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(SalesShipmentService::STATUSES)],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'event_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1200'],
            'location' => ['nullable', 'string', 'max:180'],
            'event_at' => ['nullable', 'date'],
        ]);

        $service->recordEvent($shipment->fresh(['salesRecord', 'courierProvider']), $data, $request->user());

        return back()->with('success', 'Tracking '.$shipment->shipment_no.' berhasil diperbarui.');
    }

    public function storeProvider(Request $request)
    {
        $this->authorizeManage($request);
        $data = $this->providerData($request, null);

        CourierProvider::create($data + [
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return back()->with('success', 'Jasa kurir baru berhasil ditambahkan.');
    }

    public function updateProvider(Request $request, CourierProvider $provider)
    {
        $this->authorizeManage($request);
        $provider->update($this->providerData($request, $provider));

        return back()->with('success', 'Konfigurasi kurir '.$provider->name.' berhasil diperbarui.');
    }

    public function webhook(Request $request, CourierProvider $courierProvider, SalesShipmentService $service)
    {
        $this->authorizeWebhook($request, $courierProvider);
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(SalesShipmentService::STATUSES)],
            'event_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1200'],
            'location' => ['nullable', 'string', 'max:180'],
            'event_at' => ['nullable', 'date'],
            'external_id' => ['nullable', 'string', 'max:180'],
        ]);

        $shipment = SalesShipment::where('courier_provider_id', $courierProvider->id)
            ->where('tracking_number', $data['tracking_number'])
            ->first();

        if (! $shipment) {
            return response()->json(['message' => 'Shipment not found'], 404);
        }

        $data['payload'] = $request->all();
        $service->recordEvent($shipment->fresh(['salesRecord', 'courierProvider']), $data, null, 'courier_webhook');

        return response()->json([
            'message' => 'Tracking processed',
            'shipment_no' => $shipment->shipment_no,
            'status' => $data['status'],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->hasPrivilege('modules.sales.edit'), 403, 'Anda tidak memiliki akses edit Penjualan.');
    }

    private function providerData(Request $request, ?CourierProvider $provider): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('courier_providers', 'code')->ignore($provider?->id)],
            'name' => ['required', 'string', 'max:160'],
            'integration_mode' => ['required', Rule::in(['manual', 'link', 'webhook', 'api'])],
            'services_text' => ['nullable', 'string', 'max:600'],
            'tracking_url_template' => ['nullable', 'string', 'max:1000'],
            'api_base_url' => ['nullable', 'string', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'min:12', 'max:255'],
            'supports_cod' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $services = collect(explode(',', (string) ($data['services_text'] ?? '')))
            ->map(fn ($service) => trim($service))
            ->filter()
            ->values()
            ->all();

        if (! empty($data['tracking_url_template']) && ! str_contains($data['tracking_url_template'], '{tracking_number}')) {
            throw ValidationException::withMessages([
                'tracking_url_template' => 'Template URL tracking harus memuat {tracking_number}.',
            ]);
        }

        return [
            'code' => strtolower($data['code']),
            'name' => $data['name'],
            'integration_mode' => $data['integration_mode'],
            'services' => $services,
            'tracking_url_template' => $data['tracking_url_template'] ?? null,
            'api_base_url' => $data['api_base_url'] ?? null,
            'webhook_secret' => $data['webhook_secret'] ?: $provider?->webhook_secret,
            'supports_cod' => $request->boolean('supports_cod'),
            'is_active' => $provider ? $request->boolean('is_active') : true,
        ];
    }

    private function authorizeWebhook(Request $request, CourierProvider $provider): void
    {
        abort_unless($provider->is_active && ! empty($provider->webhook_secret), 403, 'Webhook kurir belum aktif.');

        $provided = (string) $request->header('X-Courier-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $provider->webhook_secret);

        abort_unless($provided !== '' && hash_equals($expected, $provided), 403, 'Signature webhook tidak valid.');
    }
}

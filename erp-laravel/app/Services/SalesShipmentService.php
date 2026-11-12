<?php

namespace App\Services;

use App\Models\CourierProvider;
use App\Models\ErpRecord;
use App\Models\ErpRecordLink;
use App\Models\Payment;
use App\Models\SalesShipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use App\Support\ErpModules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesShipmentService
{
    public const STATUSES = [
        'draft',
        'waiting_payment',
        'ready_to_ship',
        'pickup_requested',
        'picked_up',
        'in_transit',
        'out_for_delivery',
        'delivered',
        'exception',
        'returned',
        'cancelled',
    ];

    public const PAYMENT_POLICIES = ['prepaid', 'credit', 'cod'];

    public function createShipment(ErpRecord $salesRecord, CourierProvider $provider, array $data, ?User $actor = null): SalesShipment
    {
        if ($salesRecord->module !== 'sales' || ! in_array($salesRecord->status, ['Confirmed', 'Invoiced', 'Paid'], true)) {
            throw ValidationException::withMessages([
                'sales_record_id' => 'Pengiriman hanya dapat dibuat dari sales order berstatus Confirmed, Invoiced, atau Paid.',
            ]);
        }

        if (($data['payment_policy'] ?? 'prepaid') === 'cod' && ! $provider->supports_cod) {
            throw ValidationException::withMessages([
                'payment_policy' => 'Kurir yang dipilih belum mendukung pembayaran COD.',
            ]);
        }

        return DB::transaction(function () use ($salesRecord, $provider, $data, $actor) {
            $shipment = SalesShipment::create([
                'shipment_no' => $this->generateShipmentNo(),
                'sales_record_id' => $salesRecord->id,
                'courier_provider_id' => $provider->id,
                'service_code' => $data['service_code'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'recipient_address' => $data['recipient_address'],
                'origin' => $data['origin'] ?? null,
                'destination' => $data['destination'] ?? null,
                'weight_kg' => $data['weight_kg'] ?? 0,
                'package_count' => $data['package_count'] ?? 1,
                'customer_shipping_charge' => $data['customer_shipping_charge'] ?? 0,
                'courier_cost' => $data['courier_cost'] ?? 0,
                'insurance_cost' => $data['insurance_cost'] ?? 0,
                'payment_policy' => $data['payment_policy'],
                'customer_charge_payment_status' => $this->customerChargeTotal($data) > 0 ? 'pending' : 'not_required',
                'courier_cost_payment_status' => $this->courierCostTotal($data) > 0 ? 'pending' : 'not_required',
                'settlement_status' => 'pending',
                'status' => 'draft',
                'pickup_scheduled_at' => $data['pickup_scheduled_at'] ?? null,
                'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'source' => 'sales_shipping_management',
                    'sales_code' => $salesRecord->code,
                    'provider_code' => $provider->code,
                    'product_lines' => $salesRecord->extra['product_lines'] ?? [],
                ],
                'created_by' => $actor?->id,
            ]);

            if ($this->customerChargeTotal($data) > 0) {
                $chargeRecord = $this->createFinanceRecord($shipment, $salesRecord, $provider, 'customer_charge', $this->customerChargeTotal($data), $actor);
                $shipment->update(['customer_charge_finance_record_id' => $chargeRecord->id]);
            }

            if ($this->courierCostTotal($data) > 0) {
                $costRecord = $this->createFinanceRecord($shipment, $salesRecord, $provider, 'courier_cost', $this->courierCostTotal($data), $actor);
                $shipment->update(['courier_cost_finance_record_id' => $costRecord->id]);
            }

            $shipment = $shipment->fresh(['salesRecord', 'courierProvider']);
            $initialStatus = $this->canRelease($shipment) ? 'ready_to_ship' : 'waiting_payment';
            $shipment->update([
                'status' => $initialStatus,
                'last_tracked_at' => now(),
            ]);

            $this->createEvent($shipment, [
                'event_code' => 'shipment_created',
                'status' => $initialStatus,
                'description' => $initialStatus === 'ready_to_ship'
                    ? 'Shipment siap diproses ke kurir.'
                    : 'Shipment menunggu pelunasan Finance sebelum diserahkan ke kurir.',
                'event_at' => now(),
            ], $actor, 'system');

            $this->updateSettlement($shipment->fresh());
            $this->syncSalesSummary($shipment->fresh(['salesRecord', 'courierProvider']));
            $this->notifyShipment($shipment->fresh(['salesRecord', 'courierProvider']), 'shipping.created', 'Pengiriman dibuat', $actor);

            return $shipment->fresh([
                'salesRecord',
                'courierProvider',
                'customerChargeFinanceRecord',
                'courierCostFinanceRecord',
                'trackingEvents',
            ]);
        });
    }

    public function recordEvent(SalesShipment $shipment, array $data, ?User $actor = null, string $source = 'manual'): ShipmentTrackingEvent
    {
        $status = $data['status'];

        if ($this->requiresReleasedPayment($status) && ! $this->canRelease($shipment->fresh(['salesRecord']))) {
            throw ValidationException::withMessages([
                'status' => 'Shipment prepaid belum dapat dikirim sebelum pembayaran order dan ongkir diverifikasi Finance.',
            ]);
        }

        return DB::transaction(function () use ($shipment, $data, $actor, $source, $status) {
            $eventAt = ! empty($data['event_at']) ? Carbon::parse($data['event_at']) : now();
            $event = $this->createEvent($shipment, [
                'event_code' => $data['event_code'] ?? $status,
                'status' => $status,
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'event_at' => $eventAt,
                'external_id' => $data['external_id'] ?? null,
                'payload' => $data['payload'] ?? null,
            ], $actor, $source);

            $updates = [
                'status' => $status,
                'last_tracked_at' => $eventAt,
            ];

            if (! empty($data['tracking_number'])) {
                $updates['tracking_number'] = $data['tracking_number'];
            }

            if (in_array($status, ['picked_up', 'in_transit'], true) && ! $shipment->shipped_at) {
                $updates['shipped_at'] = $eventAt;
            }

            if ($status === 'delivered') {
                $updates['delivered_at'] = $eventAt;
            }

            $shipment->update($updates);
            $this->updateSettlement($shipment->fresh());
            $this->syncSalesSummary($shipment->fresh(['salesRecord', 'courierProvider']));
            $this->notifyShipment($shipment->fresh(['salesRecord', 'courierProvider']), 'shipping.tracking.'.$status, 'Tracking pengiriman diperbarui', $actor);

            return $event;
        });
    }

    public function markFinancePaid(ErpRecord $financeRecord, Payment $payment, ?User $actor = null): void
    {
        $shipmentId = $financeRecord->extra['sales_shipment_id'] ?? null;
        $feeType = $financeRecord->extra['shipping_fee_type'] ?? null;

        if ($shipmentId && in_array($feeType, ['customer_charge', 'courier_cost'], true)) {
            $shipment = SalesShipment::find($shipmentId);

            if ($shipment) {
                $column = $feeType === 'customer_charge'
                    ? 'customer_charge_payment_status'
                    : 'courier_cost_payment_status';
                $shipment->update([
                    $column => 'paid',
                    'metadata' => array_merge($shipment->metadata ?? [], [
                        $feeType.'_payment_order_id' => $payment->order_id,
                        $feeType.'_paid_at' => ($payment->paid_at ?: now())->toDateTimeString(),
                    ]),
                ]);
                $this->updateSettlement($shipment->fresh());
                $this->refreshRelease($shipment->fresh(['salesRecord', 'courierProvider']), $actor);
            }
        }

        $salesRecords = $financeRecord->incomingLinks()
            ->with('sourceRecord')
            ->whereIn('relation_type', ['sales_ar_invoice', 'sales_installment_ar_invoice'])
            ->get()
            ->pluck('sourceRecord')
            ->filter(fn ($record) => $record?->module === 'sales');

        foreach ($salesRecords as $salesRecord) {
            SalesShipment::where('sales_record_id', $salesRecord->id)
                ->where('status', 'waiting_payment')
                ->get()
                ->each(fn (SalesShipment $shipment) => $this->refreshRelease($shipment->fresh(['salesRecord', 'courierProvider']), $actor));
        }
    }

    public function refreshRelease(SalesShipment $shipment, ?User $actor = null): void
    {
        if ($shipment->status !== 'waiting_payment' || ! $this->canRelease($shipment)) {
            $this->syncSalesSummary($shipment);

            return;
        }

        $shipment->update([
            'status' => 'ready_to_ship',
            'last_tracked_at' => now(),
        ]);

        $this->createEvent($shipment, [
            'event_code' => 'payment_verified',
            'status' => 'ready_to_ship',
            'description' => 'Pembayaran diverifikasi Finance. Shipment siap diserahkan ke kurir.',
            'event_at' => now(),
        ], $actor, 'finance_payment');

        $this->syncSalesSummary($shipment->fresh(['salesRecord', 'courierProvider']));
        $this->notifyShipment($shipment->fresh(['salesRecord', 'courierProvider']), 'shipping.payment_verified', 'Shipment siap dikirim', $actor);
    }

    private function createFinanceRecord(SalesShipment $shipment, ErpRecord $salesRecord, CourierProvider $provider, string $feeType, float $amount, ?User $actor): ErpRecord
    {
        $isCustomerCharge = $feeType === 'customer_charge';
        $record = ErpRecord::create([
            'module' => 'finance',
            'code' => $this->generateFinanceCode(),
            'category' => $isCustomerCharge ? 'AR Invoice' : 'AP Bill',
            'title' => $isCustomerCharge ? '1102 Piutang Ongkir' : '2101 Utang Kurir',
            'party' => $isCustomerCharge ? $salesRecord->title : $provider->name,
            'amount' => $amount,
            'status' => $isCustomerCharge ? 'Open' : 'Approved',
            'due_date' => optional($shipment->estimated_delivery_at)->toDateString() ?: now()->addDays(7)->toDateString(),
            'notes' => ($isCustomerCharge ? 'Tagihan ongkir pelanggan' : 'Biaya jasa kurir').' untuk '.$shipment->shipment_no.' / '.$salesRecord->code.'.',
            'extra' => [
                'sales_record_id' => $salesRecord->id,
                'sales_record_code' => $salesRecord->code,
                'sales_shipment_id' => $shipment->id,
                'shipment_no' => $shipment->shipment_no,
                'shipping_fee_type' => $feeType,
                'product_lines' => $salesRecord->extra['product_lines'] ?? [],
                'product_source' => $salesRecord->extra['product_source'] ?? null,
                'courier_provider_id' => $provider->id,
                'courier_code' => $provider->code,
                'courier_name' => $provider->name,
                'tracking_number' => $shipment->tracking_number,
                'payment_mode' => 'shipping',
                'payment_confirmation_status' => 'waiting_payment',
                'finance_verification_status' => 'waiting_verification',
            ],
            'created_by' => $actor?->id ?: $salesRecord->created_by,
        ]);

        ErpRecordLink::create([
            'source_record_id' => $salesRecord->id,
            'target_record_id' => $record->id,
            'relation_type' => $isCustomerCharge ? 'sales_shipping_charge_ar' : 'sales_courier_cost_ap',
            'status' => 'active',
            'metadata' => [
                'sales_shipment_id' => $shipment->id,
                'shipment_no' => $shipment->shipment_no,
                'shipping_fee_type' => $feeType,
                'target_code' => $record->code,
                'synced_at' => now()->toDateTimeString(),
            ],
            'created_by' => $actor?->id,
        ]);

        app(ErpModuleIntegrationService::class)->syncRecord($record, $actor);

        return $record;
    }

    private function createEvent(SalesShipment $shipment, array $data, ?User $actor, string $source): ShipmentTrackingEvent
    {
        if (! empty($data['external_id'])) {
            $existing = ShipmentTrackingEvent::where('sales_shipment_id', $shipment->id)
                ->where('external_id', $data['external_id'])
                ->where('source', $source)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return ShipmentTrackingEvent::create([
            'sales_shipment_id' => $shipment->id,
            'event_code' => $data['event_code'] ?? null,
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'event_at' => $data['event_at'] ?? now(),
            'source' => $source,
            'external_id' => $data['external_id'] ?? null,
            'payload' => $data['payload'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    private function updateSettlement(SalesShipment $shipment): void
    {
        $chargeRequired = ((float) $shipment->customer_shipping_charge + (float) $shipment->insurance_cost) > 0;
        $costRequired = ((float) $shipment->courier_cost + (float) $shipment->insurance_cost) > 0;
        $chargeDone = ! $chargeRequired || $shipment->customer_charge_payment_status === 'paid';
        $costDone = ! $costRequired || $shipment->courier_cost_payment_status === 'paid';

        $status = match (true) {
            ! $chargeRequired && ! $costRequired => 'not_required',
            $chargeDone && $costDone => 'settled',
            ($chargeRequired && $chargeDone) || ($costRequired && $costDone) => 'partial',
            default => 'pending',
        };

        $shipment->update(['settlement_status' => $status]);
    }

    private function canRelease(SalesShipment $shipment): bool
    {
        if ($shipment->payment_policy !== 'prepaid') {
            return true;
        }

        $salesPaid = $shipment->salesRecord?->status === 'Paid';
        $shippingChargePaid = ((float) $shipment->customer_shipping_charge + (float) $shipment->insurance_cost) <= 0
            || $shipment->customer_charge_payment_status === 'paid';

        return $salesPaid && $shippingChargePaid;
    }

    private function requiresReleasedPayment(string $status): bool
    {
        return in_array($status, [
            'ready_to_ship',
            'pickup_requested',
            'picked_up',
            'in_transit',
            'out_for_delivery',
            'delivered',
        ], true);
    }

    private function syncSalesSummary(SalesShipment $shipment): void
    {
        $salesRecord = $shipment->salesRecord;

        if (! $salesRecord) {
            return;
        }

        $shipments = SalesShipment::where('sales_record_id', $salesRecord->id)->latest()->get();
        $extra = array_merge($salesRecord->extra ?? [], [
            'shipment_count' => $shipments->count(),
            'latest_shipment_id' => $shipment->id,
            'latest_shipment_no' => $shipment->shipment_no,
            'latest_shipment_status' => $shipment->status,
            'latest_tracking_number' => $shipment->tracking_number,
            'latest_courier_name' => $shipment->courierProvider?->name,
            'shipping_settlement_status' => $shipment->settlement_status,
            'shipping_synced_at' => now()->toDateTimeString(),
        ]);

        $salesRecord->forceFill(['extra' => $extra])->save();
    }

    private function notifyShipment(SalesShipment $shipment, string $type, string $title, ?User $actor): void
    {
        app(ErpNotificationService::class)->notifyPrivilege('modules.sales.view', [
            'type' => $type,
            'severity' => $shipment->status === 'exception' ? 'danger' : ($shipment->status === 'delivered' ? 'success' : 'info'),
            'module' => 'sales',
            'erp_record_id' => $shipment->sales_record_id,
            'title' => $title.': '.$shipment->shipment_no,
            'body' => ($shipment->courierProvider?->name ?: 'Kurir').' - '.($shipment->tracking_number ?: 'resi belum tersedia').' - status '.str($shipment->status)->replace('_', ' ')->headline().'.',
            'action_url' => route('sales.shipments.index', ['q' => $shipment->shipment_no]),
        ], $actor);
    }

    private function customerChargeTotal(array $data): float
    {
        return round((float) ($data['customer_shipping_charge'] ?? 0) + (float) ($data['insurance_cost'] ?? 0), 2);
    }

    private function courierCostTotal(array $data): float
    {
        return round((float) ($data['courier_cost'] ?? 0) + (float) ($data['insurance_cost'] ?? 0), 2);
    }

    private function generateShipmentNo(): string
    {
        $period = now()->format('ym');
        $sequence = SalesShipment::count() + 1;

        do {
            $code = 'SHP-'.$period.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (SalesShipment::where('shipment_no', $code)->exists());

        return $code;
    }

    private function generateFinanceCode(): string
    {
        $prefix = ErpModules::get('finance')['prefix'] ?? 'FIN';
        $period = now()->format('ym');
        $sequence = ErpRecord::where('module', 'finance')->count() + 1;

        do {
            $code = $prefix.'-'.$period.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ErpRecord::where('code', $code)->exists());

        return $code;
    }
}

<?php

namespace App\Support;

use App\Models\ErpRecord;

class PaymentRules
{
    public static function isPayable(ErpRecord $record): bool
    {
        if ($record->module !== 'finance') {
            return false;
        }

        if ((float) $record->amount <= 0 || self::hasPaidPayment($record)) {
            return false;
        }

        return in_array($record->category, ['AR Invoice', 'AP Bill'], true)
            && ! self::statusIn($record, ['Paid', 'Posted']);
    }

    public static function financeGatewayRecord(ErpRecord $record): ?ErpRecord
    {
        if ($record->module === 'finance') {
            return $record;
        }

        $outgoingFinance = null;

        if ($record->module === 'sales') {
            $outgoingFinance = $record->relationLoaded('outgoingLinks')
                ? $record->outgoingLinks
                    ->first(fn ($link) => $link->status === 'active'
                        && $link->relation_type === 'sales_installment_ar_invoice'
                        && $link->targetRecord?->module === 'finance'
                        && ! self::statusIn($link->targetRecord, ['Paid', 'Posted']))?->targetRecord
                : $record->outgoingLinks()
                    ->with('targetRecord')
                    ->where('status', 'active')
                    ->where('relation_type', 'sales_installment_ar_invoice')
                    ->whereHas('targetRecord', fn ($query) => $query->where('module', 'finance')->whereNotIn('status', ['Paid', 'Posted']))
                    ->first()?->targetRecord;
        }

        $outgoingFinance ??= $record->relationLoaded('outgoingLinks')
            ? $record->outgoingLinks
                ->first(fn ($link) => $link->status === 'active' && $link->targetRecord?->module === 'finance')?->targetRecord
            : $record->outgoingLinks()
                ->with('targetRecord')
                ->where('status', 'active')
                ->whereHas('targetRecord', fn ($query) => $query->where('module', 'finance'))
                ->first()?->targetRecord;

        if ($outgoingFinance) {
            return $outgoingFinance;
        }

        return $record->relationLoaded('incomingLinks')
            ? $record->incomingLinks->first(fn ($link) => $link->status === 'active' && $link->sourceRecord?->module === 'finance')?->sourceRecord
            : $record->incomingLinks()
                ->with('sourceRecord')
                ->where('status', 'active')
                ->whereHas('sourceRecord', fn ($query) => $query->where('module', 'finance'))
                ->first()?->sourceRecord;
    }

    public static function context(ErpRecord $record): array
    {
        $module = ErpModules::get($record->module);
        $moduleLabel = $module['label'] ?? strtoupper($record->module);

        $context = match ($record->module) {
            'sales' => [
                'label' => 'Customer collection',
                'direction' => 'Pelanggan membayar sales order atau invoice.',
            ],
            'finance' => $record->category === 'AP Bill'
                ? [
                    'label' => 'AP bill payment',
                    'direction' => 'Perusahaan membayar tagihan vendor.',
                ]
                : [
                    'label' => 'AR invoice collection',
                    'direction' => 'Pelanggan melunasi piutang usaha.',
                ],
            'procurement' => [
                'label' => 'Vendor payment',
                'direction' => 'Pembayaran procure-to-pay untuk PO atau vendor bill.',
            ],
            'accounting' => [
                'label' => $record->category === 'Reconciliation' ? 'Bank reconciliation settlement' : 'Ledger posting settlement',
                'direction' => 'Pembayaran dipakai sebagai dasar posting jurnal atau rekonsiliasi.',
            ],
            'hris' => [
                'label' => 'Payroll payout',
                'direction' => 'Pembayaran payroll, overtime, atau benefit karyawan.',
            ],
            'projects' => [
                'label' => 'Project milestone billing',
                'direction' => 'Settlement milestone proyek eksternal.',
            ],
            'assets' => [
                'label' => 'Asset maintenance payment',
                'direction' => 'Pembayaran biaya maintenance aset.',
            ],
            default => [
                'label' => 'ERP payment',
                'direction' => 'Pembayaran untuk transaksi ERP.',
            ],
        };

        if ($record->module === 'finance' && ! empty($record->extra['sales_installment_id'])) {
            $context = [
                'label' => 'Sales installment collection',
                'direction' => 'Pelanggan membayar termin cicilan penjualan melalui invoice Finance.',
            ];
        }

        if ($record->module === 'finance' && ($record->extra['shipping_fee_type'] ?? null) === 'customer_charge') {
            $context = [
                'label' => 'Shipping charge collection',
                'direction' => 'Pelanggan membayar ongkos kirim penjualan melalui Finance.',
            ];
        }

        if ($record->module === 'finance' && ($record->extra['shipping_fee_type'] ?? null) === 'courier_cost') {
            $context = [
                'label' => 'Courier settlement',
                'direction' => 'Finance membayar tagihan jasa kurir untuk pengiriman penjualan.',
            ];
        }

        return array_merge($context, [
            'module_label' => $moduleLabel,
            'paid_status' => self::paidStatus($record) ?: 'Tercatat di riwayat payment',
        ]);
    }

    public static function paidStatus(ErpRecord $record): ?string
    {
        return $record->module === 'finance' && in_array($record->category, ['AR Invoice', 'AP Bill'], true)
            ? 'Paid'
            : null;
    }

    public static function sourcePaidStatus(string $relationType, ErpRecord $source): ?string
    {
        return match ($relationType) {
            'sales_ar_invoice' => 'Paid',
            'procurement_ap_bill' => 'Closed',
            'hris_payroll_payable' => 'Processed',
            'asset_maintenance_payable' => 'In Use',
            default => null,
        };
    }

    private static function hasPaidPayment(ErpRecord $record): bool
    {
        if (! $record->exists) {
            return false;
        }

        if ($record->relationLoaded('payments')) {
            return $record->payments->contains(fn ($payment) => $payment->status === 'paid');
        }

        return $record->payments()->where('status', 'paid')->exists();
    }

    private static function statusIn(ErpRecord $record, array $statuses): bool
    {
        return in_array($record->status, $statuses, true);
    }
}

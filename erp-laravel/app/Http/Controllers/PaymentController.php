<?php

namespace App\Http\Controllers;

use App\Models\EmployeeReimbursement;
use App\Models\ErpRecord;
use App\Models\Payment;
use App\Models\User;
use App\Services\EmployeePayrollService;
use App\Services\ErpModuleIntegrationService;
use App\Services\ErpNotificationService;
use App\Services\MidtransService;
use App\Services\PaylabsService;
use App\Services\ProductIntegrationService;
use App\Services\SalesInstallmentService;
use App\Services\SalesShipmentService;
use App\Support\ErpViewData;
use App\Support\PaymentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    public function index()
    {
        return view('payments.index', array_merge(ErpViewData::layout(), [
            'payments' => Payment::with(['record', 'user'])->latest()->paginate(12),
        ]));
    }

    public function create(ErpRecord $record, MidtransService $midtrans, PaylabsService $paylabs)
    {
        $this->authorizeFinancePayment($record, request()->user());
        abort_unless(PaymentRules::isPayable($record), 404);
        $user = request()->user()->loadMissing('employeeProfile');

        return view('payments.create', array_merge(ErpViewData::layout(), [
            'record' => $record,
            'payments' => $record->payments()->latest()->get(),
            'paymentContext' => PaymentRules::context($record),
            'gateways' => [
                'midtrans' => [
                    'label' => 'Midtrans Snap',
                    'configured' => $midtrans->isConfigured(),
                    'notification_url' => $midtrans->notificationUrl(),
                ],
                'paylabs' => [
                    'label' => 'Paylabs Checkout',
                    'configured' => $paylabs->isConfigured(),
                    'notification_url' => $paylabs->notificationUrl(),
                ],
            ],
            'paylabsPhoneNumber' => $user->employeeProfile?->phone,
        ]));
    }

    public function store(Request $request, ErpRecord $record, MidtransService $midtrans, PaylabsService $paylabs)
    {
        $this->authorizeFinancePayment($record, $request->user());
        abort_unless(PaymentRules::isPayable($record), 404);
        $validated = $request->validate([
            'provider' => ['required', Rule::in(['midtrans', 'paylabs'])],
            'paylabs_phone_number' => ['nullable', 'required_if:provider,paylabs', 'string', 'max:20', 'regex:/^[0-9+() .-]+$/'],
        ]);
        $provider = $validated['provider'];

        $payment = Payment::create([
            'erp_record_id' => $record->id,
            'user_id' => $request->user()->id,
            'provider' => $provider,
            'order_id' => $this->makeOrderId($record, $provider),
            'amount' => $record->amount,
            'status' => 'pending',
        ]);

        try {
            if ($provider === 'paylabs') {
                $gatewayResponse = $paylabs->createCheckoutTransaction(
                    $payment,
                    $record,
                    $request->user(),
                    (string) ($validated['paylabs_phone_number'] ?? '')
                );
            } else {
                $snap = $midtrans->createSnapTransaction($payment, $record, $request->user());
                $gatewayResponse = [
                    'snap_token' => $snap['token'] ?? null,
                    'redirect_url' => $snap['redirect_url'] ?? null,
                    'raw_response' => $snap,
                ];
            }
        } catch (RuntimeException $exception) {
            $payment->update([
                'status' => 'failed',
                'raw_response' => ['error' => $exception->getMessage()],
            ]);
            $this->notifyPaymentStatus($payment->fresh(['record', 'user']), 'failed');

            return back()->withInput()->withErrors(['provider' => $exception->getMessage()]);
        }

        $payment->update([
            'snap_token' => $gatewayResponse['snap_token'] ?? null,
            'redirect_url' => $gatewayResponse['redirect_url'] ?? null,
            'payment_type' => $gatewayResponse['payment_type'] ?? null,
            'transaction_id' => $gatewayResponse['transaction_id'] ?? null,
            'raw_response' => $gatewayResponse['raw_response'] ?? $gatewayResponse,
        ]);
        $this->notifyPaymentStatus($payment->fresh(['record', 'user']), 'pending');

        if ($payment->redirect_url) {
            return redirect()->away($payment->redirect_url);
        }

        return redirect()->route('payments.show', $payment);
    }

    public function show(Payment $payment)
    {
        $payment->load(['record', 'user']);

        return view('payments.show', array_merge(ErpViewData::layout(), [
            'payment' => $payment,
        ]));
    }

    public function notification(Request $request, MidtransService $midtrans)
    {
        $payload = $request->all();

        if (! $midtrans->verifySignature($payload)) {
            Log::warning('Invalid Midtrans signature', ['payload' => $payload]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::with(['record', 'user'])
            ->where('provider', 'midtrans')
            ->where('order_id', $payload['order_id'] ?? null)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $status = $this->mapMidtransStatus(
            $payload['transaction_status'] ?? '',
            $payload['fraud_status'] ?? null
        );

        $alreadyPaid = $payment->isPaid();
        $payment->update([
            'status' => $status,
            'payment_type' => $payload['payment_type'] ?? $payment->payment_type,
            'transaction_id' => $payload['transaction_id'] ?? $payment->transaction_id,
            'fraud_status' => $payload['fraud_status'] ?? $payment->fraud_status,
            'paid_at' => $status === 'paid' ? now() : $payment->paid_at,
            'raw_notification' => $payload,
        ]);

        if ($status === 'paid' && ! $alreadyPaid) {
            $this->markRecordAsPaid($payment);
        }

        $this->notifyPaymentStatus($payment->fresh(['record', 'user']), $status);

        return response()->json(['message' => 'Notification processed']);
    }

    public function paylabsNotification(Request $request, PaylabsService $paylabs)
    {
        $payload = $request->all();

        try {
            $validSignature = $paylabs->isValidNotification($request, $payload);
        } catch (RuntimeException $exception) {
            Log::error('Paylabs callback configuration error', ['message' => $exception->getMessage()]);

            return response()->json(['message' => 'Paylabs configuration unavailable'], 503);
        }

        if (! $validSignature) {
            Log::warning('Invalid Paylabs signature', ['payload' => $payload]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::with(['record', 'user'])
            ->where('provider', 'paylabs')
            ->where('order_id', $payload['merchantTradeNo'] ?? null)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $status = $this->mapPaylabsStatus((string) ($payload['status'] ?? ''));
        $alreadyPaid = $payment->isPaid();
        $payment->update([
            'status' => $status,
            'payment_type' => $payload['paymentType'] ?? $payment->payment_type,
            'transaction_id' => $payload['platformTradeNo'] ?? $payment->transaction_id,
            'paid_at' => $status === 'paid' ? now() : $payment->paid_at,
            'raw_notification' => $payload,
        ]);

        if ($status === 'paid' && ! $alreadyPaid) {
            $this->markRecordAsPaid($payment);
        }

        $this->notifyPaymentStatus($payment->fresh(['record', 'user']), $status);

        return $paylabs->notificationResponse($payload);
    }

    private function makeOrderId(ErpRecord $record, string $provider): string
    {
        if ($provider === 'paylabs') {
            return 'HZP-'.$record->id.'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4));
        }

        return 'HZ-'.$record->id.'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4));
    }

    private function authorizeFinancePayment(ErpRecord $record, ?User $user): void
    {
        abort_unless($record->module === 'finance', 403, 'Pembayaran hanya diproses melalui modul Keuangan. Buka AR Invoice atau AP Bill terkait terlebih dahulu.');
        abort_unless($user?->hasPrivilege('payments.create') && $user?->hasPrivilege('modules.finance.pay'), 403, 'Anda tidak memiliki privilege payment Keuangan.');
    }

    private function mapMidtransStatus(string $transactionStatus, ?string $fraudStatus): string
    {
        if ($transactionStatus === 'capture') {
            return $fraudStatus === 'accept' ? 'paid' : 'challenge';
        }

        return match ($transactionStatus) {
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny' => 'denied',
            'cancel' => 'cancelled',
            'expire' => 'expired',
            'refund', 'partial_refund' => 'refunded',
            default => 'failed',
        };
    }

    private function mapPaylabsStatus(string $status): string
    {
        return match ($status) {
            '01' => 'pending',
            '02' => 'paid',
            '09' => 'failed',
            default => 'failed',
        };
    }

    private function markRecordAsPaid(Payment $payment): void
    {
        $record = $payment->record;

        if (! $record) {
            return;
        }

        $extra = $record->extra ?: [];
        $extra['last_payment_order_id'] = $payment->order_id;
        $extra['last_payment_status'] = 'paid';
        $extra['last_paid_at'] = now()->toDateTimeString();
        $extra['payment_confirmation_status'] = 'confirmed';
        $extra['finance_verification_status'] = 'verified';

        $updates = ['extra' => $extra];
        $paidStatus = PaymentRules::paidStatus($record);

        if ($paidStatus) {
            $updates['status'] = $paidStatus;
        }

        $record->update($updates);
        $freshRecord = $record->fresh(['incomingLinks.sourceRecord']);
        app(SalesInstallmentService::class)->markInstallmentPaidFromFinance($freshRecord, $payment, $payment->user);
        $this->markLinkedSourceRecordsAsPaid($payment, $freshRecord);
        app(SalesShipmentService::class)->markFinancePaid($freshRecord, $payment, $payment->user);
        app(ErpModuleIntegrationService::class)->syncRecord($record->fresh(), $payment->user);
    }

    private function markLinkedSourceRecordsAsPaid(Payment $payment, ErpRecord $financeRecord): void
    {
        $integrator = app(ErpModuleIntegrationService::class);

        $financeRecord->incomingLinks
            ->filter(fn ($link) => $link->sourceRecord)
            ->each(function ($link) use ($payment, $integrator) {
                $source = $link->sourceRecord;
                $paidStatus = PaymentRules::sourcePaidStatus($link->relation_type, $source);

                if (! $paidStatus) {
                    return;
                }

                $extra = $source->extra ?: [];
                $extra['finance_payment_record_id'] = $payment->erp_record_id;
                $extra['finance_payment_order_id'] = $payment->order_id;
                $extra['finance_payment_status'] = 'paid';
                $extra['finance_paid_at'] = now()->toDateTimeString();
                $extra['payment_confirmation_status'] = 'confirmed';
                $extra['finance_verification_status'] = 'verified';

                if ($source->module === 'hris' && $source->category === 'Training') {
                    $extra['training_payment_status'] = 'paid';
                    $extra['training_paid_at'] = now()->toDateTimeString();
                    $extra['training_payment_order_id'] = $payment->order_id;
                }

                if ($source->module === 'hris' && $source->category === 'Business Trip') {
                    $extra['business_trip_payment_status'] = 'paid';
                    $extra['business_trip_paid_at'] = now()->toDateTimeString();
                    $extra['business_trip_payment_order_id'] = $payment->order_id;
                }

                $source->update([
                    'status' => $paidStatus,
                    'extra' => $extra,
                ]);

                if ($source->module === 'hris' && $source->category === 'Payroll Event') {
                    app(EmployeePayrollService::class)->markPayrollPaidFromFinance($source->fresh(), $payment->user);
                }

                if ($source->module === 'hris' && $source->category === 'Reimbursement') {
                    $this->markEmployeeReimbursementPaid($source->fresh(), $payment);
                }

                app(ProductIntegrationService::class)->syncRecord($source->fresh(), null, $payment->user);
                $integrator->syncRecord($source->fresh(), $payment->user);
            });
    }

    private function markEmployeeReimbursementPaid(ErpRecord $source, Payment $payment): void
    {
        if (empty($source->extra['employee_reimbursement_id'])) {
            return;
        }

        $reimbursement = EmployeeReimbursement::find($source->extra['employee_reimbursement_id']);

        if (! $reimbursement) {
            return;
        }

        $reimbursement->forceFill([
            'status' => 'paid',
            'payment_method' => $source->extra['payment_mode'] ?? 'separate',
            'paid_at' => $reimbursement->paid_at ?: ($payment->paid_at ?: now()),
            'review_note' => trim(($reimbursement->review_note ?: '').' Dibayar melalui Finance '.$payment->order_id.'.'),
        ])->save();
    }

    private function notifyPaymentStatus(?Payment $payment, string $status): void
    {
        if (! $payment) {
            return;
        }

        $record = $payment->record;
        $severity = match ($status) {
            'paid' => 'success',
            'pending', 'challenge' => 'warning',
            default => 'danger',
        };

        app(ErpNotificationService::class)->notifyUsers($this->paymentRecipients($payment), [
            'type' => 'payment.'.$status,
            'severity' => $severity,
            'module' => $record?->module,
            'erp_record_id' => $record?->id,
            'payment_id' => $payment->id,
            'title' => 'Payment '.strtoupper($status).': '.$payment->order_id,
            'body' => ($record?->code ?: 'Record').' - '.($record?->title ?: 'ERP payment').' senilai Rp '.number_format($payment->amount, 0, ',', '.').'.',
            'action_url' => route('payments.show', $payment),
        ], $payment->user);
    }

    private function paymentRecipients(Payment $payment)
    {
        $module = $payment->record?->module;
        $users = User::where('account_status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->hasPrivilege('payments.view') && (! $module || $user->hasPrivilege("modules.{$module}.view")));

        if ($payment->user) {
            $users->push($payment->user);
        }

        return $users->unique('id')->values();
    }
}

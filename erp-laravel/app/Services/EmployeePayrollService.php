<?php

namespace App\Services;

use App\Models\EmployeeAttendance;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\EmployeeReimbursement;
use App\Models\EmployeeRequest;
use App\Models\ErpRecord;
use App\Models\MarketingTracking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class EmployeePayrollService
{
    public function calculate(EmployeeProfile $profile, string $periodMonth, array $overrides = []): array
    {
        $profile->loadMissing('user');
        $period = Carbon::parse($periodMonth)->startOfMonth();
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $workingDays = max(1, $this->workingDays($start, $end));
        $attendanceQuery = EmployeeAttendance::where('employee_profile_id', $profile->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->where('approval_status', 'approved');
        $attendanceRecords = (clone $attendanceQuery)->count();
        $presentDays = (clone $attendanceQuery)
            ->whereIn('status', ['present', 'manual_present'])
            ->count();
        $absenceDays = (clone $attendanceQuery)
            ->whereIn('status', ['absent', 'unpaid_leave', 'no_show'])
            ->count();

        $basicSalary = (float) ($overrides['basic_salary'] ?? $profile->base_salary ?: ($profile->settings['salary_reference'] ?? 0));
        $allowance = (float) ($overrides['allowance'] ?? $profile->fixed_allowance ?? 0);
        $manualDeduction = (float) ($overrides['deduction'] ?? 0);
        $bonus = (float) ($overrides['bonus'] ?? 0);
        $thr = (float) ($overrides['thr'] ?? 0);

        $reimbursementItems = EmployeeReimbursement::where('employee_profile_id', $profile->id)
            ->where(function ($query) {
                $query->where('status', 'approved')
                    ->orWhere(function ($inner) {
                        $inner->where('status', 'paid');

                        if (Schema::hasColumn('employee_reimbursements', 'employee_payroll_id')) {
                            $inner->whereNotNull('employee_payroll_id');
                        }
                    });
            })
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->when(Schema::hasColumn('employee_reimbursements', 'payment_method'), function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNull('payment_method')
                        ->orWhere('payment_method', 'payroll');
                });
            })
            ->get();
        $reimbursement = (float) ($overrides['reimbursement'] ?? $reimbursementItems->sum('amount'));

        $overtimeHours = (float) EmployeeRequest::where('employee_profile_id', $profile->id)
            ->where('type', 'overtime')
            ->where('status', 'approved')
            ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
            ->sum('total_days');
        $overtimeAmount = (float) ($overrides['overtime_amount'] ?? round(($basicSalary / 173) * 1.5 * $overtimeHours, 2));

        $marketingPipeline = (float) MarketingTracking::where('employee_profile_id', $profile->id)
            ->whereIn('status', ['verified', 'converted'])
            ->whereBetween('tracked_at', [$start, $end])
            ->sum('pipeline_amount');
        $marketingCommission = (float) ($overrides['marketing_commission'] ?? round($marketingPipeline * ((float) $profile->commission_rate), 2));

        $productionOutput = (float) ErpRecord::where('module', 'manufacturing')
            ->where('status', 'Completed')
            ->where('owner', $profile->user?->name)
            ->whereBetween('updated_at', [$start, $end])
            ->sum('quantity');
        $productionIncentive = (float) ($overrides['production_incentive'] ?? round($productionOutput * ((float) $profile->production_kpi_rate), 2));

        $attendanceAllowance = (float) ($overrides['attendance_allowance'] ?? ($attendanceRecords > 0 ? $presentDays * 25000 : 0));
        $missingDays = $absenceDays;
        $attendanceDeduction = (float) ($overrides['attendance_deduction'] ?? ($absenceDays > 0 ? round(($basicSalary / $workingDays) * $absenceDays, 2) : 0));

        $gross = $basicSalary + $allowance + $reimbursement + $overtimeAmount + $bonus + $thr + $marketingCommission + $productionIncentive + $attendanceAllowance;
        $tax = (float) ($overrides['tax'] ?? round(max(0, $gross - $reimbursement) * 0.05, 2));
        $deduction = $manualDeduction + $tax + $attendanceDeduction;
        $net = max(0, $gross - $deduction);

        return [
            'period_month' => $period->toDateString(),
            'basic_salary' => $basicSalary,
            'allowance' => $allowance,
            'reimbursement' => $reimbursement,
            'overtime_amount' => $overtimeAmount,
            'bonus' => $bonus,
            'thr' => $thr,
            'tax' => $tax,
            'marketing_commission' => $marketingCommission,
            'production_incentive' => $productionIncentive,
            'attendance_allowance' => $attendanceAllowance,
            'attendance_deduction' => $attendanceDeduction,
            'deduction' => $deduction,
            'gross_salary' => $gross,
            'net_salary' => $net,
            'components' => [
                'working_days' => $workingDays,
                'attendance_records' => $attendanceRecords,
                'present_days' => $presentDays,
                'missing_days' => $missingDays,
                'absence_days' => $absenceDays,
                'attendance_policy' => $absenceDays > 0 ? 'deduct_explicit_absence_days' : 'neutral_without_explicit_absence',
                'overtime_hours' => $overtimeHours,
                'marketing_pipeline' => $marketingPipeline,
                'marketing_commission_rate' => (float) $profile->commission_rate,
                'production_output' => $productionOutput,
                'production_kpi_rate' => (float) $profile->production_kpi_rate,
                'manual_deduction' => $manualDeduction,
                'tax_status' => $profile->tax_status,
                'reimbursement_ids' => $reimbursementItems->pluck('id')->values()->all(),
                'reimbursement_count' => $reimbursementItems->count(),
                'reimbursement_payment_method' => 'payroll',
            ],
        ];
    }

    public function storePayroll(EmployeeProfile $profile, string $periodMonth, array $overrides, string $status, ?string $notes, ?User $actor): EmployeePayroll
    {
        $profile->loadMissing('user');
        $calculation = $this->calculate($profile, $periodMonth, $overrides);

        $payroll = EmployeePayroll::updateOrCreate(
            ['user_id' => $profile->user_id, 'period_month' => $calculation['period_month']],
            [
                'employee_profile_id' => $profile->id,
                'basic_salary' => $calculation['basic_salary'],
                'allowance' => $calculation['allowance'],
                'deduction' => $calculation['deduction'],
                'reimbursement' => $calculation['reimbursement'],
                'gross_salary' => $calculation['gross_salary'],
                'overtime_amount' => $calculation['overtime_amount'],
                'bonus' => $calculation['bonus'],
                'thr' => $calculation['thr'],
                'tax' => $calculation['tax'],
                'marketing_commission' => $calculation['marketing_commission'],
                'production_incentive' => $calculation['production_incentive'],
                'attendance_allowance' => $calculation['attendance_allowance'],
                'attendance_deduction' => $calculation['attendance_deduction'],
                'net_salary' => $calculation['net_salary'],
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
                'components' => $calculation['components'],
                'notes' => $notes,
            ]
        );

        $payroll = $this->syncPayrollReimbursements($payroll->fresh(['profile', 'user']), $actor);
        $this->syncPayrollToErp($payroll->fresh(['profile', 'user']), $actor);

        return $payroll->fresh(['profile', 'user']);
    }

    public function syncAllPayrolls(?User $actor = null)
    {
        return EmployeePayroll::with(['profile', 'user'])
            ->orderBy('period_month')
            ->get()
            ->map(function (EmployeePayroll $payroll) use ($actor) {
                if (in_array($payroll->status, ['draft', 'released'], true)) {
                    $payroll = $this->recalculatePayroll($payroll);
                }

                return $this->syncPayrollToErp($payroll, $actor);
            })
            ->filter()
            ->values();
    }

    public function recalculatePayroll(EmployeePayroll $payroll): EmployeePayroll
    {
        $payroll->loadMissing(['profile', 'user']);

        if (! $payroll->profile) {
            return $payroll;
        }

        $calculation = $this->calculate($payroll->profile, $payroll->period_month->toDateString(), $this->recalculationOverrides($payroll));

        $payroll->fill([
            'basic_salary' => $calculation['basic_salary'],
            'allowance' => $calculation['allowance'],
            'deduction' => $calculation['deduction'],
            'reimbursement' => $calculation['reimbursement'],
            'gross_salary' => $calculation['gross_salary'],
            'overtime_amount' => $calculation['overtime_amount'],
            'bonus' => $calculation['bonus'],
            'thr' => $calculation['thr'],
            'tax' => $calculation['tax'],
            'marketing_commission' => $calculation['marketing_commission'],
            'production_incentive' => $calculation['production_incentive'],
            'attendance_allowance' => $calculation['attendance_allowance'],
            'attendance_deduction' => $calculation['attendance_deduction'],
            'net_salary' => $calculation['net_salary'],
            'components' => $calculation['components'],
        ])->save();

        return $this->syncPayrollReimbursements($payroll->fresh(['profile', 'user']));
    }

    public function consolidateLegacyHrisPayrollRecords(?User $actor = null)
    {
        return ErpRecord::where('module', 'hris')
            ->where('category', 'Payroll Event')
            ->orderBy('id')
            ->get()
            ->filter(fn (ErpRecord $record) => empty($record->extra['employee_payroll_id'])
                && ! empty($record->extra['employee_profile_id'])
                && ($record->extra['source_master'] ?? null) !== 'employee_profiles_summary')
            ->map(function (ErpRecord $record) use ($actor) {
                $profile = EmployeeProfile::with('user')->find($record->extra['employee_profile_id']);

                if (! $profile) {
                    return null;
                }

                $period = $record->due_date?->copy()->startOfMonth()->toDateString() ?: now()->startOfMonth()->toDateString();
                $status = match ($record->status) {
                    'Processed' => 'paid',
                    'Approved' => 'released',
                    default => 'draft',
                };
                $overrides = [];

                if ((float) $profile->base_salary <= 0 && (float) $record->amount > 0) {
                    $overrides['basic_salary'] = (float) $record->amount;
                }

                $payroll = $this->storePayroll(
                    $profile,
                    $period,
                    $overrides,
                    $status,
                    trim(($record->notes ?: '').' Dikonsolidasikan dari HRIS '.$record->code.'.'),
                    $actor
                );
                $canonicalRecord = ErpRecord::where('module', 'hris')
                    ->where('category', 'Payroll Event')
                    ->get()
                    ->first(fn (ErpRecord $candidate) => (int) ($candidate->extra['employee_payroll_id'] ?? 0) === (int) $payroll->id);

                if ($canonicalRecord && $canonicalRecord->id !== $record->id) {
                    $this->removeLegacyPayrollRecord($record, $canonicalRecord);
                }

                return $canonicalRecord ?: $record->fresh();
            })
            ->filter()
            ->values();
    }

    public function syncPayrollToErp(EmployeePayroll $payroll, ?User $actor = null): ?ErpRecord
    {
        $payroll->loadMissing(['profile.managerProfile.user', 'user']);
        $periodLabel = $payroll->period_month->format('M Y');
        $code = 'HRS-PAY-'.$payroll->profile->employee_no.'-'.$payroll->period_month->format('ym');

        $record = ErpRecord::updateOrCreate(
            ['module' => 'hris', 'code' => $code],
            [
                'title' => $payroll->user->name.' - Payroll '.$periodLabel,
                'category' => 'Payroll Event',
                'party' => $payroll->profile->department,
                'quantity' => $payroll->components['present_days'] ?? null,
                'amount' => $payroll->net_salary,
                'progress' => $payroll->components['working_days'] ?? null
                    ? round((($payroll->components['present_days'] ?? 0) / max(1, $payroll->components['working_days'])) * 100)
                    : null,
                'status' => match ($payroll->status) {
                    'paid' => 'Processed',
                    'released' => 'Approved',
                    default => 'Draft',
                },
                'owner' => $payroll->profile->manager_name ?: 'HR',
                'due_date' => $payroll->period_month->copy()->endOfMonth()->toDateString(),
                'notes' => 'Payroll otomatis dari Employee Management untuk '.$periodLabel.'.',
                'extra' => [
                    'employee_payroll_id' => $payroll->id,
                    'employee_profile_id' => $payroll->employee_profile_id,
                    'employee_no' => $payroll->profile?->employee_no,
                    'user_id' => $payroll->user_id,
                    'employee_name' => $payroll->user?->name,
                    'department' => $payroll->profile?->department,
                    'position' => $payroll->profile?->position,
                    'manager_profile_id' => $payroll->profile?->manager_profile_id,
                    'manager_name' => $payroll->profile?->managerProfile?->user?->name ?: $payroll->profile?->manager_name,
                    'components' => $payroll->components,
                    'gross_salary' => (float) $payroll->gross_salary,
                    'net_salary' => (float) $payroll->net_salary,
                    'source_master' => 'employee_profiles',
                    'integration_source' => 'employee_payroll',
                ],
                'created_by' => $actor?->id ?: $payroll->user_id,
            ]
        );

        $record = app(HrisEmployeeIntegrationService::class)
            ->applyEmployeeContext($record->fresh(), $actor, $payroll->profile);

        if ($payroll->status === 'draft') {
            $this->pruneDraftFinanceLinks($record->fresh());
        } else {
            app(ErpModuleIntegrationService::class)->syncRecord($record->fresh(), $actor);
        }

        return $record->fresh();
    }

    public function markPayrollPaidFromFinance(ErpRecord $record, ?User $actor = null): ?EmployeePayroll
    {
        if ($record->module !== 'hris'
            || $record->category !== 'Payroll Event'
            || empty($record->extra['employee_payroll_id'])) {
            return null;
        }

        $payroll = EmployeePayroll::with(['profile', 'user'])->find($record->extra['employee_payroll_id']);

        if (! $payroll) {
            return null;
        }

        $payroll->forceFill([
            'status' => 'paid',
            'paid_at' => $payroll->paid_at ?: now(),
        ])->save();

        $payroll = $this->syncPayrollReimbursements($payroll->fresh(['profile', 'user']), $actor);
        $this->syncPayrollToErp($payroll, $actor);

        return $payroll->fresh(['profile', 'user']);
    }

    public function prunePayrollModeReimbursementPayables(): int
    {
        if (! Schema::hasTable('employee_reimbursements')) {
            return 0;
        }

        return ErpRecord::where('module', 'hris')
            ->where('category', 'Reimbursement')
            ->get()
            ->filter(function (ErpRecord $record) {
                if (($record->extra['payment_mode'] ?? null) === 'payroll' || ! empty($record->extra['employee_payroll_id'])) {
                    return true;
                }

                if (empty($record->extra['employee_reimbursement_id'])) {
                    return false;
                }

                $reimbursement = EmployeeReimbursement::find($record->extra['employee_reimbursement_id']);

                return $reimbursement && ($reimbursement->payment_method ?: 'payroll') === 'payroll';
            })
            ->each(fn (ErpRecord $record) => $this->prunePayrollReimbursementFinanceLinks($record))
            ->count();
    }

    private function syncPayrollReimbursements(EmployeePayroll $payroll, ?User $actor = null): EmployeePayroll
    {
        $payroll->loadMissing(['profile.managerProfile.user', 'user']);

        if (! $payroll->profile) {
            return $payroll;
        }

        if (! Schema::hasColumn('employee_reimbursements', 'payment_method')
            || ! Schema::hasColumn('employee_reimbursements', 'employee_payroll_id')
            || ! Schema::hasColumn('employee_reimbursements', 'paid_at')) {
            return $payroll;
        }

        $this->reimbursementsForPayroll($payroll)
            ->each(function (EmployeeReimbursement $reimbursement) use ($payroll, $actor) {
                $note = $payroll->status === 'paid'
                    ? 'Dibayar melalui payroll '.$payroll->period_month->format('M Y').'.'
                    : 'Dijadwalkan dibayar melalui payroll '.$payroll->period_month->format('M Y').'.';

                $updates = [
                    'payment_method' => 'payroll',
                    'employee_payroll_id' => $payroll->id,
                    'review_note' => $this->appendPayrollNote($reimbursement->review_note, $note),
                ];

                if ($payroll->status === 'paid') {
                    $updates['status'] = 'paid';
                    $updates['paid_at'] = $reimbursement->paid_at ?: ($payroll->paid_at ?: now());
                }

                $reimbursement->forceFill($updates)->save();
                $this->syncReimbursementHrisRecord($reimbursement->fresh(['profile.managerProfile.user', 'user']), $payroll, $actor);
            });

        return $payroll->fresh(['profile', 'user']);
    }

    private function reimbursementsForPayroll(EmployeePayroll $payroll)
    {
        $period = $payroll->period_month->copy()->startOfMonth();
        $start = $period->copy()->startOfMonth()->toDateString();
        $end = $period->copy()->endOfMonth()->toDateString();

        return EmployeeReimbursement::with(['profile.managerProfile.user', 'user'])
            ->where('employee_profile_id', $payroll->employee_profile_id)
            ->where(function ($query) use ($payroll) {
                $query->where('status', 'approved')
                    ->orWhere(function ($inner) use ($payroll) {
                        $inner->where('status', 'paid')
                            ->where('employee_payroll_id', $payroll->id);
                    });
            })
            ->whereBetween('expense_date', [$start, $end])
            ->where(function ($query) {
                $query->whereNull('payment_method')
                    ->orWhere('payment_method', 'payroll');
            })
            ->where(function ($query) use ($payroll) {
                $query->whereNull('employee_payroll_id')
                    ->orWhere('employee_payroll_id', $payroll->id);
            })
            ->get();
    }

    private function syncReimbursementHrisRecord(EmployeeReimbursement $reimbursement, EmployeePayroll $payroll, ?User $actor): void
    {
        $reimbursement->loadMissing(['profile.managerProfile.user', 'user']);
        $profile = $reimbursement->profile;

        if (! $profile) {
            return;
        }

        $record = ErpRecord::where('module', 'hris')
            ->where('category', 'Reimbursement')
            ->get()
            ->first(fn (ErpRecord $candidate) => (int) ($candidate->extra['employee_reimbursement_id'] ?? 0) === (int) $reimbursement->id);

        $record = ErpRecord::updateOrCreate(
            ['module' => 'hris', 'code' => $record?->code ?: 'HRS-RMB-'.$reimbursement->id],
            [
                'title' => $reimbursement->user?->name.' - '.$reimbursement->title,
                'category' => 'Reimbursement',
                'party' => $profile->department,
                'quantity' => 1,
                'amount' => $reimbursement->amount,
                'progress' => 100,
                'status' => $reimbursement->status === 'paid' ? 'Processed' : 'Approved',
                'owner' => $profile->managerProfile?->user?->name ?: ($profile->manager_name ?: 'HR'),
                'due_date' => $reimbursement->expense_date,
                'notes' => $reimbursement->description,
                'extra' => array_merge($record?->extra ?? [], [
                    'employee_reimbursement_id' => $reimbursement->id,
                    'employee_profile_id' => $reimbursement->employee_profile_id,
                    'employee_no' => $profile->employee_no,
                    'user_id' => $reimbursement->user_id,
                    'employee_name' => $reimbursement->user?->name,
                    'department' => $profile->department,
                    'position' => $profile->position,
                    'manager_profile_id' => $profile->manager_profile_id,
                    'manager_name' => $profile->managerProfile?->user?->name ?: $profile->manager_name,
                    'payment_mode' => 'payroll',
                    'employee_payroll_id' => $payroll->id,
                    'employee_payroll_period' => $payroll->period_month->toDateString(),
                    'paid_at' => $reimbursement->paid_at?->toDateTimeString(),
                    'source_master' => 'employee_profiles',
                    'source' => 'employee_reimbursement',
                ]),
                'created_by' => $actor?->id ?: $reimbursement->user_id,
            ]
        );

        $record = app(HrisEmployeeIntegrationService::class)
            ->applyEmployeeContext($record->fresh(), $actor, $profile);

        $this->prunePayrollReimbursementFinanceLinks($record->fresh());
    }

    private function pruneDraftFinanceLinks(ErpRecord $record): void
    {
        $links = $record->outgoingLinks()
            ->with(['targetRecord.payments', 'targetRecord.outgoingLinks.targetRecord'])
            ->where('relation_type', 'hris_payroll_payable')
            ->get();

        foreach ($links as $link) {
            $financeRecord = $link->targetRecord;
            $link->delete();

            if (! $financeRecord) {
                continue;
            }

            if ($financeRecord->payments()->where('status', 'paid')->exists()) {
                $financeRecord->forceFill([
                    'status' => 'Open',
                    'notes' => trim(($financeRecord->notes ?: '').' Payroll sumber kembali ke draft; verifikasi ulang sebelum pembayaran.'),
                    'extra' => array_merge($financeRecord->extra ?? [], [
                        'payroll_source_retracted' => true,
                        'payroll_source_retracted_at' => now()->toDateTimeString(),
                    ]),
                ])->save();

                continue;
            }

            foreach ($financeRecord->outgoingLinks as $accountingLink) {
                $accountingRecord = $accountingLink->targetRecord;

                if ($accountingRecord
                    && $accountingLink->relation_type === 'finance_ledger_journal'
                    && (int) ($accountingRecord->extra['integration_source_record_id'] ?? 0) === (int) $financeRecord->id) {
                    $accountingRecord->delete();
                }

                $accountingLink->delete();
            }

            $financeRecord->delete();
        }
    }

    private function prunePayrollReimbursementFinanceLinks(ErpRecord $record): void
    {
        $record->outgoingLinks()
            ->with(['targetRecord.payments', 'targetRecord.outgoingLinks.targetRecord'])
            ->where('relation_type', 'hris_payroll_payable')
            ->get()
            ->each(function ($link) use ($record) {
                $financeRecord = $link->targetRecord;
                $link->delete();

                if (! $financeRecord) {
                    return;
                }

                if ($financeRecord->payments()->where('status', 'paid')->exists()) {
                    $financeRecord->forceFill([
                        'notes' => trim(($financeRecord->notes ?: '').' Reimburse '.$record->code.' sekarang ditandai dibayar melalui payroll; payment finance lama dipertahankan sebagai histori.'),
                        'extra' => array_merge($financeRecord->extra ?? [], [
                            'reimbursement_payroll_replaced_by_record_id' => $record->id,
                            'reimbursement_payroll_replaced_by_code' => $record->code,
                            'reimbursement_payroll_replaced_at' => now()->toDateTimeString(),
                        ]),
                    ])->save();

                    return;
                }

                foreach ($financeRecord->outgoingLinks as $childLink) {
                    $childRecord = $childLink->targetRecord;

                    if ($childRecord
                        && (int) ($childRecord->extra['integration_source_record_id'] ?? 0) === (int) $financeRecord->id) {
                        $childRecord->delete();
                    }

                    $childLink->delete();
                }

                $financeRecord->delete();
            });
    }

    private function appendPayrollNote(?string $currentNote, string $note): string
    {
        $currentNote = trim((string) $currentNote);

        if (str_contains($currentNote, $note)) {
            return $currentNote;
        }

        return trim($currentNote.' '.$note);
    }

    private function recalculationOverrides(EmployeePayroll $payroll): array
    {
        return array_filter([
            'basic_salary' => (float) $payroll->basic_salary > 0 ? (float) $payroll->basic_salary : null,
            'allowance' => (float) $payroll->allowance > 0 ? (float) $payroll->allowance : null,
            'deduction' => (float) ($payroll->components['manual_deduction'] ?? 0),
            'bonus' => (float) $payroll->bonus,
            'thr' => (float) $payroll->thr,
        ], fn ($value) => $value !== null);
    }

    private function removeLegacyPayrollRecord(ErpRecord $legacyRecord, ErpRecord $canonicalRecord): void
    {
        $hasPaidPayments = $legacyRecord->outgoingLinks()
            ->with('targetRecord.payments')
            ->where('relation_type', 'hris_payroll_payable')
            ->get()
            ->contains(fn ($link) => $link->targetRecord?->payments?->contains(fn ($payment) => $payment->status === 'paid'));

        if ($hasPaidPayments) {
            $legacyRecord->forceFill([
                'status' => 'Processed',
                'notes' => trim(($legacyRecord->notes ?: '').' Digantikan oleh payroll canonical '.$canonicalRecord->code.'.'),
                'extra' => array_merge($legacyRecord->extra ?? [], [
                    'legacy_payroll_replaced_by' => $canonicalRecord->id,
                    'legacy_payroll_replaced_by_code' => $canonicalRecord->code,
                    'legacy_payroll_replaced_at' => now()->toDateTimeString(),
                ]),
            ])->save();

            return;
        }

        $legacyRecord->outgoingLinks()
            ->with('targetRecord.outgoingLinks.targetRecord')
            ->get()
            ->each(function ($link) {
                $targetRecord = $link->targetRecord;
                $link->delete();

                if (! $targetRecord) {
                    return;
                }

                foreach ($targetRecord->outgoingLinks as $childLink) {
                    $childRecord = $childLink->targetRecord;

                    if ($childRecord
                        && (int) ($childRecord->extra['integration_source_record_id'] ?? 0) === (int) $targetRecord->id) {
                        $childRecord->delete();
                    }

                    $childLink->delete();
                }

                $targetRecord->delete();
            });

        $legacyRecord->incomingLinks()->delete();
        $legacyRecord->delete();
    }

    private function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPeriod;
use App\Models\ErpRecord;
use App\Models\ErpRecordLink;
use App\Models\Payment;
use App\Support\ErpViewData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingController extends Controller
{
    private const DEFAULT_ACCOUNTS = [
        '1101' => ['name' => 'Kas dan Bank', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => true],
        '1102' => ['name' => 'Piutang Usaha', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
        '1199' => ['name' => 'Suspense / Clearing', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
        '1301' => ['name' => 'Persediaan', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
        '1501' => ['name' => 'Aset Tetap', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => false],
        '2101' => ['name' => 'Utang Vendor', 'type' => 'liability', 'normal_balance' => 'credit', 'is_cash' => false],
        '2201' => ['name' => 'PPN Keluaran', 'type' => 'liability', 'normal_balance' => 'credit', 'is_cash' => false],
        '2202' => ['name' => 'Accrued Expense', 'type' => 'liability', 'normal_balance' => 'credit', 'is_cash' => false],
        '3101' => ['name' => 'Modal / Ekuitas', 'type' => 'equity', 'normal_balance' => 'credit', 'is_cash' => false],
        '4100' => ['name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_cash' => false],
        '5100' => ['name' => 'Beban Operasional', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
        '5200' => ['name' => 'Beban Gaji dan Benefit', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
        '5300' => ['name' => 'Beban Pengiriman', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
        '5400' => ['name' => 'Beban Pelatihan dan Dinas', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cash' => false],
    ];

    public function index(Request $request)
    {
        $periods = AccountingPeriod::orderByDesc('start_date')->get();
        $selectedPeriod = $this->selectedPeriod($request, $periods);
        $accounts = AccountingAccount::with('parent')->orderBy('code')->get();
        $status = $request->get('status', 'all');
        $sections = [
            'overview' => ['label' => 'Overview', 'icon' => 'layout-dashboard'],
            'trial' => ['label' => 'Trial Balance', 'icon' => 'scale'],
            'journal' => ['label' => 'Jurnal', 'icon' => 'book-open-check'],
            'coa' => ['label' => 'Chart of Account', 'icon' => 'list-tree'],
            'period' => ['label' => 'Periode', 'icon' => 'calendar-days'],
            'sync' => ['label' => 'Sinkronisasi', 'icon' => 'git-merge'],
        ];
        $section = $request->get('section', 'overview');

        if (! array_key_exists($section, $sections)) {
            $section = 'overview';
        }

        $entries = AccountingJournalEntry::with(['period', 'lines.account', 'creator', 'poster', 'source'])
            ->when($selectedPeriod, fn ($query) => $query->where('period_id', $selectedPeriod->id))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('entry_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $trialBalance = $this->trialBalance($accounts, $selectedPeriod);
        $totalDebit = round($trialBalance->sum('debit'), 2);
        $totalCredit = round($trialBalance->sum('credit'), 2);
        $variance = round($totalDebit - $totalCredit, 2);

        return view('accounting.ledger', array_merge(ErpViewData::layout(), [
            'accounts' => $accounts,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'entries' => $entries,
            'trialBalance' => $trialBalance,
            'summary' => [
                'account_count' => $accounts->count(),
                'posted_count' => AccountingJournalEntry::where('status', 'posted')->count(),
                'draft_count' => AccountingJournalEntry::whereIn('status', ['draft', 'reviewed'])->count(),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'variance' => $variance,
                'period_status' => $selectedPeriod?->status ?? 'none',
            ],
            'activeSection' => $section,
            'accountingSections' => $sections,
            'activeStatus' => $status,
            'journalStatuses' => ['all', 'draft', 'reviewed', 'posted', 'reversed'],
            'accountTypes' => ['asset', 'liability', 'equity', 'revenue', 'expense'],
        ]));
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('accounting_accounts', 'code')],
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'parent_id' => ['nullable', 'exists:accounting_accounts,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_cash'] = $request->boolean('is_cash');
        $data['is_active'] = $request->boolean('is_active');

        AccountingAccount::create($data);

        return back()->with('success', 'Akun accounting berhasil ditambahkan.');
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate([
            'period_code' => ['required', 'string', 'max:20', Rule::unique('accounting_periods', 'period_code')],
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['open', 'locked', 'closed'])],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['closed_at'] = $data['status'] === 'closed' ? now() : null;

        AccountingPeriod::create($data);

        return back()->with('success', 'Periode accounting berhasil dibuat.');
    }

    public function storeJournal(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'period_id' => ['nullable', 'exists:accounting_periods,id'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'exists:accounting_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.partner' => ['nullable', 'string', 'max:160'],
            'lines.*.cost_center' => ['nullable', 'string', 'max:120'],
        ]);

        $entryDate = Carbon::parse($data['entry_date']);
        $period = $data['period_id']
            ? AccountingPeriod::findOrFail($data['period_id'])
            : $this->periodForDate($entryDate, $request->user()->id);

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'period_id' => 'Periode accounting harus open sebelum jurnal bisa dibuat.',
            ]);
        }

        $lines = $this->normalizedJournalLines($data['lines']);
        [$totalDebit, $totalCredit] = $this->validatedBalancedTotals($lines);

        $entry = DB::transaction(function () use ($request, $data, $entryDate, $period, $lines, $totalDebit, $totalCredit) {
            $entry = AccountingJournalEntry::create([
                'journal_no' => $this->nextJournalNo($entryDate),
                'period_id' => $period->id,
                'entry_date' => $entryDate->toDateString(),
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'status' => 'reviewed',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => $request->user()->id,
                'metadata' => [
                    'source' => 'manual_general_ledger',
                ],
            ]);

            foreach ($lines as $index => $line) {
                $entry->lines()->create($line + [
                    'line_no' => $index + 1,
                ]);
            }

            $record = $this->upsertAccountingRecordForJournal($entry, $request);
            $entry->lines()->update(['erp_record_id' => $record->id]);
            $entry->update([
                'metadata' => array_merge($entry->metadata ?? [], [
                    'erp_record_id' => $record->id,
                    'erp_record_code' => $record->code,
                ]),
            ]);

            return $entry;
        });

        return redirect()
            ->route('accounting.ledger', ['period_id' => $entry->period_id, 'section' => 'journal'])
            ->with('success', 'Jurnal '.$entry->journal_no.' tersimpan dan siap diposting.');
    }

    public function postJournal(Request $request, AccountingJournalEntry $journalEntry)
    {
        $journalEntry->load(['period', 'lines']);

        if (! $journalEntry->isBalanced()) {
            return back()->withErrors('Jurnal belum balance, tidak bisa diposting.');
        }

        if (! $journalEntry->period?->isOpen()) {
            return back()->withErrors('Periode accounting tidak open, jurnal tidak bisa diposting.');
        }

        DB::transaction(function () use ($request, $journalEntry) {
            $journalEntry->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $request->user()->id,
            ]);

            $recordId = $journalEntry->metadata['erp_record_id'] ?? null;
            if ($recordId) {
                ErpRecord::whereKey($recordId)->update([
                    'status' => 'Posted',
                    'due_date' => $journalEntry->entry_date,
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Jurnal '.$journalEntry->journal_no.' berhasil diposting ke ledger.');
    }

    public function syncErpRecords(Request $request)
    {
        $created = 0;
        $skipped = 0;
        $paymentCreated = 0;

        $records = $this->recordsEligibleForAccountingSync()->limit(400)->get();

        foreach ($records as $record) {
            if ($record->module === 'accounting' && ! empty($record->extra['accounting_journal_entry_id'])) {
                $skipped++;
                continue;
            }

            $source = $this->canonicalSourceRecord($record);

            if ($source && AccountingJournalEntry::where('source_type', ErpRecord::class)->where('source_id', $source->id)->exists()) {
                $skipped++;
                continue;
            }

            $journal = $this->createJournalFromErpRecord($record, $request);

            if ($journal) {
                $created++;
            } else {
                $skipped++;
            }
        }

        Payment::with('record')
            ->where('status', 'paid')
            ->limit(400)
            ->get()
            ->each(function (Payment $payment) use ($request, &$paymentCreated, &$skipped) {
                if (AccountingJournalEntry::where('source_type', Payment::class)->where('source_id', $payment->id)->exists()) {
                    $skipped++;
                    return;
                }

                $journal = $this->createJournalFromPayment($payment, $request);

                if ($journal) {
                    $paymentCreated++;
                } else {
                    $skipped++;
                }
            });

        return back()->with('success', 'Sync accounting selesai: '.$created.' jurnal ERP dan '.$paymentCreated.' jurnal pembayaran dibuat, '.$skipped.' record dilewati karena sudah tersinkron atau belum memenuhi aturan jurnal.');
    }

    private function selectedPeriod(Request $request, $periods): ?AccountingPeriod
    {
        if ($request->filled('period_id')) {
            $period = $periods->firstWhere('id', (int) $request->period_id);
            if ($period) {
                return $period;
            }
        }

        return $periods->firstWhere('status', 'open') ?: $periods->first();
    }

    private function trialBalance($accounts, ?AccountingPeriod $period)
    {
        $lineTotals = AccountingJournalLine::query()
            ->select('accounting_journal_lines.account_id')
            ->selectRaw('sum(accounting_journal_lines.debit) as debit_total')
            ->selectRaw('sum(accounting_journal_lines.credit) as credit_total')
            ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->where('accounting_journal_entries.status', 'posted')
            ->when($period, fn ($query) => $query->where('accounting_journal_entries.period_id', $period->id))
            ->groupBy('accounting_journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        return $accounts->map(function (AccountingAccount $account) use ($lineTotals) {
            $totals = $lineTotals->get($account->id);
            $debit = round((float) ($totals->debit_total ?? 0), 2);
            $credit = round((float) ($totals->credit_total ?? 0), 2);
            $rawBalance = round($debit - $credit, 2);
            $displayBalance = $account->normal_balance === 'debit' ? $rawBalance : round($rawBalance * -1, 2);

            return [
                'account' => $account,
                'debit' => $debit,
                'credit' => $credit,
                'raw_balance' => $rawBalance,
                'display_balance' => $displayBalance,
            ];
        })->values();
    }

    private function normalizedJournalLines(array $input): array
    {
        $lines = collect($input)
            ->map(function (array $line) {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit > 0 && $credit > 0) {
                    throw ValidationException::withMessages([
                        'lines' => 'Satu baris jurnal hanya boleh berisi debit atau kredit, bukan keduanya.',
                    ]);
                }

                return [
                    'account_id' => $line['account_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                    'partner' => $line['partner'] ?? null,
                    'cost_center' => $line['cost_center'] ?? null,
                ];
            })
            ->filter(fn (array $line) => $line['account_id'] && ($line['debit'] > 0 || $line['credit'] > 0))
            ->values()
            ->all();

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'Minimal dua baris jurnal aktif dibutuhkan.',
            ]);
        }

        return $lines;
    }

    private function validatedBalancedTotals(array $lines): array
    {
        $totalDebit = round(collect($lines)->sum('debit'), 2);
        $totalCredit = round(collect($lines)->sum('credit'), 2);

        if ($totalDebit <= 0 || abs($totalDebit - $totalCredit) > 0.01) {
            throw ValidationException::withMessages([
                'lines' => 'Jurnal harus balance. Total debit Rp '.number_format($totalDebit, 0, ',', '.').' dan kredit Rp '.number_format($totalCredit, 0, ',', '.').'.',
            ]);
        }

        return [$totalDebit, $totalCredit];
    }

    private function nextJournalNo(Carbon $entryDate): string
    {
        $prefix = 'JRN-'.$entryDate->format('ym').'-';
        $lastNo = AccountingJournalEntry::where('journal_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('journal_no')
            ->value('journal_no');
        $sequence = $lastNo ? ((int) Str::afterLast($lastNo, '-')) + 1 : 1;

        do {
            $journalNo = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (AccountingJournalEntry::where('journal_no', $journalNo)->exists());

        return $journalNo;
    }

    private function periodForDate(Carbon $date, ?int $userId = null): AccountingPeriod
    {
        $period = AccountingPeriod::whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();

        if ($period) {
            return $period;
        }

        $start = $date->copy()->startOfMonth();

        return AccountingPeriod::firstOrCreate(
            ['period_code' => $start->format('Y-m')],
            [
                'name' => $start->translatedFormat('F Y'),
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
                'created_by' => $userId,
            ]
        );
    }

    private function upsertAccountingRecordForJournal(AccountingJournalEntry $entry, Request $request): ErpRecord
    {
        return ErpRecord::updateOrCreate(
            ['code' => $entry->journal_no],
            [
                'module' => 'accounting',
                'title' => $entry->description,
                'party' => $entry->period?->period_code,
                'category' => 'Journal Entry',
                'amount' => $entry->total_debit,
                'status' => Str::headline($entry->status),
                'owner' => $request->user()->name,
                'due_date' => $entry->entry_date,
                'notes' => 'Jurnal detail dibuat dari Advanced Accounting.',
                'extra' => [
                    'accounting_journal_entry_id' => $entry->id,
                    'accounting_journal_no' => $entry->journal_no,
                    'source' => 'advanced_accounting',
                ],
                'created_by' => $request->user()->id,
            ]
        );
    }

    private function recordsEligibleForAccountingSync()
    {
        return ErpRecord::query()
            ->where(function ($query) {
                $query->where(function ($inner) {
                    $inner->where('module', 'accounting')
                        ->whereIn('category', ['Journal Entry', 'Reconciliation', 'Accrual', 'Closing']);
                })
                    ->orWhere(function ($inner) {
                        $inner->where('module', 'finance')
                            ->whereIn('category', ['AR Invoice', 'AP Bill', 'Journal', 'Budget']);
                    })
                    ->orWhere(function ($inner) {
                        $inner->where('module', 'sales')
                            ->whereIn('status', ['Invoiced', 'Paid']);
                    })
                    ->orWhere(function ($inner) {
                        $inner->where('module', 'procurement')
                            ->whereIn('status', ['Received', 'Vendor Bill', 'Closed']);
                    })
                    ->orWhere(function ($inner) {
                        $inner->where('module', 'hris')
                            ->whereIn('category', ['Payroll Event', 'Reimbursement', 'Training', 'Business Trip'])
                            ->whereIn('status', ['Approved', 'Processed']);
                    })
                    ->orWhere(function ($inner) {
                        $inner->where('module', 'manufacturing')
                            ->where('status', 'Completed');
                    });
            })
            ->orderBy('id');
    }

    private function createJournalFromErpRecord(ErpRecord $record, Request $request): ?AccountingJournalEntry
    {
        $source = $this->canonicalSourceRecord($record);

        if (! $source) {
            return null;
        }

        $entryDate = Carbon::parse($source->due_date ?: $record->due_date ?: $source->created_at ?: now());
        $period = $this->periodForDate($entryDate, $request->user()->id);
        $lines = $this->linesForErpRecord($source, $record);

        if (count($lines) < 2) {
            return null;
        }

        [$totalDebit, $totalCredit] = $this->validatedBalancedTotals($lines);
        $status = $period->isOpen() && $this->erpRecordShouldPost($source) ? 'posted' : 'reviewed';

        return DB::transaction(function () use ($request, $record, $source, $entryDate, $period, $lines, $totalDebit, $totalCredit, $status) {
            $entry = AccountingJournalEntry::create([
                'journal_no' => $this->nextJournalNo($entryDate),
                'source_type' => ErpRecord::class,
                'source_id' => $source->id,
                'period_id' => $period->id,
                'entry_date' => $entryDate->toDateString(),
                'reference_no' => $source->code,
                'description' => $this->journalDescriptionForRecord($source),
                'status' => $status,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_at' => $status === 'posted' ? now() : null,
                'posted_by' => $status === 'posted' ? $request->user()->id : null,
                'created_by' => $request->user()->id,
                'metadata' => [
                    'source' => 'erp_record_sync',
                    'source_record_id' => $source->id,
                    'source_record_code' => $source->code,
                    'source_module' => $source->module,
                    'legacy_accounting_record_id' => $record->module === 'accounting' ? $record->id : null,
                ],
            ]);

            foreach ($lines as $index => $line) {
                $entry->lines()->create($line + [
                    'line_no' => $index + 1,
                    'erp_record_id' => $record->id,
                ]);
            }

            $this->attachJournalToAccountingRecord($entry, $source, $record, $request);

            return $entry;
        });
    }

    private function canonicalSourceRecord(ErpRecord $record): ?ErpRecord
    {
        $sourceId = (int) ($record->extra['integration_source_record_id'] ?? 0);
        $sourceModule = $record->extra['integration_source_module'] ?? null;

        if ($record->module === 'accounting' && $sourceId > 0 && $sourceModule && $sourceModule !== 'accounting') {
            return ErpRecord::find($sourceId) ?: $record;
        }

        return $record;
    }

    private function linesForErpRecord(ErpRecord $source, ErpRecord $visibleRecord): array
    {
        $amount = round(abs((float) $source->amount), 2);
        if ($amount <= 0) {
            return [];
        }

        $extra = array_merge($visibleRecord->extra ?? [], $source->extra ?? []);
        $financeCategory = $source->module === 'finance' ? $source->category : ($extra['finance_category'] ?? null);
        $partner = $source->party ?: $visibleRecord->party ?: $source->title;
        $costCenter = $extra['department'] ?? $source->party ?? null;

        if ($financeCategory === 'AR Invoice' || ($source->module === 'sales' && in_array($source->status, ['Invoiced', 'Paid'], true))) {
            $tax = round((float) ($extra['tax_amount'] ?? 0), 2);
            $net = round((float) ($extra['net_amount'] ?? max($amount - $tax, 0)), 2);
            if ($tax <= 0 && $net > 0) {
                $tax = round($net * (float) ($extra['tax_rate'] ?? 0.11), 2);
            }
            $gross = round(max($amount, $net + $tax), 2);
            $debitAccount = $financeCategory === 'AR Invoice' ? '1102' : ($source->status === 'Paid' ? '1101' : '1102');

            return array_values(array_filter([
                $this->line($debitAccount, $gross, 0, 'Tagihan / penerimaan penjualan '.$source->code, $partner, $costCenter),
                $this->line('4100', 0, $net ?: $amount, 'Pendapatan '.$source->code, $partner, $costCenter),
                $tax > 0 ? $this->line('2201', 0, $tax, 'PPN keluaran '.$source->code, $partner, $costCenter) : null,
            ]));
        }

        if ($financeCategory === 'AP Bill' || in_array($source->category, ['Payroll Event', 'Reimbursement', 'Training', 'Business Trip'], true)) {
            $expenseAccount = $this->expenseAccountForRecord($source, $extra);
            $creditAccount = $financeCategory === 'AP Bill'
                ? '2101'
                : (in_array($source->status, ['Paid', 'Processed'], true) ? '1101' : '2101');

            return [
                $this->line($expenseAccount, $amount, 0, 'Beban '.$source->category.' '.$source->code, $partner, $costCenter),
                $this->line($creditAccount, 0, $amount, 'Kewajiban / pembayaran '.$source->code, $partner, $costCenter),
            ];
        }

        if ($financeCategory === 'Budget' || $source->category === 'Accrual') {
            return [
                $this->line('5100', $amount, 0, 'Pengakuan budget / accrual '.$source->code, $partner, $costCenter),
                $this->line('2202', 0, $amount, 'Accrued expense '.$source->code, $partner, $costCenter),
            ];
        }

        if ($source->module === 'procurement') {
            $debitAccount = $source->category === 'Asset' ? '1501' : (in_array($source->category, ['Raw Material', 'Spare Part'], true) ? '1301' : '5100');
            $creditAccount = $source->status === 'Closed' ? '1101' : '2101';

            return [
                $this->line($debitAccount, $amount, 0, 'Penerimaan procurement '.$source->code, $partner, $costCenter),
                $this->line($creditAccount, 0, $amount, 'Utang / pembayaran procurement '.$source->code, $partner, $costCenter),
            ];
        }

        if ($source->module === 'manufacturing') {
            return [
                $this->line('1301', $amount, 0, 'Persediaan hasil produksi '.$source->code, $partner, $costCenter),
                $this->line('5100', 0, $amount, 'Clearing biaya produksi '.$source->code, $partner, $costCenter),
            ];
        }

        if ($financeCategory === 'Journal' || $source->category === 'Journal Entry') {
            return [
                $this->line('1199', $amount, 0, 'Debit clearing '.$source->code, $partner, $costCenter),
                $this->line('3101', 0, $amount, 'Kredit balancing '.$source->code, $partner, $costCenter),
            ];
        }

        if (in_array($source->category, ['Reconciliation', 'Closing'], true)) {
            return [
                $this->line('1101', $amount, 0, 'Rekonsiliasi '.$source->code, $partner, $costCenter),
                $this->line('1199', 0, $amount, 'Clearing rekonsiliasi '.$source->code, $partner, $costCenter),
            ];
        }

        return [];
    }

    private function expenseAccountForRecord(ErpRecord $record, array $extra): string
    {
        if ($record->category === 'Payroll Event' || ! empty($extra['employee_payroll_id'])) {
            return '5200';
        }

        if (in_array($record->category, ['Training', 'Business Trip'], true)
            || filter_var($extra['training_payment_required'] ?? false, FILTER_VALIDATE_BOOL)
            || filter_var($extra['business_trip_payment_required'] ?? false, FILTER_VALIDATE_BOOL)) {
            return '5400';
        }

        if (($extra['shipping_fee_type'] ?? null) === 'courier_cost' || str_contains(strtolower((string) $record->notes), 'kurir')) {
            return '5300';
        }

        return '5100';
    }

    private function line(string $accountCode, float $debit, float $credit, string $description, ?string $partner, ?string $costCenter): array
    {
        return [
            'account_id' => $this->accountByCode($accountCode)->id,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'partner' => $partner,
            'cost_center' => $costCenter,
        ];
    }

    private function accountByCode(string $code): AccountingAccount
    {
        $defaults = self::DEFAULT_ACCOUNTS[$code] ?? self::DEFAULT_ACCOUNTS['1199'];

        return AccountingAccount::firstOrCreate(
            ['code' => $code],
            $defaults + [
                'description' => 'Akun default dibuat otomatis oleh Advanced Accounting.',
                'is_active' => true,
            ]
        );
    }

    private function erpRecordShouldPost(ErpRecord $record): bool
    {
        return in_array($record->status, ['Posted', 'Paid', 'Closed', 'Processed', 'Completed'], true);
    }

    private function journalDescriptionForRecord(ErpRecord $record): string
    {
        return 'Jurnal otomatis '.$record->module.' '.$record->code.' - '.$record->title;
    }

    private function attachJournalToAccountingRecord(AccountingJournalEntry $entry, ErpRecord $source, ErpRecord $visibleRecord, Request $request): void
    {
        $accountingRecord = $visibleRecord->module === 'accounting'
            ? $visibleRecord
            : $this->linkedAccountingRecord($source);

        if (! $accountingRecord) {
            $accountingRecord = ErpRecord::create([
                'module' => 'accounting',
                'code' => 'ACC-GL-'.$entry->id,
                'title' => $entry->description,
                'party' => $entry->period?->period_code,
                'category' => 'Journal Entry',
                'amount' => $entry->total_debit,
                'status' => Str::headline($entry->status),
                'owner' => $request->user()->name,
                'due_date' => $entry->entry_date,
                'notes' => 'Accounting record otomatis dari journal detail '.$entry->journal_no.'.',
                'extra' => [
                    'integration_source_record_id' => $source->id,
                    'integration_source_code' => $source->code,
                    'integration_source_module' => $source->module,
                    'accounting_journal_entry_id' => $entry->id,
                    'accounting_journal_no' => $entry->journal_no,
                ],
                'created_by' => $request->user()->id,
            ]);

            ErpRecordLink::updateOrCreate(
                [
                    'source_record_id' => $source->id,
                    'target_record_id' => $accountingRecord->id,
                    'relation_type' => 'advanced_accounting_journal',
                ],
                [
                    'status' => 'active',
                    'metadata' => [
                        'journal_no' => $entry->journal_no,
                        'synced_at' => now()->toDateTimeString(),
                    ],
                    'created_by' => $request->user()->id,
                ]
            );
        }

        $extra = array_merge($accountingRecord->extra ?? [], [
            'accounting_journal_entry_id' => $entry->id,
            'accounting_journal_no' => $entry->journal_no,
            'accounting_period_id' => $entry->period_id,
            'accounting_synced_at' => now()->toDateTimeString(),
        ]);

        $accountingRecord->update([
            'amount' => $entry->total_debit,
            'status' => Str::headline($entry->status),
            'due_date' => $entry->entry_date,
            'extra' => $extra,
        ]);

        $entry->update([
            'metadata' => array_merge($entry->metadata ?? [], [
                'erp_record_id' => $accountingRecord->id,
                'erp_record_code' => $accountingRecord->code,
            ]),
        ]);
    }

    private function linkedAccountingRecord(ErpRecord $source): ?ErpRecord
    {
        $link = ErpRecordLink::with('targetRecord')
            ->where('source_record_id', $source->id)
            ->whereHas('targetRecord', fn ($query) => $query->where('module', 'accounting'))
            ->latest()
            ->first();

        return $link?->targetRecord;
    }

    private function createJournalFromPayment(Payment $payment, Request $request): ?AccountingJournalEntry
    {
        $payment->loadMissing('record');

        if (! $payment->record || round((float) $payment->amount, 2) <= 0) {
            return null;
        }

        $entryDate = Carbon::parse($payment->paid_at ?: now());
        $period = $this->periodForDate($entryDate, $request->user()->id);
        $lines = $this->linesForPayment($payment);

        if (count($lines) < 2) {
            return null;
        }

        [$totalDebit, $totalCredit] = $this->validatedBalancedTotals($lines);
        $status = $period->isOpen() ? 'posted' : 'reviewed';

        return DB::transaction(function () use ($request, $payment, $entryDate, $period, $lines, $totalDebit, $totalCredit, $status) {
            $entry = AccountingJournalEntry::create([
                'journal_no' => $this->nextJournalNo($entryDate),
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'period_id' => $period->id,
                'entry_date' => $entryDate->toDateString(),
                'reference_no' => $payment->order_id,
                'description' => 'Jurnal pembayaran '.$payment->order_id.' untuk '.$payment->record->code,
                'status' => $status,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_at' => $status === 'posted' ? now() : null,
                'posted_by' => $status === 'posted' ? $request->user()->id : null,
                'created_by' => $request->user()->id,
                'metadata' => [
                    'source' => 'payment_sync',
                    'payment_id' => $payment->id,
                    'payment_provider' => $payment->provider,
                    'payment_order_id' => $payment->order_id,
                    'erp_record_id' => $payment->erp_record_id,
                    'erp_record_code' => $payment->record->code,
                ],
            ]);

            foreach ($lines as $index => $line) {
                $entry->lines()->create($line + [
                    'line_no' => $index + 1,
                    'erp_record_id' => $payment->erp_record_id,
                ]);
            }

            $this->attachJournalToPaymentRecord($entry, $payment, $request);

            return $entry;
        });
    }

    private function linesForPayment(Payment $payment): array
    {
        $record = $payment->record;
        $amount = round((float) $payment->amount, 2);
        $partner = $record->party ?: $record->title;
        $costCenter = $record->extra['department'] ?? null;

        if ($record->category === 'AR Invoice') {
            return [
                $this->line('1101', $amount, 0, 'Kas masuk '.$payment->order_id, $partner, $costCenter),
                $this->line('1102', 0, $amount, 'Pelunasan piutang '.$record->code, $partner, $costCenter),
            ];
        }

        if ($record->category === 'AP Bill') {
            return [
                $this->line('2101', $amount, 0, 'Pelunasan utang '.$record->code, $partner, $costCenter),
                $this->line('1101', 0, $amount, 'Kas keluar '.$payment->order_id, $partner, $costCenter),
            ];
        }

        return [
            $this->line('1199', $amount, 0, 'Clearing pembayaran '.$payment->order_id, $partner, $costCenter),
            $this->line('1101', 0, $amount, 'Kas pembayaran '.$payment->order_id, $partner, $costCenter),
        ];
    }

    private function attachJournalToPaymentRecord(AccountingJournalEntry $entry, Payment $payment, Request $request): void
    {
        $record = $payment->record;
        $accountingRecord = ErpRecord::updateOrCreate(
            ['code' => 'ACC-PAY-'.$payment->id],
            [
                'module' => 'accounting',
                'title' => 'Jurnal Pembayaran '.$payment->order_id,
                'party' => $record->party ?: $record->title,
                'category' => 'Journal Entry',
                'amount' => $entry->total_debit,
                'status' => Str::headline($entry->status),
                'owner' => $request->user()->name,
                'due_date' => $entry->entry_date,
                'notes' => 'Jurnal kas otomatis dari pembayaran '.$payment->provider.'.',
                'extra' => [
                    'payment_id' => $payment->id,
                    'payment_provider' => $payment->provider,
                    'payment_order_id' => $payment->order_id,
                    'payment_status' => $payment->status,
                    'integration_source_record_id' => $record->id,
                    'integration_source_code' => $record->code,
                    'integration_source_module' => $record->module,
                    'accounting_journal_entry_id' => $entry->id,
                    'accounting_journal_no' => $entry->journal_no,
                    'accounting_period_id' => $entry->period_id,
                ],
                'created_by' => $request->user()->id,
            ]
        );

        ErpRecordLink::updateOrCreate(
            [
                'source_record_id' => $record->id,
                'target_record_id' => $accountingRecord->id,
                'relation_type' => 'payment_cash_journal',
            ],
            [
                'status' => 'active',
                'metadata' => [
                    'payment_id' => $payment->id,
                    'payment_order_id' => $payment->order_id,
                    'journal_no' => $entry->journal_no,
                    'synced_at' => now()->toDateTimeString(),
                ],
                'created_by' => $request->user()->id,
            ]
        );

        $entry->update([
            'metadata' => array_merge($entry->metadata ?? [], [
                'erp_record_id' => $accountingRecord->id,
                'erp_record_code' => $accountingRecord->code,
            ]),
        ]);
    }
}

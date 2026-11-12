<?php

namespace App\Support;

use App\Models\EmployeeProfile;
use App\Models\ErpRecord;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ErpFormOptions
{
    public static function applyToModule(string $module, array $config, ?ErpRecord $record = null, ?User $user = null): array
    {
        $relationPayloads = collect(self::relationSources($module, $user, $record))
            ->pluck('payload')
            ->filter();

        foreach ($config['fields'] as $index => $field) {
            if (! in_array($field['type'] ?? null, ['text', 'select'], true)) {
                continue;
            }

            $name = $field['name'];
            $options = self::optionsForField($module, $name, $field, $record);

            if ($relationPayloads->isNotEmpty()) {
                $options = self::mergeOptions(
                    $options,
                    $relationPayloads->pluck($name)->filter()->all()
                );
            }

            if (! empty($options)) {
                $config['fields'][$index]['type'] = 'select';
                $config['fields'][$index]['options'] = self::mergeOptions($options, [$record?->{$name}]);
                $config['fields'][$index]['placeholder'] = 'Pilih '.$field['label'];
                $config['fields'][$index]['integrated'] = true;
            }
        }

        return $config;
    }

    public static function relationSources(string $module, ?User $user = null, ?ErpRecord $record = null): array
    {
        return collect(self::relationSourceDefinitions($module))
            ->flatMap(function (array $definition) use ($module, $user, $record) {
                if ($user && ! $user->hasPrivilege("modules.{$definition['source_module']}.view")) {
                    return [];
                }

                return ErpRecord::where('module', $definition['source_module'])
                    ->latest()
                    ->limit(80)
                    ->get()
                    ->map(function (ErpRecord $source) use ($module, $definition, $record) {
                        $payload = self::payloadForRelation($module, $source, $definition['relation_type']);

                        if (empty($payload)) {
                            return null;
                        }

                        return [
                            'id' => $source->id,
                            'module' => $source->module,
                            'code' => $source->code,
                            'relation_type' => $definition['relation_type'],
                            'relation_label' => $definition['label'],
                            'label' => $definition['label'].' - '.$source->code.' - '.$source->title,
                            'payload' => $payload,
                            'selected' => self::isSelectedRelation($record, $source, $definition['relation_type']),
                        ];
                    })
                    ->filter();
            })
            ->values()
            ->all();
    }

    public static function relationTypesForTarget(string $module): array
    {
        return collect(self::relationSourceDefinitions($module))
            ->pluck('relation_type')
            ->unique()
            ->values()
            ->all();
    }

    public static function payloadForRelation(string $targetModule, ErpRecord $source, string $relationType): array
    {
        if ($relationType === 'hris_payroll_payable' && ! self::hrisPayableSourceReady($source)) {
            return [];
        }

        $payload = match ($relationType) {
            'crm_sales_order' => [
                'title' => $source->party ?: $source->title,
                'party' => 'B2B',
                'category' => $source->title,
                'amount' => $source->amount,
                'status' => 'Confirmed',
                'owner' => $source->owner,
                'due_date' => $source->due_date,
                'notes' => 'Sales order dari opportunity '.$source->code.'.',
            ],
            'marketing_sales_order' => [
                'title' => $source->title,
                'party' => in_array($source->category, ['Telemarketing', 'Event'], true) ? $source->category : 'B2B',
                'category' => $source->party,
                'amount' => $source->amount,
                'status' => $source->status === 'Closed' ? 'Paid' : 'Confirmed',
                'owner' => $source->owner,
                'due_date' => $source->due_date,
                'notes' => 'Sales order dari marketing '.$source->code.'.',
            ],
            'sales_ar_invoice' => [
                'category' => 'AR Invoice',
                'title' => '1102 Piutang Usaha',
                'party' => $source->title,
                'amount' => $source->amount,
                'status' => $source->status === 'Paid' ? 'Paid' : 'Open',
                'due_date' => $source->due_date,
                'notes' => 'AR invoice dari sales order '.$source->code.'.',
            ],
            'sales_revenue_journal' => [
                'category' => 'Journal Entry',
                'title' => 'Jurnal Penjualan '.$source->code,
                'party' => $source->title,
                'amount' => $source->amount,
                'status' => $source->status === 'Paid' ? 'Posted' : 'Reviewed',
                'owner' => $source->owner,
                'due_date' => $source->due_date ?: now()->toDateString(),
                'notes' => 'Jurnal penjualan dari '.$source->code.'.',
            ],
            'procurement_ap_bill' => [
                'category' => 'AP Bill',
                'title' => '2101 Utang Vendor',
                'party' => $source->title,
                'amount' => $source->amount,
                'status' => $source->status === 'Closed' ? 'Paid' : ($source->status === 'Ordered' ? 'Open' : 'Approved'),
                'due_date' => $source->due_date,
                'notes' => 'AP bill dari procurement '.$source->code.'.',
            ],
            'procurement_inventory_receipt' => [
                'title' => $source->title,
                'party' => 'Jakarta',
                'category' => self::inventoryCategory($source->category),
                'quantity' => $source->quantity ?: 1,
                'min_quantity' => $source->min_quantity ?: 1,
                'amount' => $source->amount,
                'status' => 'Available',
                'notes' => 'Stok dari penerimaan procurement '.$source->code.'.',
            ],
            'procurement_asset_receipt' => [
                'title' => $source->title,
                'party' => 'Jakarta',
                'owner' => $source->owner,
                'amount' => $source->amount,
                'category' => 'Good',
                'status' => 'In Use',
                'due_date' => $source->due_date,
                'notes' => 'Aset dari penerimaan procurement '.$source->code.'.',
            ],
            'finance_ledger_journal' => [
                'category' => 'Journal Entry',
                'title' => 'Jurnal '.$source->category.' '.$source->code,
                'party' => $source->party ?: $source->title,
                'amount' => $source->amount,
                'status' => in_array($source->status, ['Posted', 'Paid'], true) ? 'Posted' : 'Reviewed',
                'due_date' => $source->due_date ?: now()->toDateString(),
                'notes' => 'Ledger dari finance '.$source->code.'.',
            ],
            'manufacturing_finished_goods' => [
                'title' => $source->title,
                'party' => $source->party ?: 'Jakarta',
                'category' => 'Finished Goods',
                'quantity' => $source->quantity ?: 1,
                'min_quantity' => 1,
                'amount' => $source->amount,
                'status' => 'Available',
                'notes' => 'Finished goods dari work order '.$source->code.'.',
            ],
            'project_milestone_ar' => [
                'category' => 'AR Invoice',
                'title' => '1102 Piutang Usaha',
                'party' => $source->party,
                'amount' => round(((float) $source->amount * max((int) $source->progress, 1)) / 100, 2),
                'status' => 'Open',
                'due_date' => $source->due_date,
                'notes' => 'AR milestone project '.$source->code.'.',
            ],
            'hris_payroll_payable' => [
                'category' => 'AP Bill',
                'title' => match ($source->category) {
                    'Reimbursement' => '2101 Utang Reimburse',
                    'Overtime' => '2101 Utang Lembur',
                    'Training' => '2101 Utang Pelatihan',
                    'Business Trip' => '2101 Utang Perjalanan Dinas',
                    default => '2101 Utang Payroll',
                },
                'party' => match ($source->category) {
                    'Training' => $source->extra['training_provider'] ?? $source->extra['employee_name'] ?? $source->title,
                    'Business Trip' => $source->extra['business_trip_payee_name'] ?? $source->extra['employee_name'] ?? $source->title,
                    default => $source->extra['employee_name'] ?? $source->title,
                },
                'amount' => $source->amount,
                'status' => $source->status === 'Processed' ? 'Paid' : 'Approved',
                'due_date' => match ($source->category) {
                    'Training' => $source->extra['training_payment_due_date'] ?? $source->due_date,
                    'Business Trip' => $source->extra['business_trip_payment_due_date'] ?? $source->due_date,
                    default => $source->due_date,
                },
                'notes' => match ($source->category) {
                    'Training' => 'AP pelatihan dari HRIS '.$source->code.'.',
                    'Business Trip' => 'AP perjalanan dinas dari HRIS '.$source->code.'.',
                    default => 'Payable payroll dari HRIS '.$source->code.'.',
                },
                'extra' => array_merge(self::employeeContextExtra($source), self::trainingPaymentExtra($source), self::businessTripPaymentExtra($source)),
            ],
            'asset_maintenance_procurement' => [
                'title' => 'Maintenance '.$source->title,
                'party' => 'Purchase Request',
                'category' => 'Service',
                'amount' => $source->amount,
                'status' => 'Purchase Request',
                'owner' => $source->owner,
                'due_date' => $source->due_date,
                'notes' => 'Purchase request maintenance dari aset '.$source->code.'.',
            ],
            'asset_maintenance_payable' => [
                'category' => 'AP Bill',
                'title' => '2101 Utang Vendor',
                'party' => 'Maintenance '.$source->title,
                'amount' => $source->amount,
                'status' => 'Open',
                'due_date' => $source->due_date,
                'notes' => 'AP maintenance dari aset '.$source->code.'.',
            ],
            default => [],
        };

        return self::payloadMatchesTarget($targetModule, $relationType) ? self::normalizePayload($payload) : [];
    }

    private static function optionsForField(string $module, string $name, array $field, ?ErpRecord $record): array
    {
        $options = match ($module.'.'.$name) {
            'sales.title', 'crm.party', 'projects.party' => self::customers($record),
            'sales.owner', 'procurement.owner', 'accounting.owner', 'crm.owner', 'projects.owner', 'hris.owner', 'assets.owner' => self::employees($record),
            'procurement.title' => self::mergeOptions(self::vendors($record), self::products($record), self::assets($record)),
            'inventory.title', 'manufacturing.title' => self::products($record),
            'finance.title', 'accounting.title' => self::accounts($record),
            'finance.party' => self::partners($record),
            'hris.title' => self::employees($record),
            'assets.title' => self::assets($record),
            default => [],
        };

        if (($field['type'] ?? null) === 'select') {
            $options = self::mergeOptions($field['options'] ?? [], $options);
        }

        return self::mergeOptions($options, [$record?->{$name}]);
    }

    private static function relationSourceDefinitions(string $module): array
    {
        return match ($module) {
            'sales' => [
                ['source_module' => 'crm', 'relation_type' => 'crm_sales_order', 'label' => 'CRM Opportunity'],
                ['source_module' => 'marketing', 'relation_type' => 'marketing_sales_order', 'label' => 'Marketing Lead'],
            ],
            'finance' => [
                ['source_module' => 'sales', 'relation_type' => 'sales_ar_invoice', 'label' => 'Sales Order'],
                ['source_module' => 'procurement', 'relation_type' => 'procurement_ap_bill', 'label' => 'Procurement'],
                ['source_module' => 'projects', 'relation_type' => 'project_milestone_ar', 'label' => 'Project Milestone'],
                ['source_module' => 'hris', 'relation_type' => 'hris_payroll_payable', 'label' => 'HRIS Payable'],
                ['source_module' => 'assets', 'relation_type' => 'asset_maintenance_payable', 'label' => 'Asset Maintenance'],
            ],
            'accounting' => [
                ['source_module' => 'sales', 'relation_type' => 'sales_revenue_journal', 'label' => 'Sales Revenue'],
                ['source_module' => 'finance', 'relation_type' => 'finance_ledger_journal', 'label' => 'Finance Ledger'],
            ],
            'inventory' => [
                ['source_module' => 'procurement', 'relation_type' => 'procurement_inventory_receipt', 'label' => 'Procurement Receipt'],
                ['source_module' => 'manufacturing', 'relation_type' => 'manufacturing_finished_goods', 'label' => 'Production Output'],
            ],
            'hris' => [],
            'procurement' => [
                ['source_module' => 'assets', 'relation_type' => 'asset_maintenance_procurement', 'label' => 'Asset Maintenance'],
            ],
            'assets' => [
                ['source_module' => 'procurement', 'relation_type' => 'procurement_asset_receipt', 'label' => 'Procurement Asset'],
            ],
            default => [],
        };
    }

    private static function payloadMatchesTarget(string $targetModule, string $relationType): bool
    {
        return in_array($relationType, self::relationTypesForTarget($targetModule), true);
    }

    private static function isSelectedRelation(?ErpRecord $record, ErpRecord $source, string $relationType): bool
    {
        if (! $record?->exists) {
            return false;
        }

        return $record->incomingLinks()
            ->where('source_record_id', $source->id)
            ->where('relation_type', $relationType)
            ->exists();
    }

    private static function customers(?ErpRecord $record): array
    {
        return self::mergeOptions(
            MasterDataRegistry::optionsForGroup('master.customers', []),
            self::recordValues('sales', 'title'),
            self::recordValues('crm', 'party'),
            self::recordValues('projects', 'party'),
            [$record?->title, $record?->party]
        );
    }

    private static function vendors(?ErpRecord $record): array
    {
        return self::mergeOptions(
            MasterDataRegistry::optionsForGroup('master.vendors', []),
            self::recordValues('procurement', 'title'),
            [$record?->title]
        );
    }

    private static function partners(?ErpRecord $record): array
    {
        return self::mergeOptions(
            self::customers($record),
            self::vendors($record),
            self::employees($record),
            self::recordValues('finance', 'party')
        );
    }

    private static function products(?ErpRecord $record): array
    {
        return self::mergeOptions(
            MasterDataRegistry::optionsForGroup('master.products', []),
            Schema::hasTable('products') ? Product::where('is_active', true)->orderBy('name')->pluck('name')->all() : [],
            self::recordValues('inventory', 'title'),
            self::recordValues('manufacturing', 'title'),
            [$record?->title]
        );
    }

    private static function assets(?ErpRecord $record): array
    {
        return self::mergeOptions(
            self::recordValues('assets', 'title'),
            self::products($record),
            [$record?->title]
        );
    }

    private static function accounts(?ErpRecord $record): array
    {
        return self::mergeOptions(
            MasterDataRegistry::optionsForGroup('master.accounts', []),
            self::recordValues('finance', 'title'),
            self::recordValues('accounting', 'title'),
            [$record?->title]
        );
    }

    private static function employees(?ErpRecord $record): array
    {
        return self::mergeOptions(
            MasterDataRegistry::optionsForGroup('master.employees', []),
            User::where('role', 'employee')->orderBy('name')->pluck('name')->all(),
            EmployeeProfile::with('user')->get()->map(fn (EmployeeProfile $profile) => $profile->user?->name ?: $profile->bank_account_name)->all(),
            self::recordValues(null, 'owner'),
            [$record?->title, $record?->owner]
        );
    }

    private static function positions(?ErpRecord $record): array
    {
        return self::mergeOptions(
            ['Sales Manager', 'ERP Lead', 'Procurement Lead', 'Controller', 'Warehouse Supervisor', 'Employee'],
            EmployeeProfile::query()->pluck('position')->all(),
            [$record?->category]
        );
    }

    private static function recordValues(?string $module, string $field): array
    {
        $query = ErpRecord::query()
            ->whereNotNull($field)
            ->where($field, '!=', '');

        if ($module) {
            $query->where('module', $module);
        }

        return $query->distinct()->orderBy($field)->limit(120)->pluck($field)->all();
    }

    private static function mergeOptions(...$groups): array
    {
        return collect($groups)
            ->flatten()
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->reject(fn ($value) => strtolower($value) === 'internal' && ! in_array($value, ['Internal'], true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function normalizePayload(array $payload): array
    {
        return collect($payload)
            ->map(function ($value) {
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d');
                }

                return $value;
            })
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private static function inventoryCategory(?string $category): string
    {
        return match ($category) {
            'Raw Material' => 'Raw Material',
            'Spare Part', 'IT', 'Office' => 'MRO',
            default => 'Finished Goods',
        };
    }

    private static function employeeContextExtra(ErpRecord $record): array
    {
        return collect($record->extra ?? [])
            ->only([
                'employee_profile_id',
                'employee_no',
                'user_id',
                'employee_name',
                'department',
                'position',
                'job_level',
                'grade',
                'branch',
                'work_location',
                'manager_profile_id',
                'manager_name',
                'employee_payroll_id',
                'employee_request_id',
                'employee_reimbursement_id',
                'employee_attendance_id',
                'source_master',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private static function hrisPayableSourceReady(ErpRecord $record): bool
    {
        if ($record->module !== 'hris'
            || ! in_array($record->category, ['Payroll Event', 'Overtime', 'Reimbursement', 'Training', 'Business Trip'], true)
            || ! in_array($record->status, ['Approved', 'Processed'], true)
            || (float) $record->amount <= 0) {
            return false;
        }

        if ($record->category === 'Overtime' && ($record->extra['payment_mode'] ?? 'payroll') === 'payroll') {
            return false;
        }

        if ($record->category === 'Reimbursement' && ($record->extra['payment_mode'] ?? 'payroll') === 'payroll') {
            return false;
        }

        if ($record->category === 'Training') {
            $extra = $record->extra ?? [];
            $paymentRequired = array_key_exists('training_payment_required', $extra)
                ? filter_var($extra['training_payment_required'], FILTER_VALIDATE_BOOL)
                : true;

            return $paymentRequired
                && trim((string) ($extra['training_provider'] ?? '')) !== ''
                && trim((string) ($extra['training_payment_method'] ?? '')) !== '';
        }

        if ($record->category === 'Business Trip') {
            $extra = $record->extra ?? [];
            $paymentRequired = array_key_exists('business_trip_payment_required', $extra)
                ? filter_var($extra['business_trip_payment_required'], FILTER_VALIDATE_BOOL)
                : true;

            return $paymentRequired
                && trim((string) ($extra['business_trip_payee_name'] ?? $extra['employee_name'] ?? '')) !== ''
                && trim((string) ($extra['business_trip_payment_method'] ?? '')) !== '';
        }

        return true;
    }

    private static function trainingPaymentExtra(ErpRecord $record): array
    {
        if ($record->category !== 'Training') {
            return [];
        }

        return collect($record->extra ?? [])
            ->only([
                'training_payment_required',
                'training_provider',
                'training_invoice_no',
                'training_payment_method',
                'training_bank_name',
                'training_bank_account_number',
                'training_bank_account_name',
                'training_payment_due_date',
                'training_payment_reference',
                'training_payment_notes',
                'training_payment_amount',
                'training_payment_status',
                'training_paid_at',
                'training_payment_order_id',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private static function businessTripPaymentExtra(ErpRecord $record): array
    {
        if ($record->category !== 'Business Trip') {
            return [];
        }

        return collect($record->extra ?? [])
            ->only([
                'business_trip_payment_required',
                'business_trip_payee_type',
                'business_trip_payee_name',
                'business_trip_invoice_no',
                'business_trip_payment_method',
                'business_trip_bank_name',
                'business_trip_bank_account_number',
                'business_trip_bank_account_name',
                'business_trip_payment_due_date',
                'business_trip_destination',
                'business_trip_purpose',
                'business_trip_transport_mode',
                'business_trip_payment_reference',
                'business_trip_payment_notes',
                'business_trip_payment_amount',
                'business_trip_payment_status',
                'business_trip_paid_at',
                'business_trip_payment_order_id',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}

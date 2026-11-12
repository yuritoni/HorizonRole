<?php

namespace App\Http\Controllers;

use App\Models\ErpRecord;
use App\Models\ErpRecordLink;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Services\ErpModuleIntegrationService;
use App\Services\EmployeePayrollService;
use App\Services\HrisEmployeeIntegrationService;
use App\Services\ErpNotificationService;
use App\Services\ProductIntegrationService;
use App\Support\ErpFormOptions;
use App\Support\ErpModules;
use App\Support\ErpViewData;
use App\Support\MasterDataRegistry;
use App\Support\PaymentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ErpRecordController extends Controller
{
    public function index(Request $request, string $module)
    {
        if ($module === 'hr') {
            abort_unless($request->user()?->hasPrivilege('employee.manage'), 403, 'SDM sudah digabung ke Employee Management.');

            return redirect()
                ->route('settings.employees')
                ->with('success', 'SDM sudah digabung ke Employee Management sebagai satu data master tanpa duplikasi.');
        }

        $config = $this->moduleConfig($module);
        $this->authorizeModule($request, $module, 'view');
        $query = ErpRecord::where('module', $module);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('party', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('owner', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $relations = ['payments', 'outgoingLinks.targetRecord', 'incomingLinks.sourceRecord'];

        if (Schema::hasTable('erp_record_product_lines')) {
            $relations[] = 'productLines.product';
        }

        $records = $query->with($relations)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $records->getCollection()->each(function (ErpRecord $record) {
            $outgoingLinks = collect($record->outgoingLinks->all())
                ->where('status', 'active')
                ->map(fn ($link) => [
                    'link' => $link,
                    'record' => $link->targetRecord,
                    'direction' => 'out',
                ]);

            $incomingLinks = collect($record->incomingLinks->all())
                ->where('status', 'active')
                ->map(fn ($link) => [
                    'link' => $link,
                    'record' => $link->sourceRecord,
                    'direction' => 'in',
                ]);

            $record->setRelation(
                'displayRelatedLinks',
                $outgoingLinks->concat($incomingLinks)
                    ->filter(fn ($item) => $item['record'])
                    ->values()
            );

            $record->setRelation('displayFinancePaymentRecord', PaymentRules::financeGatewayRecord($record));
        });

        $allRecords = ErpRecord::where('module', $module)->get();

        return view('records.index', array_merge(ErpViewData::layout(), [
            'module' => $module,
            'config' => $config,
            'records' => $records,
            'allRecords' => $allRecords,
            'activeStatus' => $request->get('status', 'all'),
            'search' => $request->get('q', ''),
            'privileges' => $this->modulePrivileges($request, $module),
        ]));
    }

    public function create(Request $request, string $module)
    {
        if ($module === 'hr') {
            abort_unless($request->user()?->hasPrivilege('employee.manage'), 403, 'SDM sudah digabung ke Employee Management.');

            return redirect()->route('settings.employees');
        }

        $config = $this->formConfig($module, null, $request);
        $this->authorizeModule($request, $module, 'create');

        return view('records.create', array_merge(ErpViewData::layout(), $this->productFormData($module), [
            'module' => $module,
            'config' => $config,
            'relationSources' => ErpFormOptions::relationSources($module, $request->user()),
            'employeeProfiles' => $this->employeeProfilesForForm($module),
            'record' => new ErpRecord([
                'code' => $this->generateCode($module, $config),
                'status' => $config['statuses'][0],
            ]),
        ]));
    }

    public function store(Request $request, string $module)
    {
        abort_if($module === 'hr', 404);

        $config = $this->formConfig($module, null, $request);
        $this->authorizeModule($request, $module, 'create');
        $relationInput = $this->relationInput($request, $module);
        $this->mergeRelationPayloadIntoRequest($request, $relationInput, $config);
        $productContext = app(ProductIntegrationService::class)->prepareRecordInput($request, $module);
        $data = $this->validatedData($request, $config);
        $this->validateFinanceOnlySalesPaidStatus($module, $data);
        $this->validateHrisEmployeeSelection($request, $module, $data);

        if ($this->isHrisPayrollInput($module, $data)) {
            return $this->storeHrisPayrollFromInput($request, $data, $config);
        }

        $data['module'] = $module;
        $data['code'] = $this->generateCode($module, $config);
        $data['created_by'] = $request->user()->id;

        $record = ErpRecord::create($this->normalizeData($module, $data));
        $this->mergeHrisPayableInfoIfNeeded($request, $module, $record, $data);
        $this->syncInputRelation($record, $relationInput, $request);
        $this->syncEmployeeRecordIfNeeded($module, $record, $request);
        $this->syncPayrollAfterReimbursementIfNeeded($module, $record->fresh(), $request);
        app(ProductIntegrationService::class)->syncRecord($record->fresh(), $productContext, $request->user());
        $record->refresh();
        $this->syncModuleRecordRelations($record, $request);

        app(ErpNotificationService::class)->notifyPrivilege("modules.{$module}.view", [
            'type' => 'record.created',
            'severity' => 'info',
            'module' => $module,
            'erp_record_id' => $record->id,
            'title' => $config['label'].' baru: '.$record->code,
            'body' => $record->title.' dibuat dengan status '.$record->status.' senilai Rp '.number_format($record->amount, 0, ',', '.').'.',
            'action_url' => route('records.index', $module),
        ], $request->user());

        return redirect()->route('records.index', $module)->with('success', $config['label'].' berhasil ditambahkan.');
    }

    public function edit(string $module, ErpRecord $record)
    {
        if ($module === 'hr') {
            abort_unless(request()->user()?->hasPrivilege('employee.manage'), 403, 'SDM sudah digabung ke Employee Management.');

            return redirect()->route('settings.employees');
        }

        $config = $this->formConfig($module, $record, request());
        $this->ensureRecordModule($module, $record);
        $this->authorizeModule(request(), $module, 'edit');

        return view('records.edit', array_merge(ErpViewData::layout(), $this->productFormData($module, $record), [
            'module' => $module,
            'config' => $config,
            'relationSources' => ErpFormOptions::relationSources($module, request()->user(), $record),
            'employeeProfiles' => $this->employeeProfilesForForm($module),
            'record' => $record,
        ]));
    }

    public function update(Request $request, string $module, ErpRecord $record)
    {
        abort_if($module === 'hr', 404);

        $config = $this->formConfig($module, $record, $request);
        $this->ensureRecordModule($module, $record);
        $this->authorizeModule($request, $module, 'edit');
        $relationInput = $this->relationInput($request, $module);
        $this->mergeRelationPayloadIntoRequest($request, $relationInput, $config);
        $productContext = app(ProductIntegrationService::class)->prepareRecordInput($request, $module);
        $oldStatus = $record->status;
        $data = $this->validatedData($request, $config);
        $this->validateFinanceOnlySalesPaidStatus($module, $data, $record);
        $this->validateHrisEmployeeSelection($request, $module, $data, $record);

        if ($this->isHrisPayrollInput($module, $data)) {
            return $this->updateHrisPayrollFromInput($request, $record, $data, $config, $oldStatus);
        }

        $record->update($this->normalizeData($module, $data));
        $this->mergeHrisPayableInfoIfNeeded($request, $module, $record, $data);
        $this->syncInputRelation($record, $relationInput, $request);
        $this->syncEmployeeRecordIfNeeded($module, $record, $request);
        $this->syncPayrollAfterReimbursementIfNeeded($module, $record->fresh(), $request);
        app(ProductIntegrationService::class)->syncRecord($record->fresh(), $productContext, $request->user());
        $record->refresh();
        $this->syncModuleRecordRelations($record, $request);

        app(ErpNotificationService::class)->notifyPrivilege("modules.{$module}.view", [
            'type' => 'record.updated',
            'severity' => $oldStatus !== $record->status ? 'warning' : 'info',
            'module' => $module,
            'erp_record_id' => $record->id,
            'title' => $config['label'].' diperbarui: '.$record->code,
            'body' => $record->title.' diperbarui'.($oldStatus !== $record->status ? ' dari '.$oldStatus.' ke '.$record->status : '').'.',
            'action_url' => route('records.index', $module),
        ], $request->user());

        return redirect()->route('records.index', $module)->with('success', $config['label'].' berhasil diperbarui.');
    }

    public function destroy(string $module, ErpRecord $record)
    {
        abort_if($module === 'hr', 404);

        $config = $this->moduleConfig($module);
        $this->ensureRecordModule($module, $record);
        $this->authorizeModule(request(), $module, 'delete');
        $code = $record->code;
        $title = $record->title;
        app(ProductIntegrationService::class)->cancelRecordMovements($record);
        $record->delete();

        app(ErpNotificationService::class)->notifyPrivilege("modules.{$module}.view", [
            'type' => 'record.deleted',
            'severity' => 'danger',
            'module' => $module,
            'title' => $config['label'].' dihapus: '.$code,
            'body' => $title.' sudah dihapus dari modul '.$config['label'].'.',
            'action_url' => route('records.index', $module),
        ], request()->user());

        return redirect()->route('records.index', $module)->with('success', 'Record berhasil dihapus.');
    }

    public function advance(string $module, ErpRecord $record)
    {
        abort_if($module === 'hr', 404);

        $config = $this->moduleConfig($module);
        $this->ensureRecordModule($module, $record);
        $this->authorizeModule(request(), $module, 'advance');
        $index = array_search($record->status, $config['statuses'], true);

        if ($index === false || $index >= count($config['statuses']) - 1) {
            return back()->with('success', $record->code.' sudah berada di status akhir.');
        }

        $nextStatus = $config['statuses'][$index + 1];

        if ($module === 'sales' && $nextStatus === 'Paid' && ($record->extra['finance_verification_status'] ?? null) !== 'verified') {
            return back()->withErrors(['status' => 'Status Paid hanya diperbarui setelah pembayaran diverifikasi pada modul Keuangan.']);
        }

        $oldStatus = $record->status;
        $record->update(['status' => $nextStatus]);
        $this->syncEmployeeRecordIfNeeded($module, $record, request());
        app(ProductIntegrationService::class)->syncRecord($record->fresh(), null, request()->user());
        $record->refresh();
        $this->syncModuleRecordRelations($record, request());

        app(ErpNotificationService::class)->notifyPrivilege("modules.{$module}.view", [
            'type' => 'record.workflow',
            'severity' => 'warning',
            'module' => $module,
            'erp_record_id' => $record->id,
            'title' => 'Workflow '.$record->code.' maju',
            'body' => $record->title.' dipindahkan dari '.$oldStatus.' ke '.$record->status.'.',
            'action_url' => route('records.index', $module),
        ], request()->user());

        return back()->with('success', $record->code.' dipindahkan ke '.$record->status.'.');
    }

    private function moduleConfig(string $module): array
    {
        $config = ErpModules::get($module);
        abort_if(! $config, 404);

        return MasterDataRegistry::applyToModule($module, $config);
    }

    private function formConfig(string $module, ?ErpRecord $record, ?Request $request): array
    {
        return ErpFormOptions::applyToModule($module, $this->moduleConfig($module), $record, $request?->user());
    }

    private function syncEmployeeRecordIfNeeded(string $module, ErpRecord $record, Request $request): void
    {
        if ($module !== 'hris') {
            return;
        }

        $profile = null;

        if ($request->filled('employee_profile_id')) {
            $profile = EmployeeProfile::with(['user', 'managerProfile.user'])
                ->find($request->input('employee_profile_id'));
        }

        app(HrisEmployeeIntegrationService::class)
            ->applyEmployeeContext($record->fresh(), $request->user(), $profile);
    }

    private function syncModuleRecordRelations(ErpRecord $record, Request $request): void
    {
        app(ErpModuleIntegrationService::class)->syncRecord($record->fresh(), $request->user());
    }

    private function ensureRecordModule(string $module, ErpRecord $record): void
    {
        abort_if($record->module !== $module, 404);
    }

    private function authorizeModule(Request $request, string $module, string $action): void
    {
        abort_unless($request->user()?->hasPrivilege("modules.{$module}.{$action}"), 403, 'Anda tidak memiliki privilege untuk modul ini.');
    }

    private function modulePrivileges(Request $request, string $module): array
    {
        return collect(['view', 'create', 'edit', 'advance', 'pay', 'delete'])
            ->mapWithKeys(fn ($action) => [$action => $request->user()?->hasPrivilege("modules.{$module}.{$action}")])
            ->all();
    }

    private function validatedData(Request $request, array $config): array
    {
        $rules = [];

        foreach ($config['fields'] as $field) {
            $rule = [empty($field['required']) ? 'nullable' : 'required'];
            $rule[] = $field['type'] === 'number' ? 'numeric' : 'string';

            if ($field['type'] === 'date') {
                $rule = [empty($field['required']) ? 'nullable' : 'required', 'date'];
            }

            if ($field['type'] === 'select') {
                $options = $field['name'] === 'status' ? $config['statuses'] : ($field['options'] ?? []);
                $rule = [empty($field['required']) ? 'nullable' : 'required', Rule::in($options)];
            }

            if ($field['name'] === 'progress') {
                $rule[] = 'min:0';
                $rule[] = 'max:100';
            }

            $rules[$field['name']] = $rule;
        }

        return $request->validate($rules);
    }

    private function relationInput(Request $request, string $module): ?array
    {
        $sourceId = $request->input('related_source_record_id');
        $relationType = trim((string) $request->input('related_relation_type'));

        if (! $sourceId && $relationType === '') {
            return null;
        }

        $sources = collect(ErpFormOptions::relationSources($module, $request->user()));
        $selected = $sources->first(function (array $source) use ($sourceId, $relationType) {
            if ((int) $source['id'] !== (int) $sourceId) {
                return false;
            }

            return $relationType === '' || $source['relation_type'] === $relationType;
        });

        if (! $selected) {
            throw ValidationException::withMessages([
                'related_source_record_id' => 'Sumber terintegrasi tidak valid untuk modul ini.',
            ]);
        }

        return $selected;
    }

    private function mergeRelationPayloadIntoRequest(Request $request, ?array $relationInput, array $config): void
    {
        if (! $relationInput) {
            return;
        }

        $fieldNames = collect($config['fields'])->pluck('name')->all();
        $payload = collect($relationInput['payload'])
            ->only($fieldNames)
            ->all();

        $request->merge($payload);
    }

    private function employeeProfilesForForm(string $module)
    {
        if ($module !== 'hris') {
            return collect();
        }

        return EmployeeProfile::with(['user', 'managerProfile.user'])
            ->orderBy('employee_no')
            ->get();
    }

    private function productFormData(string $module, ?ErpRecord $record = null): array
    {
        if (! in_array($module, ['sales', 'manufacturing'], true) || ! Schema::hasTable('products')) {
            return ['managedProducts' => collect(), 'recordProductLines' => collect()];
        }

        $products = \App\Models\Product::where('is_active', true)
            ->when($module === 'sales', fn ($query) => $query->where('is_sellable', true))
            ->when($module === 'manufacturing', fn ($query) => $query->where('is_manufacturable', true))
            ->with('bomItems.component')
            ->orderBy('name')
            ->get();

        return [
            'managedProducts' => $products,
            'recordProductLines' => $record
                ? $record->productLines()->with('product')->get()
                : collect(),
        ];
    }

    private function validateHrisEmployeeSelection(Request $request, string $module, array $data, ?ErpRecord $record = null): void
    {
        if ($module !== 'hris') {
            return;
        }

        $request->validate([
            'employee_profile_id' => ['nullable', 'integer', 'exists:employee_profiles,id'],
        ]);

        if (! $this->hrisCategoryRequiresEmployee($data['category'] ?? $record?->category)) {
            return;
        }

        if (($record?->extra['source_master'] ?? null) === 'employee_profiles_summary') {
            return;
        }

        $tempRecord = new ErpRecord([
            'module' => 'hris',
            'title' => $data['title'] ?? $record?->title,
            'category' => $data['category'] ?? $record?->category,
            'party' => $data['party'] ?? $record?->party,
            'owner' => $data['owner'] ?? $record?->owner,
            'notes' => $data['notes'] ?? $record?->notes,
            'extra' => array_merge($record?->extra ?? [], [
                'employee_profile_id' => $request->input('employee_profile_id'),
            ]),
        ]);

        if (app(HrisEmployeeIntegrationService::class)->profileForRecord($tempRecord)) {
            return;
        }

        throw ValidationException::withMessages([
            'employee_profile_id' => 'Pilih pegawai dari master SDM/Employee agar record HRIS tersambung ke presensi, payroll, finance, dan accounting.',
        ]);
    }

    private function hrisCategoryRequiresEmployee(?string $category): bool
    {
        return ! in_array($category, ['Recruitment', 'Interview', 'Test', 'Onboarding'], true);
    }

    private function validateFinanceOnlySalesPaidStatus(string $module, array $data, ?ErpRecord $record = null): void
    {
        if ($module !== 'sales' || ($data['status'] ?? null) !== 'Paid' || $record?->status === 'Paid') {
            return;
        }

        if (($record?->extra['finance_verification_status'] ?? null) === 'verified') {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Status Paid hanya dapat terbentuk dari pembayaran yang telah diverifikasi di modul Keuangan.',
        ]);
    }

    private function syncPayrollAfterReimbursementIfNeeded(string $module, ErpRecord $record, Request $request): void
    {
        if ($module !== 'hris' || $record->category !== 'Reimbursement') {
            return;
        }

        if (($record->extra['payment_mode'] ?? 'payroll') !== 'payroll') {
            return;
        }

        app(EmployeePayrollService::class)->syncAllPayrolls($request->user());
    }

    private function mergeHrisPayableInfoIfNeeded(Request $request, string $module, ErpRecord $record, array $data): void
    {
        if ($module !== 'hris') {
            return;
        }

        $category = $data['category'] ?? $record->category;

        $paymentData = match ($category) {
            'Training' => $this->validatedTrainingPaymentData($request, $record, $data),
            'Business Trip' => $this->validatedBusinessTripPaymentData($request, $record, $data),
            default => null,
        };

        if (! $paymentData) {
            return;
        }

        $record->forceFill([
            'extra' => array_merge($record->extra ?? [], $paymentData),
        ])->save();
    }

    private function validatedTrainingPaymentData(Request $request, ErpRecord $record, array $data): array
    {
        $validated = $request->validate([
            'training_payment_required' => ['nullable', 'boolean'],
            'training_provider' => ['nullable', 'string', 'max:180'],
            'training_invoice_no' => ['nullable', 'string', 'max:120'],
            'training_payment_method' => ['nullable', Rule::in(['bank_transfer', 'virtual_account', 'cash', 'corporate_card', 'reimbursement', 'other'])],
            'training_bank_name' => ['nullable', 'string', 'max:120'],
            'training_bank_account_number' => ['nullable', 'string', 'max:120'],
            'training_bank_account_name' => ['nullable', 'string', 'max:160'],
            'training_payment_due_date' => ['nullable', 'date'],
            'training_payment_reference' => ['nullable', 'string', 'max:160'],
            'training_payment_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $status = $data['status'] ?? $record->status;
        $amount = (float) ($data['amount'] ?? $record->amount ?? 0);
        $paymentRequired = $request->boolean('training_payment_required');

        if ($paymentRequired && in_array($status, ['Approved', 'Processed'], true)) {
            $errors = [];

            if ($amount <= 0) {
                $errors['amount'] = 'Isi Nilai / Biaya pelatihan lebih dari 0 sebelum membuat payable Finance.';
            }

            if (empty($validated['training_provider'])) {
                $errors['training_provider'] = 'Isi lembaga/vendor pelatihan sebelum membuat payable Finance.';
            }

            if (empty($validated['training_payment_method'])) {
                $errors['training_payment_method'] = 'Pilih metode pembayaran pelatihan.';
            }

            if (empty($validated['training_payment_due_date']) && empty($data['due_date'])) {
                $errors['training_payment_due_date'] = 'Isi jatuh tempo pembayaran pelatihan.';
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        return [
            'payment_mode' => 'separate',
            'payment_required' => $paymentRequired,
            'training_payment_required' => $paymentRequired,
            'training_provider' => $validated['training_provider'] ?? null,
            'training_invoice_no' => $validated['training_invoice_no'] ?? null,
            'training_payment_method' => $validated['training_payment_method'] ?? null,
            'training_bank_name' => $validated['training_bank_name'] ?? null,
            'training_bank_account_number' => $validated['training_bank_account_number'] ?? null,
            'training_bank_account_name' => $validated['training_bank_account_name'] ?? null,
            'training_payment_due_date' => $validated['training_payment_due_date'] ?? ($data['due_date'] ?? null),
            'training_payment_reference' => $validated['training_payment_reference'] ?? null,
            'training_payment_notes' => $validated['training_payment_notes'] ?? null,
            'training_payment_amount' => $amount,
            'training_payment_status' => $status === 'Processed' ? 'paid' : ($paymentRequired ? 'ready_for_finance' : 'not_required'),
            'training_payment_updated_by' => $request->user()?->id,
            'training_payment_updated_at' => now()->toDateTimeString(),
        ];
    }

    private function validatedBusinessTripPaymentData(Request $request, ErpRecord $record, array $data): array
    {
        $validated = $request->validate([
            'business_trip_payment_required' => ['nullable', 'boolean'],
            'business_trip_payee_type' => ['nullable', Rule::in(['employee', 'vendor', 'travel_agent', 'other'])],
            'business_trip_payee_name' => ['nullable', 'string', 'max:180'],
            'business_trip_invoice_no' => ['nullable', 'string', 'max:120'],
            'business_trip_payment_method' => ['nullable', Rule::in(['bank_transfer', 'virtual_account', 'cash', 'corporate_card', 'reimbursement', 'other'])],
            'business_trip_bank_name' => ['nullable', 'string', 'max:120'],
            'business_trip_bank_account_number' => ['nullable', 'string', 'max:120'],
            'business_trip_bank_account_name' => ['nullable', 'string', 'max:160'],
            'business_trip_payment_due_date' => ['nullable', 'date'],
            'business_trip_destination' => ['nullable', 'string', 'max:180'],
            'business_trip_purpose' => ['nullable', 'string', 'max:500'],
            'business_trip_transport_mode' => ['nullable', 'string', 'max:120'],
            'business_trip_payment_reference' => ['nullable', 'string', 'max:160'],
            'business_trip_payment_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $status = $data['status'] ?? $record->status;
        $amount = (float) ($data['amount'] ?? $record->amount ?? 0);
        $paymentRequired = $request->boolean('business_trip_payment_required');
        $defaultPayee = $this->employeePaymentName($request, $record);
        $payeeName = $validated['business_trip_payee_name'] ?? $defaultPayee;

        if ($paymentRequired && in_array($status, ['Approved', 'Processed'], true)) {
            $errors = [];

            if ($amount <= 0) {
                $errors['amount'] = 'Isi Nilai / Biaya perjalanan dinas lebih dari 0 sebelum membuat payable Finance.';
            }

            if (empty($payeeName)) {
                $errors['business_trip_payee_name'] = 'Isi penerima pembayaran perjalanan dinas.';
            }

            if (empty($validated['business_trip_payment_method'])) {
                $errors['business_trip_payment_method'] = 'Pilih metode pembayaran perjalanan dinas.';
            }

            if (empty($validated['business_trip_payment_due_date']) && empty($data['due_date'])) {
                $errors['business_trip_payment_due_date'] = 'Isi jatuh tempo pembayaran perjalanan dinas.';
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        return [
            'payment_mode' => 'separate',
            'payment_required' => $paymentRequired,
            'business_trip_payment_required' => $paymentRequired,
            'business_trip_payee_type' => $validated['business_trip_payee_type'] ?? 'employee',
            'business_trip_payee_name' => $payeeName,
            'business_trip_invoice_no' => $validated['business_trip_invoice_no'] ?? null,
            'business_trip_payment_method' => $validated['business_trip_payment_method'] ?? null,
            'business_trip_bank_name' => $validated['business_trip_bank_name'] ?? null,
            'business_trip_bank_account_number' => $validated['business_trip_bank_account_number'] ?? null,
            'business_trip_bank_account_name' => $validated['business_trip_bank_account_name'] ?? $payeeName,
            'business_trip_payment_due_date' => $validated['business_trip_payment_due_date'] ?? ($data['due_date'] ?? null),
            'business_trip_destination' => $validated['business_trip_destination'] ?? null,
            'business_trip_purpose' => $validated['business_trip_purpose'] ?? null,
            'business_trip_transport_mode' => $validated['business_trip_transport_mode'] ?? null,
            'business_trip_payment_reference' => $validated['business_trip_payment_reference'] ?? null,
            'business_trip_payment_notes' => $validated['business_trip_payment_notes'] ?? null,
            'business_trip_payment_amount' => $amount,
            'business_trip_payment_status' => $status === 'Processed' ? 'paid' : ($paymentRequired ? 'ready_for_finance' : 'not_required'),
            'business_trip_payment_updated_by' => $request->user()?->id,
            'business_trip_payment_updated_at' => now()->toDateTimeString(),
        ];
    }

    private function employeePaymentName(Request $request, ErpRecord $record): ?string
    {
        $profileId = $request->input('employee_profile_id') ?: ($record->extra['employee_profile_id'] ?? null);

        if (! $profileId) {
            return $record->extra['employee_name'] ?? null;
        }

        $profile = EmployeeProfile::with('user')->find($profileId);

        return $profile?->user?->name ?: $profile?->bank_account_name ?: ($record->extra['employee_name'] ?? null);
    }

    private function isHrisPayrollInput(string $module, array $data): bool
    {
        return $module === 'hris' && ($data['category'] ?? null) === 'Payroll Event';
    }

    private function storeHrisPayrollFromInput(Request $request, array $data, array $config)
    {
        $payroll = $this->storePayrollFromHrisPayload($request, $data);
        $record = $this->payrollRecordFor($payroll);

        if ($record) {
            app(ErpNotificationService::class)->notifyPrivilege('modules.hris.view', [
                'type' => 'record.created',
                'severity' => 'info',
                'module' => 'hris',
                'erp_record_id' => $record->id,
                'title' => $config['label'].' payroll dibuat: '.$record->code,
                'body' => $record->title.' dihitung dari Employee Payroll senilai Rp '.number_format((float) $record->amount, 0, ',', '.').'.',
                'action_url' => route('records.index', ['module' => 'hris', 'q' => $record->code]),
            ], $request->user());
        }

        return redirect()
            ->route('records.index', ['module' => 'hris', 'q' => $record?->code])
            ->with('success', 'Payroll HRIS tersimpan memakai kalkulasi Employee Payroll otomatis.');
    }

    private function updateHrisPayrollFromInput(Request $request, ErpRecord $record, array $data, array $config, string $oldStatus)
    {
        $payroll = $this->storePayrollFromHrisPayload($request, $data, $record);
        app(EmployeePayrollService::class)->consolidateLegacyHrisPayrollRecords($request->user());
        $payrollRecord = $this->payrollRecordFor($payroll);

        if ($payrollRecord) {
            app(ErpNotificationService::class)->notifyPrivilege('modules.hris.view', [
                'type' => 'record.updated',
                'severity' => $oldStatus !== $payrollRecord->status ? 'warning' : 'info',
                'module' => 'hris',
                'erp_record_id' => $payrollRecord->id,
                'title' => $config['label'].' payroll diperbarui: '.$payrollRecord->code,
                'body' => $payrollRecord->title.' disinkronkan ulang dari Employee Payroll.',
                'action_url' => route('records.index', ['module' => 'hris', 'q' => $payrollRecord->code]),
            ], $request->user());
        }

        return redirect()
            ->route('records.index', ['module' => 'hris', 'q' => $payrollRecord?->code])
            ->with('success', 'Payroll HRIS diperbarui dari Employee Payroll. Nilai HRIS sekarang mengikuti kalkulasi payroll utama.');
    }

    private function storePayrollFromHrisPayload(Request $request, array $data, ?ErpRecord $record = null): EmployeePayroll
    {
        $request->validate([
            'employee_profile_id' => ['required', 'integer', 'exists:employee_profiles,id'],
        ]);

        $profile = EmployeeProfile::with(['user', 'managerProfile.user'])
            ->findOrFail($request->input('employee_profile_id'));

        if (! $profile->user_id || ! $profile->user) {
            throw ValidationException::withMessages([
                'employee_profile_id' => 'Pegawai ini belum punya akun user. Lengkapi akun user di Employee Management sebelum membuat payroll.',
            ]);
        }

        return app(EmployeePayrollService::class)->storePayroll(
            $profile,
            $this->payrollPeriodFromHrisData($data, $record),
            [],
            $this->payrollStatusFromHrisStatus($data['status'] ?? $record?->status),
            $data['notes'] ?? $record?->notes,
            $request->user()
        );
    }

    private function payrollStatusFromHrisStatus(?string $status): string
    {
        return match ($status) {
            'Processed' => 'paid',
            'Approved' => 'released',
            default => 'draft',
        };
    }

    private function payrollPeriodFromHrisData(array $data, ?ErpRecord $record = null): string
    {
        $date = $data['due_date'] ?? $record?->due_date?->toDateString();

        return $date
            ? Carbon::parse($date)->startOfMonth()->toDateString()
            : now()->startOfMonth()->toDateString();
    }

    private function payrollRecordFor(EmployeePayroll $payroll): ?ErpRecord
    {
        return ErpRecord::where('module', 'hris')
            ->where('category', 'Payroll Event')
            ->latest()
            ->get()
            ->first(fn (ErpRecord $record) => (int) ($record->extra['employee_payroll_id'] ?? 0) === (int) $payroll->id);
    }

    private function syncInputRelation(ErpRecord $record, ?array $relationInput, Request $request): void
    {
        if (! $relationInput) {
            return;
        }

        ErpRecordLink::updateOrCreate(
            [
                'source_record_id' => $relationInput['id'],
                'target_record_id' => $record->id,
                'relation_type' => $relationInput['relation_type'],
            ],
            [
                'status' => 'active',
                'metadata' => [
                    'input_form' => true,
                    'source_module' => $relationInput['module'],
                    'source_code' => $relationInput['code'],
                    'target_module' => $record->module,
                    'target_code' => $record->code,
                    'synced_at' => now()->toDateTimeString(),
                ],
                'created_by' => $request->user()?->id,
            ]
        );

        $record->forceFill([
            'extra' => array_merge($record->extra ?? [], [
                'input_source_record_id' => $relationInput['id'],
                'input_source_code' => $relationInput['code'],
                'input_source_module' => $relationInput['module'],
                'input_relation_type' => $relationInput['relation_type'],
                'input_synced_at' => now()->toDateTimeString(),
            ]),
        ])->save();
    }

    private function normalizeData(string $module, array $data): array
    {
        foreach (['amount', 'quantity', 'min_quantity', 'progress'] as $numberField) {
            if (array_key_exists($numberField, $data) && $data[$numberField] === null) {
                $data[$numberField] = 0;
            }
        }

        if ($module === 'inventory' && ! in_array($data['status'] ?? '', ['Reserved', 'Blocked'], true)) {
            $data['status'] = (int) ($data['quantity'] ?? 0) <= (int) ($data['min_quantity'] ?? 0)
                ? 'Low Stock'
                : 'Available';
        }

        if (in_array($module, ['manufacturing', 'projects'], true) && (int) ($data['progress'] ?? 0) >= 100) {
            $data['status'] = 'Completed';
        }

        return $data;
    }

    private function generateCode(string $module, array $config): string
    {
        $period = now()->format('ym');
        $sequence = ErpRecord::where('module', $module)->count() + 1;

        do {
            $code = $config['prefix'].'-'.$period.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ErpRecord::where('code', $code)->exists());

        return $code;
    }
}

<form method="post" action="{{ $action }}" class="record-form">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @php
        $relationSources = collect($relationSources ?? []);
        $employeeProfiles = collect($employeeProfiles ?? []);
        $selectedRelation = $relationSources->firstWhere('selected', true);
        $selectedRelationId = old('related_source_record_id', $selectedRelation['id'] ?? '');
        $selectedRelationType = old('related_relation_type', $selectedRelation['relation_type'] ?? '');
        $selectedEmployeeProfileId = old('employee_profile_id', $record->extra['employee_profile_id'] ?? '');
        $currentCategory = old('category', $record->category);
        $trainingExtra = $record->extra ?? [];
        $hasExplicitTrainingPaymentRequired = array_key_exists('training_payment_required', $trainingExtra);
        $hasOldTrainingPaymentRequired = session()->hasOldInput('training_payment_required') || session()->hasOldInput('training_payment_required_marker');
        $trainingPaymentRequired = $hasOldTrainingPaymentRequired
            ? old('training_payment_required') === '1'
            : ($hasExplicitTrainingPaymentRequired ? filter_var($trainingExtra['training_payment_required'], FILTER_VALIDATE_BOOL) : true);
        $trainingPaymentMethods = [
            'bank_transfer' => 'Bank transfer',
            'virtual_account' => 'Virtual account',
            'cash' => 'Cash',
            'corporate_card' => 'Corporate card',
            'reimbursement' => 'Reimburse employee',
            'other' => 'Lainnya',
        ];
        $businessTripPayeeTypes = [
            'employee' => 'Employee',
            'vendor' => 'Vendor',
            'travel_agent' => 'Travel agent',
            'other' => 'Lainnya',
        ];
        $hasExplicitBusinessTripPaymentRequired = array_key_exists('business_trip_payment_required', $trainingExtra);
        $hasOldBusinessTripPaymentRequired = session()->hasOldInput('business_trip_payment_required') || session()->hasOldInput('business_trip_payment_required_marker');
        $businessTripPaymentRequired = $hasOldBusinessTripPaymentRequired
            ? old('business_trip_payment_required') === '1'
            : ($hasExplicitBusinessTripPaymentRequired ? filter_var($trainingExtra['business_trip_payment_required'], FILTER_VALIDATE_BOOL) : true);
        $managedProducts = collect($managedProducts ?? []);
        $recordProductLines = collect($recordProductLines ?? []);
        $storedSalesLines = $recordProductLines->where('line_type', 'sales_item')->map(fn ($line) => [
            'product_id' => $line->product_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
        ])->values()->all();
        $salesItemRows = old('product_items', $storedSalesLines ?: [['product_id' => '', 'quantity' => 1, 'unit_price' => '']]);
        $outputLine = $recordProductLines->firstWhere('line_type', 'production_output');
        $selectedProductionProductId = old('product_id', $outputLine?->product_id ?? ($record->extra['product_id'] ?? ''));
    @endphp

    <div class="form-grid">
        <label>
            <span>Kode</span>
            <input type="text" value="{{ $record->code }}" readonly>
        </label>

        @if($relationSources->isNotEmpty())
            <label class="full relation-source-field">
                <span>Sumber Terintegrasi</span>
                <select name="related_source_record_id" data-relation-source>
                    <option value="">Pilih sumber dokumen terkait</option>
                    @foreach($relationSources as $source)
                        <option
                            value="{{ $source['id'] }}"
                            data-relation-type="{{ $source['relation_type'] }}"
                            data-fill="{{ json_encode($source['payload']) }}"
                            @selected((string) $selectedRelationId === (string) $source['id'] && ($selectedRelationType === '' || $selectedRelationType === $source['relation_type']))
                        >
                            {{ $source['label'] }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="related_relation_type" value="{{ $selectedRelationType }}" data-relation-type-input>
                <small>Field terkait akan mengikuti data dari sumber ini agar tidak diketik bebas.</small>
                @error('related_source_record_id')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>
        @endif

        @if(($module ?? null) === 'hris')
            <label class="full relation-source-field">
                <span>Pegawai / Master SDM <b class="integrated-badge">Employee Profile</b></span>
                <select name="employee_profile_id" data-hris-employee-source>
                    <option value="">Pilih pegawai dari master SDM</option>
                    @foreach($employeeProfiles as $profile)
                        @php
                            $employeeName = $profile->user?->name ?: $profile->bank_account_name ?: $profile->employee_no;
                            $baseSalary = (float) $profile->base_salary;
                            $fixedAllowance = (float) $profile->fixed_allowance;
                            $payrollEstimate = $baseSalary + $fixedAllowance;
                            $fillPayload = [
                                'title' => $employeeName,
                                'party' => $profile->department,
                                'owner' => $profile->managerProfile?->user?->name ?: $profile->manager_name ?: 'HR',
                            ];
                        @endphp
                        <option
                            value="{{ $profile->id }}"
                            data-fill="{{ json_encode($fillPayload) }}"
                            data-employee-name="{{ $employeeName }}"
                            data-base-salary="{{ $baseSalary }}"
                            data-fixed-allowance="{{ $fixedAllowance }}"
                            data-payroll-estimate="{{ $payrollEstimate }}"
                            @selected((string) $selectedEmployeeProfileId === (string) $profile->id)
                        >
                            {{ $profile->employee_no }} - {{ $employeeName }} - {{ $profile->department ?: 'Tanpa departemen' }} / {{ $profile->position ?: 'Employee' }}
                        </option>
                    @endforeach
                </select>
                <small>HRIS operasional harus memakai master pegawai yang sama agar presensi, cuti, lembur, payroll, reimburse, finance, dan accounting tersambung.</small>
                <small>Untuk Payroll Event, nilai final dihitung dari Employee Payroll: gaji pokok, tunjangan, presensi, lembur, reimburse, bonus, THR, pajak, komisi marketing, dan insentif produksi.</small>
                @error('employee_profile_id')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>
        @endif

        @if(($module ?? null) === 'finance')
            <div class="full relation-source-field">
                <small>AR Invoice dan AP Bill akan muncul di proses Pembayaran Keuangan. Semua transaksi Finance otomatis membuat relasi jurnal Accounting sejak status Open; status Paid/Posted akan mem-posting jurnal.</small>
                <small>Journal dan Budget dipakai untuk pencatatan/anggaran Accounting, bukan dokumen pembayaran Midtrans.</small>
            </div>
        @endif

        @if(($module ?? null) === 'sales')
            <div class="full-block relation-source-field product-order-panel">
                <div class="training-payment-header">
                    <div>
                        <strong>Item Penjualan <b class="integrated-badge">Product Master</b></strong>
                        <small>Harga dan PPN berasal dari katalog produk; nilai order dihitung dari item yang dipilih.</small>
                    </div>
                    <button class="ghost-button" type="button" data-add-sales-item><x-icon name="plus" />Item</button>
                </div>
                <div class="sales-item-table">
                    <div class="sales-item-head">
                        <span>Produk</span><span>Qty</span><span>Harga / unit</span><span>Total</span><span></span>
                    </div>
                    <div data-sales-product-rows>
                        @foreach($salesItemRows as $index => $item)
                            <div class="sales-item-row" data-sales-item-row>
                                <select name="product_items[{{ $index }}][product_id]" data-sales-product required>
                                    <option value="">Pilih produk</option>
                                    @foreach($managedProducts as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->sales_price }}" data-tax="{{ $product->tax_rate }}" data-uom="{{ $product->uom }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>
                                            {{ $product->sku }} - {{ $product->name }} (stok {{ number_format($product->availableStock(), 3, ',', '.') }} {{ $product->uom }})
                                        </option>
                                    @endforeach
                                </select>
                                <input type="number" min="0.001" step="0.001" name="product_items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-sales-quantity required>
                                <input type="number" min="0" step="0.01" name="product_items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" data-sales-price required>
                                <strong data-sales-line-total>Rp 0</strong>
                                <button class="icon-button danger-text" type="button" data-remove-sales-item aria-label="Hapus item"><x-icon name="trash-2" /></button>
                            </div>
                        @endforeach
                    </div>
                </div>
                @error('product_items')<small class="field-error">{{ $message }}</small>@enderror
                @error('product_items.*.product_id')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <template data-sales-item-template>
                <div class="sales-item-row" data-sales-item-row>
                    <select data-field="product_id" data-sales-product required>
                        <option value="">Pilih produk</option>
                        @foreach($managedProducts as $product)
                            <option value="{{ $product->id }}" data-price="{{ $product->sales_price }}" data-tax="{{ $product->tax_rate }}" data-uom="{{ $product->uom }}">
                                {{ $product->sku }} - {{ $product->name }} (stok {{ number_format($product->availableStock(), 3, ',', '.') }} {{ $product->uom }})
                            </option>
                        @endforeach
                    </select>
                    <input type="number" min="0.001" step="0.001" value="1" data-field="quantity" data-sales-quantity required>
                    <input type="number" min="0" step="0.01" value="" data-field="unit_price" data-sales-price required>
                    <strong data-sales-line-total>Rp 0</strong>
                    <button class="icon-button danger-text" type="button" data-remove-sales-item aria-label="Hapus item"><x-icon name="trash-2" /></button>
                </div>
            </template>
        @endif

        @if(($module ?? null) === 'manufacturing')
            <div class="full-block relation-source-field production-product-panel">
                <label class="full">
                    <span>Produk Output <b class="integrated-badge">Product + BOM</b></span>
                    <select name="product_id" data-production-product required>
                        <option value="">Pilih produk yang diproduksi</option>
                        @foreach($managedProducts as $product)
                            @php
                                $bomPayload = $product->bomItems->where('is_active', true)->map(fn ($bom) => [
                                    'sku' => $bom->component?->sku,
                                    'name' => $bom->component?->name,
                                    'quantity' => (float) $bom->quantity_per_unit,
                                    'waste' => (float) $bom->waste_percent,
                                    'uom' => $bom->component?->uom,
                                ])->values()->all();
                            @endphp
                            <option value="{{ $product->id }}" data-title="{{ $product->name }}" data-bom="{{ json_encode($bomPayload) }}" @selected((string) $selectedProductionProductId === (string) $product->id)>
                                {{ $product->sku }} - {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <div class="production-bom-preview" data-production-bom-preview></div>
            </div>
        @endif

        @foreach($config['fields'] as $field)
            @php
                $name = $field['name'];
                $value = old($name, $record->{$name});
            @endphp
            @if(($module ?? null) === 'manufacturing' && $name === 'title')
                <input type="hidden" name="title" value="{{ $value }}" data-production-title>
                @continue
            @endif
            <label class="{{ $field['type'] === 'textarea' ? 'full' : '' }}">
                <span>
                    {{ $field['label'] }}
                    @if(!empty($field['integrated']))
                        <b class="integrated-badge">Relasi</b>
                    @endif
                </span>

                @if($field['type'] === 'select')
                    @php
                        $options = $name === 'status' ? $config['statuses'] : ($field['options'] ?? []);
                    @endphp
                    <select name="{{ $name }}" data-integrated-field="{{ !empty($field['integrated']) ? 'true' : 'false' }}" @if($name === 'category') data-category-field @endif @required(!empty($field['required']))>
                        <option value="">{{ $field['placeholder'] ?? 'Pilih opsi' }}</option>
                        @foreach($options as $option)
                            <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif($field['type'] === 'textarea')
                    <textarea name="{{ $name }}" @required(!empty($field['required']))>{{ $value }}</textarea>
                @else
                    <input
                        type="{{ $field['type'] }}"
                        name="{{ $name }}"
                        value="{{ $field['type'] === 'date' && $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : $value }}"
                        @if(($module ?? null) === 'sales' && $name === 'amount') readonly data-sales-order-total @endif
                        @if(($module ?? null) === 'manufacturing' && $name === 'quantity') data-production-quantity @endif
                        @required(!empty($field['required']))
                    >
                @endif

                @error($name)
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>
        @endforeach

        @if(($module ?? null) === 'hris')
            <div class="full-block relation-source-field training-payment-panel" data-training-payment-panel @if($currentCategory !== 'Training') hidden @endif>
                <div class="training-payment-header">
                    <div>
                        <strong>Informasi Pembayaran Pelatihan</strong>
                        <small>HRD melengkapi data ini di HRIS. Setelah status Approved/Processed dan nominal terisi, sistem membuat AP Bill ke Finance untuk dibayar satu pintu oleh keuangan.</small>
                    </div>
                    <span class="status warning">Training payable</span>
                </div>
                <div class="form-grid compact">
                    <input type="hidden" name="training_payment_required_marker" value="1">
                    <label class="checkbox-row full">
                        <input type="checkbox" name="training_payment_required" value="1" @checked($trainingPaymentRequired)>
                        Buat payable Finance untuk biaya pelatihan ini
                    </label>
                    <label>
                        <span>Lembaga / Vendor Pelatihan</span>
                        <input name="training_provider" value="{{ old('training_provider', $trainingExtra['training_provider'] ?? '') }}" placeholder="Contoh: Vendor Training ABC">
                        @error('training_provider')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>No Invoice / Tagihan</span>
                        <input name="training_invoice_no" value="{{ old('training_invoice_no', $trainingExtra['training_invoice_no'] ?? '') }}" placeholder="INV/TRN/001">
                        @error('training_invoice_no')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Metode Pembayaran</span>
                        <select name="training_payment_method">
                            <option value="">Pilih metode</option>
                            @foreach($trainingPaymentMethods as $methodValue => $methodLabel)
                                <option value="{{ $methodValue }}" @selected(old('training_payment_method', $trainingExtra['training_payment_method'] ?? '') === $methodValue)>{{ $methodLabel }}</option>
                            @endforeach
                        </select>
                        @error('training_payment_method')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Jatuh Tempo Pembayaran</span>
                        <input type="date" name="training_payment_due_date" value="{{ old('training_payment_due_date', !empty($trainingExtra['training_payment_due_date']) ? \Illuminate\Support\Carbon::parse($trainingExtra['training_payment_due_date'])->format('Y-m-d') : '') }}">
                        @error('training_payment_due_date')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Bank / Provider VA</span>
                        <input name="training_bank_name" value="{{ old('training_bank_name', $trainingExtra['training_bank_name'] ?? '') }}" placeholder="BCA / Mandiri / Midtrans VA">
                        @error('training_bank_name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>No Rekening / VA</span>
                        <input name="training_bank_account_number" value="{{ old('training_bank_account_number', $trainingExtra['training_bank_account_number'] ?? '') }}">
                        @error('training_bank_account_number')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Atas Nama</span>
                        <input name="training_bank_account_name" value="{{ old('training_bank_account_name', $trainingExtra['training_bank_account_name'] ?? '') }}">
                        @error('training_bank_account_name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Referensi Pembayaran</span>
                        <input name="training_payment_reference" value="{{ old('training_payment_reference', $trainingExtra['training_payment_reference'] ?? '') }}" placeholder="PO / kontrak / link invoice">
                        @error('training_payment_reference')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="full">
                        <span>Catatan Pembayaran</span>
                        <textarea name="training_payment_notes" placeholder="Instruksi pembayaran, keterangan invoice, atau catatan Finance">{{ old('training_payment_notes', $trainingExtra['training_payment_notes'] ?? '') }}</textarea>
                        @error('training_payment_notes')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <small>Nominal payable memakai field <b>Nilai / Biaya</b> pada form utama. Tanggal kegiatan tetap memakai field <b>Tanggal Efektif / Kegiatan</b>.</small>
            </div>

            <div class="full-block relation-source-field training-payment-panel" data-business-trip-payment-panel @if($currentCategory !== 'Business Trip') hidden @endif>
                <div class="training-payment-header">
                    <div>
                        <strong>Informasi Biaya Perjalanan Dinas</strong>
                        <small>HRD dapat membuat perjalanan dinas langsung dari HRIS atau melengkapi pengajuan employee yang sudah approved. Setelah lengkap, sistem membuat AP Bill ke Finance.</small>
                    </div>
                    <span class="status warning">Dinas payable</span>
                </div>
                <div class="form-grid compact">
                    <input type="hidden" name="business_trip_payment_required_marker" value="1">
                    <label class="checkbox-row full">
                        <input type="checkbox" name="business_trip_payment_required" value="1" @checked($businessTripPaymentRequired)>
                        Buat payable Finance untuk perjalanan dinas ini
                    </label>
                    <label>
                        <span>Tipe Penerima</span>
                        <select name="business_trip_payee_type">
                            @foreach($businessTripPayeeTypes as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" @selected(old('business_trip_payee_type', $trainingExtra['business_trip_payee_type'] ?? 'employee') === $typeValue)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        @error('business_trip_payee_type')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Penerima Pembayaran</span>
                        <input name="business_trip_payee_name" value="{{ old('business_trip_payee_name', $trainingExtra['business_trip_payee_name'] ?? $trainingExtra['employee_name'] ?? '') }}" placeholder="Nama employee/vendor/travel agent">
                        @error('business_trip_payee_name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Tujuan Perjalanan</span>
                        <input name="business_trip_destination" value="{{ old('business_trip_destination', $trainingExtra['business_trip_destination'] ?? $trainingExtra['travel_destination'] ?? '') }}" placeholder="Kota / lokasi tujuan">
                        @error('business_trip_destination')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Agenda</span>
                        <input name="business_trip_purpose" value="{{ old('business_trip_purpose', $trainingExtra['business_trip_purpose'] ?? $trainingExtra['travel_purpose'] ?? '') }}" placeholder="Meeting / audit / kunjungan cabang">
                        @error('business_trip_purpose')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Transportasi</span>
                        <input name="business_trip_transport_mode" value="{{ old('business_trip_transport_mode', $trainingExtra['business_trip_transport_mode'] ?? $trainingExtra['transport_mode'] ?? '') }}" placeholder="Pesawat / kereta / kendaraan dinas">
                        @error('business_trip_transport_mode')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>No Invoice / Referensi</span>
                        <input name="business_trip_invoice_no" value="{{ old('business_trip_invoice_no', $trainingExtra['business_trip_invoice_no'] ?? '') }}" placeholder="INV / advance request / tiket">
                        @error('business_trip_invoice_no')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Metode Pembayaran</span>
                        <select name="business_trip_payment_method">
                            <option value="">Pilih metode</option>
                            @foreach($trainingPaymentMethods as $methodValue => $methodLabel)
                                <option value="{{ $methodValue }}" @selected(old('business_trip_payment_method', $trainingExtra['business_trip_payment_method'] ?? '') === $methodValue)>{{ $methodLabel }}</option>
                            @endforeach
                        </select>
                        @error('business_trip_payment_method')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Jatuh Tempo Pembayaran</span>
                        <input type="date" name="business_trip_payment_due_date" value="{{ old('business_trip_payment_due_date', !empty($trainingExtra['business_trip_payment_due_date']) ? \Illuminate\Support\Carbon::parse($trainingExtra['business_trip_payment_due_date'])->format('Y-m-d') : '') }}">
                        @error('business_trip_payment_due_date')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Bank / Provider VA</span>
                        <input name="business_trip_bank_name" value="{{ old('business_trip_bank_name', $trainingExtra['business_trip_bank_name'] ?? '') }}" placeholder="BCA / Mandiri / Midtrans VA">
                        @error('business_trip_bank_name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>No Rekening / VA</span>
                        <input name="business_trip_bank_account_number" value="{{ old('business_trip_bank_account_number', $trainingExtra['business_trip_bank_account_number'] ?? '') }}">
                        @error('business_trip_bank_account_number')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Atas Nama</span>
                        <input name="business_trip_bank_account_name" value="{{ old('business_trip_bank_account_name', $trainingExtra['business_trip_bank_account_name'] ?? '') }}">
                        @error('business_trip_bank_account_name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Referensi Pembayaran</span>
                        <input name="business_trip_payment_reference" value="{{ old('business_trip_payment_reference', $trainingExtra['business_trip_payment_reference'] ?? '') }}" placeholder="PO / tiket / advance">
                        @error('business_trip_payment_reference')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="full">
                        <span>Catatan Pembayaran</span>
                        <textarea name="business_trip_payment_notes" placeholder="Instruksi pembayaran, komponen transport/hotel/uang jalan, atau catatan Finance">{{ old('business_trip_payment_notes', $trainingExtra['business_trip_payment_notes'] ?? '') }}</textarea>
                        @error('business_trip_payment_notes')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <small>Nominal payable memakai field <b>Nilai / Biaya</b> pada form utama. Pembayaran final tetap dilakukan di Finance.</small>
            </div>
        @endif
    </div>

    <div class="form-actions">
        <a class="ghost-button" href="{{ route('records.index', $module) }}"><x-icon name="x" />Batal</a>
        <button class="primary-button" type="submit"><x-icon name="save" />Simpan</button>
    </div>
</form>

<script>
    (function () {
        const relationSelect = document.querySelector('[data-relation-source]');
        const relationTypeInput = document.querySelector('[data-relation-type-input]');
        const employeeSelect = document.querySelector('[data-hris-employee-source]');
        const categoryField = document.querySelector('[data-category-field]');
        const trainingPaymentPanel = document.querySelector('[data-training-payment-panel]');
        const businessTripPaymentPanel = document.querySelector('[data-business-trip-payment-panel]');
        const salesRows = document.querySelector('[data-sales-product-rows]');
        const salesTemplate = document.querySelector('[data-sales-item-template]');
        const salesTotal = document.querySelector('[data-sales-order-total]');
        const addSalesItem = document.querySelector('[data-add-sales-item]');
        const productionProduct = document.querySelector('[data-production-product]');
        const productionQuantity = document.querySelector('[data-production-quantity]');
        const productionTitle = document.querySelector('[data-production-title]');
        const productionBomPreview = document.querySelector('[data-production-bom-preview]');

        function ensureSelectOption(field, value) {
            if (! value || field.tagName !== 'SELECT') {
                return;
            }

            const exists = Array.from(field.options).some((option) => option.value === String(value));

            if (! exists) {
                field.add(new Option(String(value), String(value)));
            }
        }

        function applyFillPayload(form, option) {
            if (! option || ! option.dataset.fill) {
                return;
            }

            let payload = {};

            try {
                payload = JSON.parse(option.dataset.fill);
            } catch (error) {
                payload = {};
            }

            Object.entries(payload).forEach(([name, value]) => {
                const field = form.elements[name];

                if (! field || value === null || value === undefined) {
                    return;
                }

                ensureSelectOption(field, value);
                field.value = value;
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        function applySourcePayload() {
            if (! relationSelect || ! relationTypeInput) {
                return;
            }

            const option = relationSelect.selectedOptions[0];
            relationTypeInput.value = option ? (option.dataset.relationType || '') : '';
            applyFillPayload(relationSelect.form, option);
        }

        function applyEmployeePayload() {
            if (! employeeSelect) {
                return;
            }

            applyFillPayload(employeeSelect.form, employeeSelect.selectedOptions[0]);
            applyPayrollDefaults();
        }

        function syncEmployeeRequirement() {
            if (! employeeSelect || ! categoryField) {
                return;
            }

            const optionalCategories = ['Recruitment', 'Interview', 'Test', 'Onboarding'];
            employeeSelect.required = categoryField.value !== '' && ! optionalCategories.includes(categoryField.value);
        }

        function syncTrainingPaymentPanel() {
            if (! trainingPaymentPanel || ! categoryField) {
                return;
            }

            trainingPaymentPanel.hidden = categoryField.value !== 'Training';
        }

        function syncBusinessTripPaymentPanel() {
            if (! businessTripPaymentPanel || ! categoryField) {
                return;
            }

            businessTripPaymentPanel.hidden = categoryField.value !== 'Business Trip';
        }

        function applyPayrollDefaults() {
            if (! employeeSelect || ! categoryField || categoryField.value !== 'Payroll Event') {
                return;
            }

            const option = employeeSelect.selectedOptions[0];

            if (! option || ! option.value) {
                return;
            }

            const form = employeeSelect.form;
            const employeeName = option.dataset.employeeName || '';
            const estimate = Number(option.dataset.payrollEstimate || 0);
            const titleField = form.elements.title;
            const amountField = form.elements.amount;
            const dueDateField = form.elements.due_date;

            if (titleField && employeeName && (! titleField.value || ! titleField.value.toLowerCase().includes('payroll'))) {
                titleField.value = `${employeeName} - Payroll`;
            }

            if (amountField && (! amountField.value || Number(amountField.value) === 0)) {
                amountField.value = String(Math.round(estimate));
            }

            if (dueDateField && ! dueDateField.value) {
                const now = new Date();
                const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                dueDateField.value = endOfMonth.toISOString().slice(0, 10);
            }
        }

        function formatCurrency(value) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
        }

        function updateSalesItems() {
            if (! salesRows) {
                return;
            }

            let total = 0;
            salesRows.querySelectorAll('[data-sales-item-row]').forEach((row) => {
                const product = row.querySelector('[data-sales-product]');
                const price = row.querySelector('[data-sales-price]');
                const quantity = row.querySelector('[data-sales-quantity]');
                const selected = product?.selectedOptions[0];

                if (product?.dataset.pricePending === 'true' && selected?.value) {
                    price.value = selected.dataset.price || 0;
                    delete product.dataset.pricePending;
                }

                const lineTotal = Number(quantity?.value || 0) * Number(price?.value || 0);
                total += lineTotal;
                row.querySelector('[data-sales-line-total]').textContent = formatCurrency(lineTotal);
            });

            if (salesTotal) {
                salesTotal.value = total.toFixed(2);
            }
        }

        function addProductRow() {
            if (! salesRows || ! salesTemplate) {
                return;
            }

            const index = salesRows.querySelectorAll('[data-sales-item-row]').length;
            const fragment = salesTemplate.content.cloneNode(true);
            fragment.querySelector('[data-field="product_id"]').name = `product_items[${index}][product_id]`;
            fragment.querySelector('[data-field="quantity"]').name = `product_items[${index}][quantity]`;
            fragment.querySelector('[data-field="unit_price"]').name = `product_items[${index}][unit_price]`;
            salesRows.appendChild(fragment);
            window.renderErpIcons?.();
            updateSalesItems();
        }

        function updateProductionBom() {
            if (! productionProduct || ! productionBomPreview) {
                return;
            }

            const selected = productionProduct.selectedOptions[0];
            const outputQuantity = Number(productionQuantity?.value || 0);
            let bom = [];

            if (productionTitle) {
                productionTitle.value = selected?.dataset.title || '';
            }

            try {
                bom = JSON.parse(selected?.dataset.bom || '[]');
            } catch (error) {
                bom = [];
            }

            if (! selected?.value) {
                productionBomPreview.innerHTML = '';
                return;
            }

            if (bom.length === 0) {
                productionBomPreview.innerHTML = '<span class="muted">Produk belum memiliki komponen BOM.</span>';
                return;
            }

            productionBomPreview.innerHTML = '<strong>Kebutuhan Bahan</strong>' + bom.map((component) => {
                const quantity = outputQuantity * component.quantity * (1 + (component.waste / 100));
                const required = outputQuantity > 0
                    ? `${quantity.toLocaleString('id-ID', { maximumFractionDigits: 4 })} ${component.uom}`
                    : `${component.quantity.toLocaleString('id-ID', { maximumFractionDigits: 4 })} ${component.uom} / unit`;
                return `<span>${component.sku} - ${component.name}: <b>${required}</b></span>`;
            }).join('');
        }

        if (relationSelect) {
            relationSelect.addEventListener('change', applySourcePayload);
        }

        if (employeeSelect) {
            employeeSelect.addEventListener('change', applyEmployeePayload);
            syncEmployeeRequirement();
            applyPayrollDefaults();
        }

        if (categoryField) {
            categoryField.addEventListener('change', () => {
                syncEmployeeRequirement();
                applyPayrollDefaults();
                syncTrainingPaymentPanel();
                syncBusinessTripPaymentPanel();
            });
            syncTrainingPaymentPanel();
            syncBusinessTripPaymentPanel();
        }

        if (salesRows) {
            salesRows.addEventListener('change', (event) => {
                if (event.target.matches('[data-sales-product]')) {
                    event.target.dataset.pricePending = 'true';
                }
                updateSalesItems();
            });
            salesRows.addEventListener('input', updateSalesItems);
            salesRows.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-sales-item]');
                if (! button || salesRows.querySelectorAll('[data-sales-item-row]').length === 1) {
                    return;
                }
                button.closest('[data-sales-item-row]').remove();
                updateSalesItems();
            });
            addSalesItem?.addEventListener('click', addProductRow);
            updateSalesItems();
        }

        if (productionProduct) {
            productionProduct.addEventListener('change', updateProductionBom);
            productionQuantity?.addEventListener('input', updateProductionBom);
            updateProductionBom();
        }
    })();
</script>

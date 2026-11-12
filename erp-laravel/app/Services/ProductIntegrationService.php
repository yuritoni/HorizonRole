<?php

namespace App\Services;

use App\Models\ErpRecord;
use App\Models\ErpRecordProductLine;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductIntegrationService
{
    public const TYPES = [
        'finished_good' => 'Produk Jadi',
        'semi_finished' => 'Barang Setengah Jadi',
        'raw_material' => 'Bahan Baku',
        'packaging' => 'Kemasan',
        'service' => 'Jasa',
    ];

    public const UOMS = ['pcs', 'box', 'set', 'kg', 'gram', 'liter', 'meter', 'unit', 'jam'];

    public function prepareRecordInput(Request $request, string $module): ?array
    {
        if (! $this->available() || ! in_array($module, ['sales', 'manufacturing'], true)) {
            return null;
        }

        return $module === 'sales'
            ? $this->prepareSalesInput($request)
            : $this->prepareManufacturingInput($request);
    }

    public function syncRecord(ErpRecord $record, ?array $context = null, ?User $actor = null): void
    {
        if (! $this->available() || ! in_array($record->module, ['sales', 'manufacturing'], true)) {
            return;
        }

        DB::transaction(function () use ($record, $context, $actor) {
            if ($context && $record->module === 'sales') {
                $this->syncSalesLines($record, $context['items']);
            }

            if ($context && $record->module === 'manufacturing') {
                $this->syncManufacturingLines($record, $context['product']);
            }

            $record->refresh();
            $affectedProducts = $record->productLines()->pluck('product_id')
                ->merge(ProductStockMovement::where('erp_record_id', $record->id)->pluck('product_id'))
                ->unique()
                ->values();

            if ($record->module === 'sales') {
                $this->syncSalesMovements($record, $actor);
            } else {
                $this->syncManufacturingMovements($record, $actor);
            }

            $affectedProducts = $affectedProducts
                ->merge(ProductStockMovement::where('erp_record_id', $record->id)->pluck('product_id'))
                ->unique();

            $this->refreshStocks($affectedProducts);
        });
    }

    public function openingBalance(Product $product, float $quantity, ?User $actor = null): void
    {
        if ($quantity <= 0) {
            $this->refreshStocks(collect([$product->id]));

            return;
        }

        ProductStockMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'opening_balance',
            'direction' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $product->standard_cost,
            'status' => 'posted',
            'reference' => 'OPENING-'.$product->sku,
            'occurred_at' => now(),
            'notes' => 'Saldo awal produk dari Product Management.',
            'created_by' => $actor?->id,
        ]);

        $this->refreshStocks(collect([$product->id]));
    }

    public function adjustment(Product $product, string $direction, float $quantity, string $notes, ?User $actor = null): void
    {
        ProductStockMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'manual_adjustment',
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $product->standard_cost,
            'status' => 'posted',
            'reference' => 'ADJ-'.$product->sku.'-'.now()->format('YmdHis'),
            'occurred_at' => now(),
            'notes' => $notes,
            'created_by' => $actor?->id,
        ]);

        $this->refreshStocks(collect([$product->id]));
    }

    public function cancelRecordMovements(ErpRecord $record): void
    {
        if (! $this->available()) {
            return;
        }

        $productIds = ProductStockMovement::where('erp_record_id', $record->id)->pluck('product_id');
        ProductStockMovement::where('erp_record_id', $record->id)->update(['status' => 'cancelled']);
        $this->refreshStocks($productIds);
    }

    public function refreshStocks(Collection $productIds): void
    {
        Product::whereIn('id', $productIds->filter()->unique())->get()->each(function (Product $product) {
            $posted = ProductStockMovement::where('product_id', $product->id)
                ->where('status', 'posted')
                ->get();
            $stock = $posted->sum(fn (ProductStockMovement $movement) => $movement->direction === 'in'
                ? (float) $movement->quantity
                : -(float) $movement->quantity);
            $reserved = ProductStockMovement::where('product_id', $product->id)
                ->where('direction', 'reserve')
                ->where('status', 'active')
                ->sum('quantity');

            $product->forceFill([
                'stock_on_hand' => $stock,
                'reserved_stock' => $reserved,
            ])->save();
        });
    }

    private function prepareSalesInput(Request $request): array
    {
        $data = $request->validate([
            'product_items' => ['required', 'array', 'min:1'],
            'product_items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'product_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'product_items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $products = Product::whereIn('id', collect($data['product_items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $items = collect($data['product_items'])->map(function (array $item) use ($products) {
            $product = $products->get((int) $item['product_id']);

            if (! $product || ! $product->is_active || ! $product->is_sellable) {
                throw ValidationException::withMessages([
                    'product_items' => 'Pilih produk aktif yang tersedia untuk penjualan.',
                ]);
            }

            $quantity = (float) $item['quantity'];
            $price = $item['unit_price'] !== null && $item['unit_price'] !== ''
                ? (float) $item['unit_price']
                : (float) $product->sales_price;

            return [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $price,
                'tax_rate' => (float) $product->tax_rate,
                'line_amount' => round($quantity * $price, 2),
            ];
        })->values();

        $request->merge(['amount' => $items->sum('line_amount')]);

        return ['items' => $items];
    }

    private function prepareManufacturingInput(Request $request): array
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $product = Product::findOrFail($data['product_id']);

        if (! $product->is_active || ! $product->is_manufacturable) {
            throw ValidationException::withMessages([
                'product_id' => 'Pilih produk aktif yang dapat diproduksi.',
            ]);
        }

        $request->merge(['title' => $product->name]);

        return ['product' => $product];
    }

    private function syncSalesLines(ErpRecord $record, Collection $items): void
    {
        $record->productLines()->delete();

        foreach ($items as $index => $item) {
            ErpRecordProductLine::create([
                'erp_record_id' => $record->id,
                'product_id' => $item['product']->id,
                'line_type' => 'sales_item',
                'line_no' => $index + 1,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'],
                'line_amount' => $item['line_amount'],
                'metadata' => [
                    'sku' => $item['product']->sku,
                    'name' => $item['product']->name,
                    'uom' => $item['product']->uom,
                ],
            ]);
        }

        $net = round((float) $items->sum('line_amount'), 2);
        $tax = round((float) $items->sum(fn ($item) => $item['line_amount'] * ($item['tax_rate'] / 100)), 2);

        $record->forceFill([
            'amount' => $net,
            'extra' => array_merge($record->extra ?? [], [
                'product_lines' => $items->map(fn ($item) => [
                    'product_id' => $item['product']->id,
                    'sku' => $item['product']->sku,
                    'name' => $item['product']->name,
                    'uom' => $item['product']->uom,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'line_amount' => $item['line_amount'],
                ])->all(),
                'tax_amount' => $tax,
                'gross_amount' => $net + $tax,
                'product_source' => 'product_management',
            ]),
        ])->save();
    }

    private function syncManufacturingLines(ErpRecord $record, Product $product): void
    {
        $record->productLines()->delete();
        $quantity = (float) $record->quantity;
        $amount = round($quantity * (float) $product->standard_cost, 2);

        ErpRecordProductLine::create([
            'erp_record_id' => $record->id,
            'product_id' => $product->id,
            'line_type' => 'production_output',
            'line_no' => 1,
            'quantity' => $quantity,
            'unit_price' => $product->standard_cost,
            'tax_rate' => 0,
            'line_amount' => $amount,
            'metadata' => ['sku' => $product->sku, 'name' => $product->name, 'uom' => $product->uom],
        ]);

        $materials = $product->bomItems()->with('component')->where('is_active', true)->get()
            ->map(function ($bom, $index) use ($record, $quantity) {
                $requiredQty = round($quantity * (float) $bom->quantity_per_unit * (1 + ((float) $bom->waste_percent / 100)), 3);
                $amount = round($requiredQty * (float) $bom->component->standard_cost, 2);

                ErpRecordProductLine::create([
                    'erp_record_id' => $record->id,
                    'product_id' => $bom->component_product_id,
                    'line_type' => 'material_requirement',
                    'line_no' => $index + 1,
                    'quantity' => $requiredQty,
                    'unit_price' => $bom->component->standard_cost,
                    'tax_rate' => 0,
                    'line_amount' => $amount,
                    'metadata' => [
                        'sku' => $bom->component->sku,
                        'name' => $bom->component->name,
                        'uom' => $bom->component->uom,
                        'waste_percent' => (float) $bom->waste_percent,
                    ],
                ]);

                return [
                    'product_id' => $bom->component->id,
                    'sku' => $bom->component->sku,
                    'name' => $bom->component->name,
                    'uom' => $bom->component->uom,
                    'quantity' => $requiredQty,
                    'standard_cost' => (float) $bom->component->standard_cost,
                    'amount' => $amount,
                ];
            });

        $record->forceFill([
            'title' => $product->name,
            'category' => $product->category,
            'amount' => $amount,
            'extra' => array_merge($record->extra ?? [], [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_uom' => $product->uom,
                'product_source' => 'product_management',
                'bom_materials' => $materials->all(),
                'material_cost' => round((float) $materials->sum('amount'), 2),
            ]),
        ])->save();
    }

    private function syncSalesMovements(ErpRecord $record, ?User $actor): void
    {
        $lines = $record->productLines()->where('line_type', 'sales_item')->get()->groupBy('product_id');
        $currentProductIds = $lines->keys()->map(fn ($id) => (int) $id);

        ProductStockMovement::where('erp_record_id', $record->id)
            ->whereIn('movement_type', ['sales_reservation', 'sales_issue'])
            ->whereNotIn('product_id', $currentProductIds)
            ->update(['status' => 'cancelled']);

        foreach ($lines as $productId => $productLines) {
            $quantity = (float) $productLines->sum('quantity');
            $cost = (float) (Product::find($productId)?->standard_cost ?? 0);
            $reservationStatus = in_array($record->status, ['Confirmed', 'Invoiced'], true) ? 'active' : ($record->status === 'Paid' ? 'released' : 'cancelled');
            $issueStatus = $record->status === 'Paid' ? 'posted' : 'cancelled';

            $this->upsertMovement($record, (int) $productId, 'sales_reservation', 'reserve', $quantity, $cost, $reservationStatus, $actor);
            $this->upsertMovement($record, (int) $productId, 'sales_issue', 'out', $quantity, $cost, $issueStatus, $actor);
        }
    }

    private function syncManufacturingMovements(ErpRecord $record, ?User $actor): void
    {
        $output = $record->productLines()->where('line_type', 'production_output')->first();
        $materials = $record->productLines()->where('line_type', 'material_requirement')->get();
        $consumptionPosted = in_array($record->status, ['Released', 'Running', 'Inspection', 'Completed'], true);

        if ($output) {
            $this->upsertMovement(
                $record,
                $output->product_id,
                'production_receipt',
                'in',
                (float) $output->quantity,
                (float) $output->unit_price,
                $record->status === 'Completed' ? 'posted' : 'cancelled',
                $actor
            );
        }

        foreach ($materials as $material) {
            $this->upsertMovement(
                $record,
                $material->product_id,
                'production_consumption',
                'out',
                (float) $material->quantity,
                (float) $material->unit_price,
                $consumptionPosted ? 'posted' : 'cancelled',
                $actor
            );
        }
    }

    private function upsertMovement(ErpRecord $record, int $productId, string $type, string $direction, float $quantity, float $cost, string $status, ?User $actor): void
    {
        ProductStockMovement::updateOrCreate(
            [
                'erp_record_id' => $record->id,
                'product_id' => $productId,
                'movement_type' => $type,
            ],
            [
                'direction' => $direction,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'status' => $status,
                'reference' => $record->code,
                'occurred_at' => now(),
                'notes' => 'Otomatis dari '.$record->module.' '.$record->code.'.',
                'created_by' => $actor?->id ?: $record->created_by,
            ]
        );
    }

    private function available(): bool
    {
        return Schema::hasTable('products')
            && Schema::hasTable('erp_record_product_lines')
            && Schema::hasTable('product_stock_movements');
    }
}

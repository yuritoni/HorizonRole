<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBomItem;
use App\Services\ErpNotificationService;
use App\Services\ProductIntegrationService;
use App\Support\ErpViewData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeView($request);
        $query = Product::with(['bomItems.component', 'stockMovements.record'])->orderBy('name');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(fn ($inner) => $inner->where('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%"));
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'low_stock') {
                $query->whereRaw('(stock_on_hand - reserved_stock) <= min_stock');
            } else {
                $query->where('is_active', $request->status === 'active');
            }
        }

        $allProducts = Product::all();

        return view('products.index', array_merge(ErpViewData::layout(), [
            'products' => $query->paginate(10)->withQueryString(),
            'allProducts' => $allProducts,
            'components' => Product::where('is_active', true)->orderBy('name')->get(),
            'types' => ProductIntegrationService::TYPES,
            'uoms' => ProductIntegrationService::UOMS,
            'search' => $request->get('q', ''),
            'activeType' => $request->get('type', 'all'),
            'activeStatus' => $request->get('status', 'all'),
            'canManage' => $this->canManage($request),
        ]));
    }

    public function store(Request $request, ProductIntegrationService $service)
    {
        $this->authorizeManage($request);
        $data = $this->productData($request);
        $initialStock = (float) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock']);

        $product = Product::create($data + ['created_by' => $request->user()->id]);
        $service->openingBalance($product, $initialStock, $request->user());
        $this->notify($request, 'Produk baru: '.$product->sku, $product->name.' ditambahkan ke katalog produk.');

        return redirect()->route('products.index', ['q' => $product->sku])
            ->with('success', 'Produk '.$product->sku.' berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product, ProductIntegrationService $service)
    {
        $this->authorizeManage($request);
        $data = $this->productData($request, $product);
        unset($data['initial_stock']);
        $product->update($data);
        $service->refreshStocks(collect([$product->id]));
        $this->notify($request, 'Produk diperbarui: '.$product->sku, $product->name.' diperbarui di katalog.');

        return back()->with('success', 'Produk '.$product->sku.' berhasil diperbarui.');
    }

    public function storeBom(Request $request, Product $product)
    {
        $this->authorizeManage($request);

        if (! $product->is_manufacturable) {
            throw ValidationException::withMessages(['component_product_id' => 'BOM hanya dapat ditambahkan pada produk yang dapat diproduksi.']);
        }

        $data = $request->validate([
            'component_product_id' => ['required', 'integer', 'exists:products,id', Rule::notIn([$product->id])],
            'quantity_per_unit' => ['required', 'numeric', 'gt:0'],
            'waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        ProductBomItem::updateOrCreate(
            ['product_id' => $product->id, 'component_product_id' => $data['component_product_id']],
            [
                'quantity_per_unit' => $data['quantity_per_unit'],
                'waste_percent' => $data['waste_percent'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Komponen BOM '.$product->sku.' berhasil disimpan.');
    }

    public function destroyBom(Request $request, Product $product, ProductBomItem $bomItem)
    {
        $this->authorizeManage($request);
        abort_if((int) $bomItem->product_id !== (int) $product->id, 404);
        $bomItem->delete();

        return back()->with('success', 'Komponen BOM berhasil dihapus.');
    }

    public function storeAdjustment(Request $request, Product $product, ProductIntegrationService $service)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $service->adjustment($product, $data['direction'], (float) $data['quantity'], $data['notes'], $request->user());

        return back()->with('success', 'Penyesuaian stok '.$product->sku.' berhasil dicatat.');
    }

    private function productData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(array_keys(ProductIntegrationService::TYPES))],
            'category' => ['nullable', 'string', 'max:120'],
            'uom' => ['required', Rule::in(ProductIntegrationService::UOMS)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'sales_price' => ['required', 'numeric', 'min:0'],
            'standard_cost' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'warehouse' => ['nullable', 'string', 'max:120'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]) + [
            'is_sellable' => $request->boolean('is_sellable'),
            'is_manufacturable' => $request->boolean('is_manufacturable'),
            'is_active' => $product ? $request->boolean('is_active') : true,
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()?->hasPrivilege('modules.sales.view')
                || $request->user()?->hasPrivilege('modules.manufacturing.view')
                || $request->user()?->hasPrivilege('master-data.manage'),
            403,
            'Anda tidak memiliki akses Product Management.'
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403, 'Anda tidak memiliki akses pengelolaan produk.');
    }

    private function canManage(Request $request): bool
    {
        return (bool) ($request->user()?->hasPrivilege('master-data.manage')
            || $request->user()?->hasPrivilege('modules.sales.edit')
            || $request->user()?->hasPrivilege('modules.manufacturing.edit'));
    }

    private function notify(Request $request, string $title, string $body): void
    {
        app(ErpNotificationService::class)->notifyPrivilege('modules.sales.view', [
            'type' => 'product.updated',
            'severity' => 'info',
            'module' => 'sales',
            'title' => $title,
            'body' => $body,
            'action_url' => route('products.index'),
        ], $request->user());
    }
}

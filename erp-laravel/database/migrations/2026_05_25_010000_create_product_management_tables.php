<?php

use App\Support\MasterDataRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->string('category')->nullable()->index();
            $table->string('uom', 30)->default('pcs');
            $table->string('barcode')->nullable()->unique();
            $table->decimal('sales_price', 18, 2)->default(0);
            $table->decimal('standard_cost', 18, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(11);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->decimal('stock_on_hand', 14, 3)->default(0);
            $table->decimal('reserved_stock', 14, 3)->default(0);
            $table->string('warehouse')->nullable();
            $table->boolean('is_sellable')->default(false)->index();
            $table->boolean('is_manufacturable')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_per_unit', 14, 4);
            $table->decimal('waste_percent', 6, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'component_product_id']);
        });

        Schema::create('erp_record_product_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('erp_record_id')->constrained('erp_records')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('line_type')->index();
            $table->unsignedInteger('line_no')->default(1);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('line_amount', 18, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['erp_record_id', 'line_type', 'line_no'], 'record_product_line_unique');
            $table->index(['product_id', 'line_type']);
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('erp_record_id')->nullable()->constrained('erp_records')->nullOnDelete();
            $table->string('movement_type')->index();
            $table->string('direction')->index();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('status')->default('posted')->index();
            $table->string('reference')->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->json('extra')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'erp_record_id', 'movement_type'], 'product_record_movement_index');
        });

        $now = now();
        DB::table('products')->insert([
            [
                'sku' => 'FG-A-001',
                'name' => 'Finished Product A',
                'type' => 'finished_good',
                'category' => 'Produk Jadi',
                'uom' => 'pcs',
                'sales_price' => 250000,
                'standard_cost' => 145000,
                'tax_rate' => 11,
                'min_stock' => 10,
                'stock_on_hand' => 0,
                'reserved_stock' => 0,
                'warehouse' => 'Jakarta',
                'is_sellable' => true,
                'is_manufacturable' => true,
                'is_active' => true,
                'notes' => 'Produk awal terpusat untuk Sales dan Produksi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 'FG-B-001',
                'name' => 'Finished Product B',
                'type' => 'finished_good',
                'category' => 'Produk Jadi',
                'uom' => 'pcs',
                'sales_price' => 175000,
                'standard_cost' => 98000,
                'tax_rate' => 11,
                'min_stock' => 10,
                'stock_on_hand' => 0,
                'reserved_stock' => 0,
                'warehouse' => 'Jakarta',
                'is_sellable' => true,
                'is_manufacturable' => true,
                'is_active' => true,
                'notes' => 'Produk awal terpusat untuk Sales dan Produksi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 'RM-A-001',
                'name' => 'Raw Material A',
                'type' => 'raw_material',
                'category' => 'Bahan Baku',
                'uom' => 'kg',
                'sales_price' => 0,
                'standard_cost' => 45000,
                'tax_rate' => 11,
                'min_stock' => 100,
                'stock_on_hand' => 1000,
                'reserved_stock' => 0,
                'warehouse' => 'Jakarta',
                'is_sellable' => false,
                'is_manufacturable' => false,
                'is_active' => true,
                'notes' => 'Stok pembuka bahan baku.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 'RM-B-001',
                'name' => 'Raw Material B',
                'type' => 'raw_material',
                'category' => 'Bahan Baku',
                'uom' => 'kg',
                'sales_price' => 0,
                'standard_cost' => 28000,
                'tax_rate' => 11,
                'min_stock' => 100,
                'stock_on_hand' => 1000,
                'reserved_stock' => 0,
                'warehouse' => 'Jakarta',
                'is_sellable' => false,
                'is_manufacturable' => false,
                'is_active' => true,
                'notes' => 'Stok pembuka bahan baku.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'sku' => 'PKG-001',
                'name' => 'Packaging Standard',
                'type' => 'packaging',
                'category' => 'Kemasan',
                'uom' => 'pcs',
                'sales_price' => 0,
                'standard_cost' => 3500,
                'tax_rate' => 11,
                'min_stock' => 100,
                'stock_on_hand' => 1000,
                'reserved_stock' => 0,
                'warehouse' => 'Jakarta',
                'is_sellable' => false,
                'is_manufacturable' => false,
                'is_active' => true,
                'notes' => 'Stok pembuka kemasan.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $productIds = DB::table('products')->pluck('id', 'sku');
        DB::table('product_bom_items')->insert([
            [
                'product_id' => $productIds['FG-A-001'],
                'component_product_id' => $productIds['RM-A-001'],
                'quantity_per_unit' => 1.5,
                'waste_percent' => 2,
                'is_active' => true,
                'notes' => 'Bahan utama Product A.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product_id' => $productIds['FG-A-001'],
                'component_product_id' => $productIds['RM-B-001'],
                'quantity_per_unit' => 0.5,
                'waste_percent' => 2,
                'is_active' => true,
                'notes' => 'Bahan pelengkap Product A.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product_id' => $productIds['FG-A-001'],
                'component_product_id' => $productIds['PKG-001'],
                'quantity_per_unit' => 1,
                'waste_percent' => 0,
                'is_active' => true,
                'notes' => 'Kemasan Product A.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product_id' => $productIds['FG-B-001'],
                'component_product_id' => $productIds['RM-B-001'],
                'quantity_per_unit' => 1.1,
                'waste_percent' => 1,
                'is_active' => true,
                'notes' => 'Bahan utama Product B.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product_id' => $productIds['FG-B-001'],
                'component_product_id' => $productIds['PKG-001'],
                'quantity_per_unit' => 1,
                'waste_percent' => 0,
                'is_active' => true,
                'notes' => 'Kemasan Product B.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $openingProducts = DB::table('products')
            ->where('stock_on_hand', '>', 0)
            ->get(['id', 'sku', 'stock_on_hand', 'standard_cost']);

        foreach ($openingProducts as $product) {
            DB::table('product_stock_movements')->insert([
                'product_id' => $product->id,
                'movement_type' => 'opening_balance',
                'direction' => 'in',
                'quantity' => $product->stock_on_hand,
                'unit_cost' => $product->standard_cost,
                'status' => 'posted',
                'reference' => 'OPENING-'.$product->sku,
                'occurred_at' => $now,
                'notes' => 'Saldo awal katalog produk.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        MasterDataRegistry::syncDefaults();
    }

    public function down()
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('erp_record_product_lines');
        Schema::dropIfExists('product_bom_items');
        Schema::dropIfExists('products');
    }
};

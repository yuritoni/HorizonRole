<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('phone', 32)->nullable()->unique();
                $table->string('google_subject')->nullable()->unique();
                $table->string('registration_channel', 30)->default('email');
                $table->date('birth_date')->nullable();
                $table->string('gender', 20)->nullable();
                $table->boolean('marketing_opt_in')->default(false);
                $table->json('preferences')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('label')->default('Rumah');
                $table->string('recipient_name');
                $table->string('phone', 32);
                $table->text('address_line');
                $table->string('district')->nullable();
                $table->string('city');
                $table->string('province');
                $table->string('postal_code', 12)->nullable();
                $table->string('notes')->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_cart_items')) {
            Schema::create('customer_cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('quantity', 14, 3)->default(1);
                $table->timestamps();
                $table->unique(['user_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('customer_orders')) {
            Schema::create('customer_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('erp_record_id')->unique()->constrained('erp_records')->cascadeOnDelete();
                $table->foreignId('finance_record_id')->nullable()->constrained('erp_records')->nullOnDelete();
                $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
                $table->string('order_number')->unique();
                $table->decimal('subtotal', 18, 2);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('shipping_amount', 18, 2)->default(0);
                $table->decimal('total_amount', 18, 2);
                $table->string('payment_status')->default('waiting_payment')->index();
                $table->string('fulfillment_status')->default('processing')->index();
                $table->json('shipping_snapshot');
                $table->text('customer_note')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('customer_verification_codes')) {
            Schema::create('customer_verification_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('channel', 20);
                $table->string('identifier');
                $table->string('code_hash');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'expires_at']);
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'brand')) {
                    $table->string('brand')->nullable()->after('category');
                }

                if (! Schema::hasColumn('products', 'ecommerce_description')) {
                    $table->text('ecommerce_description')->nullable()->after('notes');
                }

                if (! Schema::hasColumn('products', 'ecommerce_image')) {
                    $table->string('ecommerce_image')->nullable()->after('ecommerce_description');
                }

                if (! Schema::hasColumn('products', 'ecommerce_featured')) {
                    $table->boolean('ecommerce_featured')->default(false)->after('ecommerce_image')->index();
                }
            });

            if (Schema::hasColumn('products', 'brand') && Schema::hasColumn('products', 'ecommerce_description')) {
                DB::table('products')->where('sku', 'FG-A-001')->update([
                    'brand' => 'Horizon Home',
                    'ecommerce_description' => 'Perangkat serbaguna premium untuk kebutuhan harian dengan kualitas produksi terpercaya.',
                    'ecommerce_image' => 'images/ecommerce/finished-product-a.png',
                    'ecommerce_featured' => true,
                ]);
                DB::table('products')->where('sku', 'FG-B-001')->update([
                    'brand' => 'Horizon Living',
                    'ecommerce_description' => 'Pilihan ringkas dan elegan untuk aktivitas modern, siap dikirim dari gudang utama.',
                    'ecommerce_image' => 'images/ecommerce/finished-product-b.png',
                    'ecommerce_featured' => true,
                ]);
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'ecommerce_featured')) {
                    $table->dropIndex(['ecommerce_featured']);
                }

                $dropColumns = collect(['brand', 'ecommerce_description', 'ecommerce_image', 'ecommerce_featured'])
                    ->filter(fn ($column) => Schema::hasColumn('products', $column))
                    ->all();

                if ($dropColumns) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        Schema::dropIfExists('customer_verification_codes');
        Schema::dropIfExists('customer_orders');
        Schema::dropIfExists('customer_cart_items');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_profiles');
    }
};

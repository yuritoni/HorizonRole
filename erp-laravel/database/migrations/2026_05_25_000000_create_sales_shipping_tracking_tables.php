<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\MasterDataRegistry;

return new class extends Migration
{
    public function up()
    {
        Schema::create('courier_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('integration_mode')->default('manual')->index();
            $table->json('services')->nullable();
            $table->text('tracking_url_template')->nullable();
            $table->string('api_base_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('supports_cod')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_no')->unique();
            $table->foreignId('sales_record_id')->constrained('erp_records')->cascadeOnDelete();
            $table->foreignId('courier_provider_id')->nullable()->constrained('courier_providers')->nullOnDelete();
            $table->foreignId('customer_charge_finance_record_id')->nullable()->constrained('erp_records')->nullOnDelete();
            $table->foreignId('courier_cost_finance_record_id')->nullable()->constrained('erp_records')->nullOnDelete();
            $table->string('service_code')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address');
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->decimal('weight_kg', 10, 2)->default(0);
            $table->unsignedInteger('package_count')->default(1);
            $table->decimal('customer_shipping_charge', 18, 2)->default(0);
            $table->decimal('courier_cost', 18, 2)->default(0);
            $table->decimal('insurance_cost', 18, 2)->default(0);
            $table->string('payment_policy')->default('prepaid')->index();
            $table->string('customer_charge_payment_status')->default('not_required')->index();
            $table->string('courier_cost_payment_status')->default('not_required')->index();
            $table->string('settlement_status')->default('pending')->index();
            $table->string('status')->default('draft')->index();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_tracked_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_record_id', 'status']);
            $table->index(['courier_provider_id', 'tracking_number']);
        });

        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_shipment_id')->constrained('sales_shipments')->cascadeOnDelete();
            $table->string('event_code')->nullable()->index();
            $table->string('status')->index();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('event_at')->index();
            $table->string('source')->default('manual')->index();
            $table->string('external_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->seedCouriers();
        MasterDataRegistry::syncDefaults();
    }

    public function down()
    {
        Schema::dropIfExists('shipment_tracking_events');
        Schema::dropIfExists('sales_shipments');
        Schema::dropIfExists('courier_providers');
    }

    private function seedCouriers(): void
    {
        $now = now();
        $providers = [
            ['code' => 'jne', 'name' => 'JNE', 'services' => ['REG', 'YES', 'OKE'], 'supports_cod' => false],
            ['code' => 'jnt', 'name' => 'J&T Express', 'services' => ['EZ', 'ECO', 'Super'], 'supports_cod' => true],
            ['code' => 'sicepat', 'name' => 'SiCepat', 'services' => ['REG', 'BEST', 'HALU'], 'supports_cod' => true],
            ['code' => 'anteraja', 'name' => 'AnterAja', 'services' => ['Regular', 'Next Day', 'Economy'], 'supports_cod' => true],
            ['code' => 'pos', 'name' => 'Pos Indonesia', 'services' => ['Pos Reguler', 'Pos Nextday'], 'supports_cod' => true],
            ['code' => 'internal', 'name' => 'Internal Delivery', 'services' => ['Fleet', 'Pickup'], 'supports_cod' => false],
        ];

        foreach ($providers as $provider) {
            DB::table('courier_providers')->updateOrInsert(
                ['code' => $provider['code']],
                [
                    'name' => $provider['name'],
                    'integration_mode' => 'manual',
                    'services' => json_encode($provider['services']),
                    'supports_cod' => $provider['supports_cod'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};

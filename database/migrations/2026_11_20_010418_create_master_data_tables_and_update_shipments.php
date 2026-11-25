<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('province', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contact_categories')) {
            Schema::create('contact_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_category_id')->nullable()->constrained('contact_categories')->nullOnDelete();
                $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
                $table->string('code', 30)->unique();
                $table->string('name', 120);
                $table->string('company_name', 120)->nullable();
                $table->string('phone', 30);
                $table->string('email', 120)->nullable();
                $table->text('address');
                $table->string('postal_code', 15)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('service_types')) {
            Schema::create('service_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('sla_days', 50)->nullable();
                $table->decimal('base_rate', 12, 2)->default(0);
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('shipment_types')) {
            Schema::create('shipment_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('handling_note')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('service_areas')) {
            Schema::create('service_areas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
                $table->string('code', 30)->unique();
                $table->string('name', 120);
                $table->string('hub_name', 120);
                $table->text('coverage_districts')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (!Schema::hasColumn('shipments', 'item_description')) {
                    $table->string('item_description')->nullable()->after('destination_address');
                }
                if (!Schema::hasColumn('shipments', 'shipment_type')) {
                    $table->string('shipment_type', 100)->nullable()->default('Paket Reguler')->after('service_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (Schema::hasColumn('shipments', 'item_description')) {
                    $table->dropColumn('item_description');
                }
                if (Schema::hasColumn('shipments', 'shipment_type')) {
                    $table->dropColumn('shipment_type');
                }
            });
        }

        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('contact_categories');
        Schema::dropIfExists('service_types');
        Schema::dropIfExists('shipment_types');
    }
};
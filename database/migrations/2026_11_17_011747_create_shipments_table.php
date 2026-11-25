<?php

use App\Enums\ServiceType;
use App\Enums\ShipmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('awb_number', 40)->unique();
            $table->foreignId('courier_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('sender_name', 100);
            $table->string('sender_phone', 20);
            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 20);
            $table->text('origin_address');
            $table->text('destination_address');
            $table->enum('service_type', ServiceType::values())->default(ServiceType::REGULAR->value);
            $table->decimal('weight_kg', 8, 2)->default(1.00);
            $table->enum('status', ShipmentStatus::values())
                ->default(ShipmentStatus::AWB_CREATED->value)
                ->index();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proof_of_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')
                ->unique()
                ->constrained('shipments')
                ->cascadeOnDelete();
            $table->foreignId('courier_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('recipient_name', 100);
            $table->string('recipient_relation', 50)->nullable();
            $table->string('photo_path', 255);
            $table->string('signature_path', 255);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_of_deliveries');
    }
};
<?php

use App\Enums\ManifestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_number', 40)->unique();
            $table->foreignId('courier_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('origin_hub', 100);
            $table->string('destination_hub', 100);
            $table->date('manifest_date')->index();
            $table->enum('status', ManifestStatus::values())
                ->default(ManifestStatus::OPEN->value)
                ->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifests');
    }
};
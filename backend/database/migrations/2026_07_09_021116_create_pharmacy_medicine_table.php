<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory pivot: which medicines each pharmacy stocks, with the live
     * stock count and availability status (FR5, FR6). POS sales decrement
     * stock_quantity here.
     */
    public function up(): void
    {
        Schema::create('pharmacy_medicine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('availability_status', ['available', 'low', 'out_of_stock'])->default('available');
            $table->timestamps();

            $table->unique(['pharmacy_id', 'medicine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_medicine');
    }
};

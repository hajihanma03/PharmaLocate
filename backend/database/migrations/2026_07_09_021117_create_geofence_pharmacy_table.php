<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assigns pharmacies to geofence zones (FR8/FR9). Many-to-many so a
     * pharmacy can belong to more than one overlapping zone if needed.
     */
    public function up(): void
    {
        Schema::create('geofence_pharmacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geofence_id')->constrained('geofences')->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['geofence_id', 'pharmacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_pharmacy');
    }
};

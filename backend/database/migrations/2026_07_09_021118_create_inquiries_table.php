<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer inquiries about medicine availability (FR3, FR4). Staff reply
     * and move the status through pending -> in_progress -> resolved.
     */
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->text('message');
            $table->text('response')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'resolved'])->default('pending');
            // (Removed in 2026_07_20_000001_remove_user_priority_types.)
            $table->boolean('is_priority')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // System role: 'admin', 'staff' (pharmacy staff), or 'customer'.
            $table->enum('role', ['admin', 'staff', 'customer'])->default('customer');
            // (Removed in 2026_07_20_000001_remove_user_priority_types.)
            $table->enum('priority_type', ['senior', 'pwd', 'pregnant', 'parent'])->nullable();
            // Staff accounts belong to a pharmacy. No FK constraint here because the
            // pharmacies table is created by a later migration; the relationship is
            // enforced at the application (model) layer instead.
            $table->unsignedBigInteger('pharmacy_id')->nullable()->index();
            $table->string('phone')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

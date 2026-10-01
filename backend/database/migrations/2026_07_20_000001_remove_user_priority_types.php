<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('priority_type');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn('is_priority');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('priority_type', ['senior', 'pwd', 'pregnant', 'parent'])->nullable()->after('role');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->boolean('is_priority')->default(false)->after('status');
        });
    }
};

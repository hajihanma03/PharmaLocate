<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->boolean('is_starting_point')->default(false)->after('is_active');
        });

        DB::table('geofences')->where(function ($query) {
            $query->where('name', 'like', '%TPH%')
                ->orWhere('name', 'like', '%Tarlac Provincial%');
        })->update(['is_starting_point' => true]);
    }

    public function down(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->dropColumn('is_starting_point');
        });
    }
};

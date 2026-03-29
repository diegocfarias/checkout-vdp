<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_emissions', function (Blueprint $table) {
            $table->decimal('miles_cost_per_thousand', 10, 2)->nullable()->after('emission_value');
        });
    }

    public function down(): void
    {
        Schema::table('order_emissions', function (Blueprint $table) {
            $table->dropColumn('miles_cost_per_thousand');
        });
    }
};

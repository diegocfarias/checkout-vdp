<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_flights', function (Blueprint $table) {
            $table->decimal('provider_direct_cost', 12, 2)->nullable()->after('provider_payload');
        });

        Schema::table('order_emissions', function (Blueprint $table) {
            $table->string('emission_provider', 40)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('order_flights', function (Blueprint $table) {
            $table->dropColumn('provider_direct_cost');
        });

        Schema::table('order_emissions', function (Blueprint $table) {
            $table->dropColumn('emission_provider');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_flights', function (Blueprint $table) {
            $table->json('provider_payload')->nullable()->after('source_airlines');
        });
    }

    public function down(): void
    {
        Schema::table('order_flights', function (Blueprint $table) {
            $table->dropColumn('provider_payload');
        });
    }
};

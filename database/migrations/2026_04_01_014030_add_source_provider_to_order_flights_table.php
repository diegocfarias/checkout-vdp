<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_flights', function (Blueprint $table) {
            $table->string('source_provider', 30)->nullable()->after('pricing_type');
            $table->string('source_airlines', 30)->nullable()->after('source_provider');
        });
    }

    public function down(): void
    {
        Schema::table('order_flights', function (Blueprint $table) {
            $table->dropColumn(['source_provider', 'source_airlines']);
        });
    }
};

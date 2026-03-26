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
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('flight_search_id')->nullable()->after('conversation_id');
            $table->foreign('flight_search_id')->references('id')->on('flight_searches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['flight_search_id']);
            $table->dropColumn('flight_search_id');
        });
    }
};

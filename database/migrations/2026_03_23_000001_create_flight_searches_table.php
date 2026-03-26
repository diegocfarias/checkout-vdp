<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_searches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('departure_iata', 3);
            $table->string('arrival_iata', 3);
            $table->date('outbound_date');
            $table->date('inbound_date')->nullable();
            $table->string('trip_type', 10);
            $table->string('cabin', 5);
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('results_count')->nullable();
            $table->timestamps();

            $table->index(['departure_iata', 'arrival_iata', 'outbound_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_searches');
    }
};

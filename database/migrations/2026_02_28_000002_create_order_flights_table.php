<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('direction'); // outbound | inbound
            $table->string('cia');
            $table->string('operator')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('departure_time')->nullable();
            $table->string('arrival_time')->nullable();
            $table->string('departure_location')->nullable();
            $table->string('arrival_location')->nullable();
            $table->string('departure_label')->nullable();
            $table->string('arrival_label')->nullable();
            $table->string('boarding_tax')->nullable();
            $table->string('class_service')->nullable();
            $table->string('price_money')->nullable();
            $table->string('price_miles')->nullable();
            $table->string('price_miles_vip')->nullable();
            $table->string('total_flight_duration')->nullable();

            $table->string('unique_id')->nullable();
            $table->json('connection')->nullable();
            $table->string('miles_price')->nullable();
            $table->string('money_price')->nullable();
            $table->string('tax')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_flights');
    }
};

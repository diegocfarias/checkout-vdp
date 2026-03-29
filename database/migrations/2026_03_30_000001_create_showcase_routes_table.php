<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcase_routes', function (Blueprint $table) {
            $table->id();
            $table->string('departure_iata', 3);
            $table->string('departure_city');
            $table->string('arrival_iata', 3);
            $table->string('arrival_city');
            $table->string('trip_type', 20)->default('roundtrip');
            $table->string('cabin', 20)->default('economy');
            $table->unsignedInteger('search_window_days')->default(30);
            $table->unsignedInteger('return_stay_days')->nullable()->default(7);
            $table->unsignedInteger('sample_dates_count')->default(8);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('image_url', 500)->nullable();
            $table->string('image_credit')->nullable();
            $table->decimal('cached_price', 10, 2)->nullable();
            $table->date('cached_date')->nullable();
            $table->date('cached_return_date')->nullable();
            $table->string('cached_airline', 10)->nullable();
            $table->json('cached_flight_data')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_routes');
    }
};

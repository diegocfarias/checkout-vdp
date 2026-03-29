<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcase_refresh_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showcase_route_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->unsignedInteger('dates_searched')->default(0);
            $table->unsignedInteger('cache_hits')->default(0);
            $table->unsignedInteger('api_calls')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->decimal('best_price', 10, 2)->nullable();
            $table->date('best_date')->nullable();
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['showcase_route_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_refresh_logs');
    }
};

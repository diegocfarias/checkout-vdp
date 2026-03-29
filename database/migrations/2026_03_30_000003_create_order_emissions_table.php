<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_emissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('issuer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('emission_value', 10, 2)->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('issuer_id');
            $table->index('status');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_emissions');
    }
};

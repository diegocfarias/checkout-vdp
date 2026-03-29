<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_emission_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_emission_id')->constrained('order_emissions')->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_issuer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_issuer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_emission_logs');
    }
};

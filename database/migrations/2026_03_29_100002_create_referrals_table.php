<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('referred_order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('referred_document', 14);
            $table->string('referral_code_used', 20);
            $table->decimal('order_base_total', 12, 2);
            $table->decimal('discount_pct', 5, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('credit_pct', 5, 2);
            $table->decimal('credit_amount', 12, 2);
            $table->string('credit_status', 20)->default('pending');
            $table->timestamp('credit_available_at')->nullable();
            $table->timestamp('credit_released_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('affiliate_id');
            $table->index('credit_status');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};

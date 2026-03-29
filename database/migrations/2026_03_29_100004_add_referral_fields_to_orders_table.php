<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->after('coupon_id')->constrained('referrals')->nullOnDelete();
            $table->decimal('wallet_amount_used', 12, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['referral_id']);
            $table->dropColumn(['referral_id', 'wallet_amount_used']);
        });
    }
};

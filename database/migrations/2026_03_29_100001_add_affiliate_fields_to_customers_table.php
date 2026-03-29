<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_affiliate')->default(false)->after('status');
            $table->string('referral_code', 20)->nullable()->unique()->after('is_affiliate');
            $table->decimal('affiliate_discount_pct', 5, 2)->nullable()->after('referral_code');
            $table->decimal('affiliate_credit_pct', 5, 2)->nullable()->after('affiliate_discount_pct');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['is_affiliate', 'referral_code', 'affiliate_discount_pct', 'affiliate_credit_pct']);
        });
    }
};

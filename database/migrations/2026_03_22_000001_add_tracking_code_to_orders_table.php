<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_code', 10)->unique()->nullable()->after('token');
        });

        // Backfill existing orders
        $orders = \App\Models\Order::whereNull('tracking_code')->get();
        foreach ($orders as $order) {
            $order->update(['tracking_code' => 'VDP-' . strtoupper(Str::random(4))]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking_code');
        });
    }
};

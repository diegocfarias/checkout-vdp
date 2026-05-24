<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_passengers', function (Blueprint $table) {
            $table->string('document')->nullable()->change();
        });

        Schema::table('saved_passengers', function (Blueprint $table) {
            $table->string('document')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_passengers', function (Blueprint $table) {
            $table->string('document')->nullable(false)->change();
        });

        Schema::table('saved_passengers', function (Blueprint $table) {
            $table->string('document')->nullable(false)->change();
        });
    }
};

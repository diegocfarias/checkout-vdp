<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_passengers', function (Blueprint $table) {
            $table->string('nationality', 2)->default('BR')->after('full_name');
            $table->string('passport_number', 50)->nullable()->after('nationality');
            $table->date('passport_expiry')->nullable()->after('passport_number');
        });

        Schema::table('saved_passengers', function (Blueprint $table) {
            $table->string('nationality', 2)->default('BR')->after('full_name');
            $table->string('passport_number', 50)->nullable()->after('nationality');
            $table->date('passport_expiry')->nullable()->after('passport_number');
        });
    }

    public function down(): void
    {
        Schema::table('order_passengers', function (Blueprint $table) {
            $table->dropColumn(['nationality', 'passport_number', 'passport_expiry']);
        });

        Schema::table('saved_passengers', function (Blueprint $table) {
            $table->dropColumn(['nationality', 'passport_number', 'passport_expiry']);
        });
    }
};

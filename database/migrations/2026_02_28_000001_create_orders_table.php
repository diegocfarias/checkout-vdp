<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->unsignedTinyInteger('total_adults');
            $table->unsignedTinyInteger('total_children')->default(0);
            $table->unsignedTinyInteger('total_babies')->default(0);
            $table->string('user_id')->nullable();
            $table->string('conversation_id')->nullable();
            $table->string('cabin');
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

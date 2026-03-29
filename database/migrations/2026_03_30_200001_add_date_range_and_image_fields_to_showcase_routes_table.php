<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showcase_routes', function (Blueprint $table) {
            $table->date('search_date_from')->nullable()->after('sample_dates_count');
            $table->date('search_date_to')->nullable()->after('search_date_from');
            $table->string('image_search_query', 255)->nullable()->after('image_credit');
            $table->unsignedInteger('image_zoom')->default(100)->after('image_search_query');
        });
    }

    public function down(): void
    {
        Schema::table('showcase_routes', function (Blueprint $table) {
            $table->dropColumn(['search_date_from', 'search_date_to', 'image_search_query', 'image_zoom']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('cancellation_reason')->nullable()->after('priority');
            $table->boolean('cancellation_within_policy')->default(false)->after('cancellation_reason');
            $table->json('cancellation_policy_snapshot')->nullable()->after('cancellation_within_policy');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_policy_snapshot');

            $table->index(['subject', 'cancellation_within_policy', 'status'], 'support_tickets_cancellation_priority_index');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex('support_tickets_cancellation_priority_index');
            $table->dropColumn([
                'cancellation_reason',
                'cancellation_within_policy',
                'cancellation_policy_snapshot',
                'cancellation_requested_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Set when the off-session notification has been delivered. The job
            // claims this atomically (whereNull update) so a retried/duplicate
            // run can never double-notify (CLAUDE.md "idempotent on ussd_session_id").
            $table->timestamp('notified_at')->nullable()->after('ussd_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};

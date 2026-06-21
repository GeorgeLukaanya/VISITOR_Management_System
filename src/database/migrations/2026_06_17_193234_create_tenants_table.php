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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Routing code: the key a visitor types to reach this tenant.
            //
            // DECISION (Phase 1, see CLAUDE.md): codes are GLOBALLY UNIQUE so the
            // two-screen flow can resolve a tenant without first asking which
            // building the visitor is in. A per-building unique code (shorter,
            // reusable across buildings) is the more scalable alternative but
            // costs an extra screen.
            // >>> FLAG FOR ARTHUR to confirm with the client (relates to Q1/Q4).
            // If this changes to per-building, swap this unique() for a
            // composite unique(['building_id','routing_code']) and update
            // Tenant::resolveByCode().
            $table->string('routing_code')->unique();

            // Notification settings (who to alert off-session on check-in).
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();   // tenant contact (SMS target)
            $table->boolean('notify_tenant')->default(true);
            $table->boolean('notify_guard')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            // building_id is denormalised onto the visit so building-manager
            // queries and scoping never need a join through tenants.
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Visitor phone comes from the USSD session payload — never asked for. PII.
            $table->string('visitor_phone');
            $table->string('purpose');  // Meeting / Delivery / Interview / Other

            $table->string('status')->default('checked_in'); // checked_in | checked_out | auto_closed
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();

            // The Africa's Talking USSD session id. Unique so a retried/duplicate
            // callback for the same session can never create a second visit, and
            // so the off-session notification job stays idempotent on it.
            $table->string('ussd_session_id')->nullable()->unique();

            $table->timestamps();

            $table->index(['tenant_id', 'checked_in_at']);
            $table->index(['building_id', 'checked_in_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};

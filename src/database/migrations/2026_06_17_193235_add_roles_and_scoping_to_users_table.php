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
        Schema::table('users', function (Blueprint $table) {
            // role: platform_admin | building_manager | tenant_admin (see App\Enums\UserRole).
            $table->string('role')->default('tenant_admin')->after('password');

            // Scoping anchors. A platform_admin has both null (sees everything).
            // A building_manager has building_id set (sees their building).
            // A tenant_admin has both set (sees only their tenant).
            $table->foreignId('building_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->after('building_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropConstrainedForeignId('building_id');
            $table->dropColumn('role');
        });
    }
};

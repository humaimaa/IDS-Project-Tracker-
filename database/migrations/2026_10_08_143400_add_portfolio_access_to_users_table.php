<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role_id')) {
            return;
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('account_status')->default('Active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('account_status');
        });
    }
};

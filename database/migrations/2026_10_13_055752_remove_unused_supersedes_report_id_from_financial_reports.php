<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }
        if (DB::table('financial_reports')->whereNotNull('supersedes_report_id')->exists()) {
            throw new RuntimeException('Financial replacement links contain data. Review them before removing this column.');
        }
        Schema::table('financial_reports', function (Blueprint $table): void {
            $table->dropForeign(['supersedes_report_id']);
            $table->dropColumn('supersedes_report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_reports', function (Blueprint $table): void {
            $table->foreignId('supersedes_report_id')->nullable()->constrained('financial_reports')->restrictOnDelete();
        });
    }
};

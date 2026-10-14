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
        Schema::dropIfExists('financial_totals');
        $permissions = DB::table('permissions')->where('code', 'like', 'financial-totals.%')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissions)->delete();
        DB::table('permissions')->whereIn('id', $permissions)->delete();
        Schema::table('financial_reports', function (Blueprint $table): void {
            $table->string('currency_code', 3)->default('PKR');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        foreach (DB::table('financial_reports')->select('project_id', 'financial_year_id')->distinct()->get() as $group) {
            $reports = DB::table('financial_reports')->where('project_id', $group->project_id)->where('financial_year_id', $group->financial_year_id)->orderByDesc('revision_no')->orderByDesc('id')->get();
            $keep = $reports->first();
            $discard = $reports->skip(1)->pluck('id');
            DB::table('document_links')->whereIn('financial_report_id', $discard)->update(['financial_report_id' => $keep->id]);
            DB::table('annual_financials')->whereIn('report_id', $discard)->delete();
            DB::table('quarterly_financials')->whereIn('report_id', $discard)->delete();
            DB::table('financial_reports')->whereIn('id', $discard)->delete();
            $currency = DB::table('quarterly_financials')->where('report_id', $keep->id)->orderBy('id')->value('currency_code')
                ?? DB::table('annual_financials')->where('report_id', $keep->id)->orderBy('id')->value('currency_code') ?? 'PKR';
            DB::table('annual_financials')->where('report_id', $keep->id)->where('currency_code', '!=', $currency)->delete();
            DB::table('quarterly_financials')->where('report_id', $keep->id)->where('currency_code', '!=', $currency)->delete();
            $values = ['currency_code' => $currency, 'revision_no' => 1];
            if ($discard->isNotEmpty()) {
                $values += ['status' => 'Draft', 'approved_by' => null, 'approved_at' => null, 'approval_notes' => null];
            }
            DB::table('financial_reports')->where('id', $keep->id)->update($values);
        }
        Schema::table('financial_reports', function (Blueprint $table): void {
            $table->unique(['project_id', 'financial_year_id'], 'financial_project_year_unique');
        });
        Schema::table('annual_financials', fn (Blueprint $table) => $table->unique('report_id', 'financial_annual_report_unique'));
        Schema::table('quarterly_financials', fn (Blueprint $table) => $table->unique(['report_id', 'period_id'], 'financial_quarter_report_period_unique'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('This consolidation cannot reconstruct discarded source figures. Restore the pre-migration database backup to reverse it.');
    }
};

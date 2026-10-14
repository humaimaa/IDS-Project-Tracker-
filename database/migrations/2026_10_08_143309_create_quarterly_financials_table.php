<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quarterly_financials')) {
            return;
        }
        Schema::create('quarterly_financials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('financial_reports')->restrictOnDelete();
            $table->foreignId('period_id')->constrained('reporting_periods')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->decimal('planned_disbursement', 20, 2)->nullable();
            $table->decimal('actual_disbursement', 20, 2)->nullable();
            $table->decimal('expenditure_amount', 20, 2)->nullable();
            $table->unique(['report_id', 'period_id', 'currency_code']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_financials');
    }
};

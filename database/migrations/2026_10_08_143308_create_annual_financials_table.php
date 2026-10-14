<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('annual_financials')) {
            return;
        }
        Schema::create('annual_financials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('financial_reports')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->decimal('total_allocation', 20, 2)->nullable();
            $table->decimal('expenditure_ytd', 20, 2)->nullable();
            $table->decimal('cumulative_expenditure', 20, 2)->nullable();
            $table->decimal('disbursement_target', 20, 2)->nullable();
            $table->decimal('disbursement_ytd', 20, 2)->nullable();
            $table->unique(['report_id', 'currency_code']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_financials');
    }
};

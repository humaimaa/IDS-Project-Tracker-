<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_totals')) {
            return;
        }
        Schema::create('financial_totals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('financial_reports')->restrictOnDelete();
            $table->foreignId('agreement_id')->constrained('financing_agreements')->restrictOnDelete();
            $table->decimal('net_commitment', 20, 2)->nullable();
            $table->decimal('cumulative_disbursed', 20, 2)->nullable();
            $table->unique(['report_id', 'agreement_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_totals');
    }
};

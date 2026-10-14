<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_reports')) {
            return;
        }
        Schema::create('financial_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->date('as_of_date');
            $table->unsignedBigInteger('revision_no');
            $table->string('status')->default('Draft');
            $table->foreignId('supersedes_report_id')->nullable()->constrained('financial_reports')->restrictOnDelete();
            $table->text('source_notes')->nullable();
            $table->unique(['project_id', 'financial_year_id', 'revision_no']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financing_agreements')) {
            return;
        }
        Schema::create('financing_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('donor_id')->constrained('organizations')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->string('agreement_ref')->nullable();
            $table->decimal('original_commitment', 20, 2)->nullable();
            $table->date('signing_date')->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_agreements');
    }
};

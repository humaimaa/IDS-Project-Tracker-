<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_cost_versions')) {
            return;
        }
        Schema::create('project_cost_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->unsignedBigInteger('version_no');
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->decimal('total_cost', 20, 2);
            $table->decimal('foreign_cost', 20, 2)->nullable();
            $table->decimal('local_cost', 20, 2)->nullable();
            $table->date('effective_on')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('project_cost_versions')->restrictOnDelete();
            $table->unique(['project_id', 'version_no']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_cost_versions');
    }
};

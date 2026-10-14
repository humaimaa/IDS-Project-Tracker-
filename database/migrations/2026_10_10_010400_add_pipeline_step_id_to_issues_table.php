<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->foreignId('pipeline_step_id')->nullable()->constrained('project_pipeline_steps')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pipeline_step_id');
        });
    }
};

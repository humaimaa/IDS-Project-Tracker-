<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_pipeline_steps', function (Blueprint $table): void {
            $table->date('start_date')->nullable();
            $table->string('responsible_office')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_pipeline_steps', function (Blueprint $table): void {
            $table->dropColumn(['start_date', 'responsible_office']);
        });
    }
};

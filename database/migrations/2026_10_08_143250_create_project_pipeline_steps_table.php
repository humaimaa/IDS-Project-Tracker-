<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_pipeline_steps')) {
            return;
        }
        Schema::create('project_pipeline_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('template_id')->constrained('pipeline_step_templates')->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('sort_order');
            $table->string('status')->default('Not started');
            $table->date('planned_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->boolean('is_current')->default(0);
            $table->text('remarks')->nullable();
            $table->unique(['project_id', 'template_id']);
            $table->timestamps();
        });
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'])) {
            DB::statement('CREATE UNIQUE INDEX pipeline_current_step ON project_pipeline_steps (project_id) WHERE is_current = true');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_pipeline_steps');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_phase_history')) {
            return;
        }
        Schema::create('project_phase_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('from_phase');
            $table->string('to_phase');
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('changed_at');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phase_history');
    }
};

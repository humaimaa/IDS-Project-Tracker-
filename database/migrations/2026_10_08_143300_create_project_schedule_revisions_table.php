<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_schedule_revisions')) {
            return;
        }
        Schema::create('project_schedule_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->unsignedBigInteger('revision_no');
            $table->date('previous_end');
            $table->date('revised_end');
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->unique(['project_id', 'revision_no']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_schedule_revisions');
    }
};

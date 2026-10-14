<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_projects')) {
            return;
        }
        Schema::create('meeting_projects', function (Blueprint $table): void {
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->primary(['meeting_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_projects');
    }
};

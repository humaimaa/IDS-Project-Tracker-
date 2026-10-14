<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_organizations')) {
            return;
        }
        Schema::create('project_organizations', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('role');
            $table->primary(['project_id', 'organization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_organizations');
    }
};

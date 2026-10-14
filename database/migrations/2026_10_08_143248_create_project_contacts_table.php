<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_contacts')) {
            return;
        }
        Schema::create('project_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('responsibility');
            $table->boolean('is_primary')->default(0);
            $table->unique(['project_id', 'contact_id', 'responsibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contacts');
    }
};

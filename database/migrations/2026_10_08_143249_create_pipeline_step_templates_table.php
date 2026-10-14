<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pipeline_step_templates')) {
            return;
        }
        Schema::create('pipeline_step_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('sort_order')->unique();
            $table->boolean('active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_step_templates');
    }
};

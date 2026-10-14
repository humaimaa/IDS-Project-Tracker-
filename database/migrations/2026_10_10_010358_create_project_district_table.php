<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_district', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->primary(['project_id', 'district_id']);
        });
        DB::table('projects')->orderBy('id')->each(function (object $project): void {
            DB::table('project_district')->insert(['project_id' => $project->id, 'district_id' => $project->district_id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_district');
    }
};

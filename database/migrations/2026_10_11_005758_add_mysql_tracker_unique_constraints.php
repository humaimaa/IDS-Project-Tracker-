<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        Schema::table('project_pipeline_steps', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_project_key')->nullable()->storedAs('CASE WHEN is_current = 1 THEN project_id ELSE NULL END');
            $table->unique('current_project_key', 'pipeline_current_project_mysql');
        });
        Schema::table('extension_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('pending_component_key')->nullable()->storedAs("CASE WHEN status = 'Pending' THEN component_id ELSE NULL END");
            $table->unsignedBigInteger('pending_subcomponent_key')->nullable()->storedAs("CASE WHEN status = 'Pending' THEN subcomponent_id ELSE NULL END");
            $table->unique('pending_component_key', 'extension_pending_component_mysql');
            $table->unique('pending_subcomponent_key', 'extension_pending_subcomponent_mysql');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        Schema::table('extension_requests', function (Blueprint $table): void {
            $table->dropUnique('extension_pending_component_mysql');
            $table->dropUnique('extension_pending_subcomponent_mysql');
            $table->dropColumn(['pending_component_key', 'pending_subcomponent_key']);
        });
        Schema::table('project_pipeline_steps', function (Blueprint $table): void {
            $table->dropUnique('pipeline_current_project_mysql');
            $table->dropColumn('current_project_key');
        });
    }
};

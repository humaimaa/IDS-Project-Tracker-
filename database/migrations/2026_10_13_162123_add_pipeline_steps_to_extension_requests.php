<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_pipeline_steps', function (Blueprint $table): void {
            $table->unsignedBigInteger('version')->default(1);
        });
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE extension_requests ADD COLUMN pipeline_step_id INTEGER REFERENCES project_pipeline_steps(id) ON DELETE RESTRICT');
        } else {
            Schema::table('extension_requests', function (Blueprint $table): void {
                $table->foreignId('pipeline_step_id')->nullable()->constrained('project_pipeline_steps')->restrictOnDelete();
            });
        }
        $expression = '(CASE WHEN @component_id IS NULL THEN 0 ELSE 1 END + CASE WHEN @subcomponent_id IS NULL THEN 0 ELSE 1 END + CASE WHEN @pipeline_step_id IS NULL THEN 0 ELSE 1 END) = 1';
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("DROP TRIGGER extension_requests_one_target_{$event}");
                $condition = str_replace('@', 'NEW.', $expression);
                DB::unprepared("CREATE TRIGGER extension_requests_one_target_{$event} BEFORE {$event} ON extension_requests WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, 'Invalid extension_requests: one_target'); END");
            }
        } else {
            $drop = DB::getDriverName() === 'mysql' ? 'DROP CHECK' : 'DROP CONSTRAINT';
            DB::statement("ALTER TABLE extension_requests {$drop} extension_requests_one_target_check");
            $condition = str_replace('@', '', $expression);
            DB::statement("ALTER TABLE extension_requests ADD CONSTRAINT extension_requests_one_target_check CHECK ({$condition})");
        }
        if (DB::getDriverName() === 'mysql') {
            Schema::table('extension_requests', function (Blueprint $table): void {
                $table->unsignedBigInteger('pending_pipeline_step_key')->nullable()->storedAs("CASE WHEN status = 'Pending' THEN pipeline_step_id ELSE NULL END");
                $table->unique('pending_pipeline_step_key', 'extension_pending_pipeline_step');
            });
        } else {
            DB::statement("CREATE UNIQUE INDEX extension_pending_pipeline_step ON extension_requests (pipeline_step_id) WHERE status = 'Pending' AND pipeline_step_id IS NOT NULL");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the pre-migration backup to roll back pipeline extensions without losing request history.');
    }
};

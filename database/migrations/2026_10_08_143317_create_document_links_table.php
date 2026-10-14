<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_links')) {
            return;
        }
        Schema::create('document_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('components')->restrictOnDelete();
            $table->foreignId('subcomponent_id')->nullable()->constrained('subcomponents')->restrictOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained('issues')->restrictOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->restrictOnDelete();
            $table->foreignId('extension_request_id')->nullable()->constrained('extension_requests')->restrictOnDelete();
            $table->foreignId('financial_report_id')->nullable()->constrained('financial_reports')->restrictOnDelete();
            $table->timestamps();
        });
        $this->check('document_links', 'one_target', '(CASE WHEN @project_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN @component_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN @subcomponent_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN @issue_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN @meeting_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN @extension_request_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN @financial_report_id IS NULL THEN 0 ELSE 1 END) = 1');
    }

    private function check(string $table, string $name, string $expression): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                $condition = str_replace('@', 'NEW.', $expression);
                DB::unprepared("CREATE TRIGGER {$table}_{$name}_{$event} BEFORE {$event} ON {$table} WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, 'Invalid {$table}: {$name}'); END");
            }
        } else {
            $condition = str_replace('@', '', $expression);
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$name}_check CHECK ({$condition})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};

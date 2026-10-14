<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('issues')) {
            return;
        }
        Schema::create('issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('components')->restrictOnDelete();
            $table->foreignId('subcomponent_id')->nullable()->constrained('subcomponents')->restrictOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('issue_rules')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->nullable();
            $table->string('status')->default('Open');
            $table->date('due_date')->nullable();
            $table->date('raised_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->string('responsible_entity')->nullable();
            $table->string('source_key')->nullable()->unique();
            $table->timestamps();
        });
        $this->check('issues', 'one_target', '@component_id IS NULL OR @subcomponent_id IS NULL');
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
        Schema::dropIfExists('issues');
    }
};

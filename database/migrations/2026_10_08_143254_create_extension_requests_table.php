<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extension_requests')) {
            return;
        }
        Schema::create('extension_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->nullable()->constrained('components')->restrictOnDelete();
            $table->foreignId('subcomponent_id')->nullable()->constrained('subcomponents')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->date('old_end_date');
            $table->date('requested_end_date');
            $table->text('reason');
            $table->text('impact')->nullable();
            $table->string('status')->default('Pending');
            $table->unsignedBigInteger('target_version');
            $table->foreignId('supersedes_id')->nullable()->constrained('extension_requests')->restrictOnDelete();
            $table->timestamps();
        });
        $this->check('extension_requests', 'one_target', '(CASE WHEN @component_id IS NULL THEN 0 ELSE 1 END + CASE WHEN @subcomponent_id IS NULL THEN 0 ELSE 1 END) = 1');
        $this->check('extension_requests', 'later_date', '@requested_end_date > @old_end_date');
        $this->check('extension_requests', 'separate_reviewer', '@requester_id <> @reviewer_id');
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'])) {
            DB::statement("CREATE UNIQUE INDEX extension_pending_component ON extension_requests (component_id) WHERE status = 'Pending' AND component_id IS NOT NULL");
            DB::statement("CREATE UNIQUE INDEX extension_pending_subcomponent ON extension_requests (subcomponent_id) WHERE status = 'Pending' AND subcomponent_id IS NOT NULL");
        }
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
        Schema::dropIfExists('extension_requests');
    }
};

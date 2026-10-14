<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('progress_updates')) {
            return;
        }
        Schema::create('progress_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->nullable()->constrained('components')->restrictOnDelete();
            $table->foreignId('subcomponent_id')->nullable()->constrained('subcomponents')->restrictOnDelete();
            $table->date('as_of_date');
            $table->decimal('progress_percent', 5, 2);
            $table->string('status');
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
        $this->check('progress_updates', 'one_target', '(CASE WHEN @component_id IS NULL THEN 0 ELSE 1 END + CASE WHEN @subcomponent_id IS NULL THEN 0 ELSE 1 END) = 1');
        $this->check('progress_updates', 'percent', '@progress_percent >= 0 AND @progress_percent <= 100');
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
        Schema::dropIfExists('progress_updates');
    }
};

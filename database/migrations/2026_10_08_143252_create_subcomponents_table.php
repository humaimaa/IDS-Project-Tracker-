<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subcomponents')) {
            return;
        }
        Schema::create('subcomponents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained('components')->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('sequence_no');
            $table->date('start_date')->nullable();
            $table->date('baseline_end')->nullable();
            $table->date('effective_end')->nullable();
            $table->string('lifecycle')->default('Not started');
            $table->decimal('weight', 20, 2)->nullable();
            $table->decimal('reported_progress', 5, 2)->nullable();
            $table->string('responsible_office')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->unique(['component_id', 'sequence_no']);
            $table->timestamps();
        });
        $this->check('subcomponents', 'progress', '@reported_progress IS NULL OR (@reported_progress >= 0 AND @reported_progress <= 100)');
        $this->check('subcomponents', 'deadline', '@baseline_end IS NULL OR @effective_end >= @baseline_end');
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
        Schema::dropIfExists('subcomponents');
    }
};

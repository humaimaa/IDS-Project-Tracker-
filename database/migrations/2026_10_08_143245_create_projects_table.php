<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            return;
        }
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('name');
            $table->text('objective')->nullable();
            $table->string('phase');
            $table->string('lifecycle')->default('Active');
            $table->boolean('is_flagship')->default(0);
            $table->foreignId('sector_id')->constrained('sectors')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('original_end_date')->nullable();
            $table->date('current_end_date')->nullable();
            $table->string('approval_forum')->nullable();
            $table->date('approval_date')->nullable();
            $table->text('funding_type')->nullable();
            $table->string('source_rating')->nullable();
            $table->decimal('reported_progress', 5, 2)->nullable();
            $table->date('progress_as_of')->nullable();
            $table->text('progress_scope')->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamps();
        });
        $this->check('projects', 'phase', "@phase IN ('Pipeline', 'Ongoing')");
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
        Schema::dropIfExists('projects');
    }
};

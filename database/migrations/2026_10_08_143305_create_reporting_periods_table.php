<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reporting_periods')) {
            return;
        }
        Schema::create('reporting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->unsignedBigInteger('quarter_no');
            $table->date('start_date');
            $table->date('end_date');
            $table->unique(['financial_year_id', 'quarter_no']);
            $table->timestamps();
        });
        $this->check('reporting_periods', 'quarter', '@quarter_no BETWEEN 1 AND 4');
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
        Schema::dropIfExists('reporting_periods');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extension_decisions')) {
            return;
        }
        Schema::create('extension_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('extension_requests')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('sequence_no');
            $table->string('decision');
            $table->date('approved_end_date')->nullable();
            $table->text('remarks');
            $table->dateTime('decided_at');
            $table->unique(['request_id', 'sequence_no']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_decisions');
    }
};

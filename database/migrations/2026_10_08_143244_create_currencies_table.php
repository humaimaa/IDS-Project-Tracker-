<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('currencies')) {
            return;
        }
        Schema::create('currencies', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->string('name');
            $table->unsignedBigInteger('decimal_places')->default(2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Compatibility marker for local databases created before the migration split.
     * Table definitions now live in the following per-table migrations, which adopt existing tables.
     */
    public function up(): void {}

    public function down(): void {}
};

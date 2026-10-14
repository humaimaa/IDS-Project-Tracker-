<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('implementing_agency')->nullable();
        });
        DB::table('project_organizations')->where('role', 'implementing')->join('organizations', 'organizations.id', '=', 'project_organizations.organization_id')->select('project_id', 'organizations.name')->get()->each(function (object $agency): void {
            DB::table('projects')->where('id', $agency->project_id)->update(['implementing_agency' => $agency->name]);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('implementing_agency');
        });
    }
};

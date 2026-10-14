<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\PortfolioAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = collect(PortfolioAccess::permissions())->mapWithKeys(fn (string $code): array => [$code => Permission::firstOrCreate(['code' => $code])->id]);
            foreach (['Super Admin', 'Admin', 'Project Director'] as $name) {
                $role = Role::firstOrCreate(['name' => $name]);
                $codes = match ($name) {
                    'Super Admin' => $permissions->keys(),
                    'Admin' => $permissions->keys()->reject(fn (string $code): bool => str_starts_with($code, 'roles.') && $code !== 'roles.view'),
                    'Project Director' => $permissions->keys()->filter(fn (string $code): bool => in_array($code, ['dashboard.view', 'projects.view', 'projects.create', 'projects.update', 'projects.flagship', 'comments.view', 'comments.create', 'documents.view', 'documents.create', 'extensions.view', 'extensions.request', 'extensions.withdraw']) || (in_array(explode('.', $code)[0], ['issues', 'activities', 'subcomponents', 'progress-updates', 'pipeline-steps', 'project-costs', 'financing-agreements', 'financial-reports', 'annual-financials', 'quarterly-financials', 'meetings']) && ! in_array(explode('.', $code)[1], ['delete', 'approve']) && ! str_starts_with($code, 'meetings.create') && ! str_starts_with($code, 'meetings.update'))),
                };
                foreach ($codes as $code) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permissions[$code]]);
                }
            }
        });
    }
}

<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /** @var list<array{name: string, email: string, password: string, role: string}> */
    public const DEMO_USERS = [
        ['name' => 'Demo Super Admin', 'email' => 'superadmin@ids.local', 'password' => 'SuperAdmin@12345', 'role' => 'Super Admin'],
        ['name' => 'Demo Admin', 'email' => 'admin@ids.local', 'password' => 'Admin@12345Demo', 'role' => 'Admin'],
    ];

    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        DB::transaction(function (): void {
            foreach (self::DEMO_USERS as $account) {
                if (User::where('email', $account['email'])->exists()) {
                    continue;
                }
                $user = new User;
                $user->forceFill(['name' => $account['name'], 'email' => $account['email'], 'password' => $account['password'], 'role_id' => Role::where('name', $account['role'])->sole()->id, 'account_status' => 'Active', 'email_verified_at' => null])->save();
            }
        });
    }
}

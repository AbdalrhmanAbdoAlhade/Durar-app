<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminSeeder extends Seeder
{
    /**
     * Create the admin role (sanctum guard) and the default admin user.
     *
     * Override defaults via .env: ADMIN_NAME, ADMIN_EMAIL, ADMIN_PHONE, ADMIN_PASSWORD
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'phone' => env('ADMIN_PHONE', '01000000000'),
                'password' => env('ADMIN_PASSWORD', 'password123'),
            ]
        );

        if (! $admin->hasRole($role)) {
            $admin->assignRole($role);
        }
    }
}

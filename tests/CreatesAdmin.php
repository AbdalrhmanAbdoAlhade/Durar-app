<?php

namespace Tests;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait CreatesAdmin
{
    protected function createAdmin(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);

        $admin = User::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create default roles
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Store Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Representative', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);

        // Sync existing users
        foreach (User::all() as $user) {
            $roleStr = $user->role ?? 'customer'; // string 'admin', 'manager', 'customer'

            if ($roleStr === 'admin') {
                $user->assignRole('Super Admin');
            } elseif ($roleStr === 'manager') {
                $user->assignRole('Store Manager');
            } elseif ($roleStr === 'sales_rep') {
                $user->assignRole('Sales Representative');
            } else {
                $user->assignRole('Customer');
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        // optionally remove roles
    }
};

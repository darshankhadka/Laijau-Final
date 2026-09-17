<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Workspace Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Store Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Support Agent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Representative', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

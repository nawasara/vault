<?php

namespace Nawasara\Vault\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'vault.credential.view',
            'vault.credential.manage',
            'vault.log.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::where('name', 'developer')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}

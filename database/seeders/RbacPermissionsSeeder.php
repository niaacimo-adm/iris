<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Registers the permission codes used by the role / permission screens and
 * grants every permission to the super admin role. Safe to run repeatedly.
 *
 *   php artisan db:seed --class=RbacPermissionsSeeder
 */
class RbacPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            'role.view'         => 'View roles',
            'role.manage'       => 'Create, edit and delete roles',
            'permission.view'   => 'View permissions',
            'permission.manage' => 'Create, edit and delete permissions',
        ];

        foreach ($codes as $code => $description) {
            Permission::firstOrCreate(['code' => $code], ['description' => $description]);
        }

        Role::where('code', Role::SUPER_ADMIN_CODE)
            ->first()
            ?->permissions()
            ->syncWithoutDetaching(Permission::pluck('id')->all());
    }
}

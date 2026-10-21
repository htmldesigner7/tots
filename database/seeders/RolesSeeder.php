<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('cms.permissions') as $slug => [$group, $label]) {
            Permission::updateOrCreate(['slug' => $slug], ['group' => $group, 'label' => $label]);
        }
        $all = Permission::pluck('id', 'slug');
        foreach (config('cms.roles') as $slug => $def) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $def['name'], 'description' => $def['description'], 'is_system' => true]);
            // only set default permissions for newly created roles; keep admin customisations
            if ($role->wasRecentlyCreated) {
                $ids = $def['permissions'] === '*' ? $all->values() : $all->only($def['permissions'])->values();
                $role->permissions()->sync($ids);
            }
        }
    }
}

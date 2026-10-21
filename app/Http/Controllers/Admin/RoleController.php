<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('users.manage');

        return view('admin.roles', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('id')->get(),
            'permissions' => Permission::orderBy('id')->get()->groupBy('group'),
        ]);
    }

    /** Only Super Administrators may change the permission matrix. */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Administrator can change role permissions.');
        $data = $request->validate(['perm' => ['array'], 'perm.*' => ['array'], 'perm.*.*' => ['integer', 'exists:permissions,id']]);
        DB::transaction(function () use ($data) {
            foreach (Role::where('slug', '!=', 'super-admin')->get() as $role) {
                $ids = array_map('intval', $data['perm'][$role->id] ?? []);
                $role->permissions()->sync($ids);
            }
        });
        AuditLogger::log('roles.updated', 'Changed role permissions', null, ['perm' => $data['perm'] ?? []]);

        return back()->with('success', 'Role permissions saved.');
    }
}

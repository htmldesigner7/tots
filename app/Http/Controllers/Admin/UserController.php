<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.index', ['users' => User::with('role')->orderBy('name')->paginate(30)]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->assignableRoles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $this->validated($request, null);
        $user = User::create($data);
        AuditLogger::log('user.created', 'Created user '.$user->email.' ('.$user->role->name.')', $user);

        return redirect()->route('admin.users.index')->with('success', 'User created. Share the password with them securely; they can change it under “My profile”.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', ['user' => $user, 'roles' => $this->assignableRoles()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($user, $data) {
            $user->fill($data);
            // never leave the site without an active Super Administrator
            if ($user->isDirty(['role_id', 'is_active']) && $user->getOriginal('role_id') && Role::find($user->getOriginal('role_id'))?->slug === 'super-admin') {
                $others = User::where('id', '!=', $user->id)->where('is_active', true)->whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))->count();
                $stillSuper = $user->is_active && Role::find($user->role_id)?->slug === 'super-admin';
                abort_if(! $stillSuper && $others === 0, 422, 'There must always be at least one active Super Administrator.');
            }
            $changes = AuditLogger::diff($user);
            $user->save();
            AuditLogger::log('user.updated', 'Updated user '.$user->email, $user, $changes);
        });

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $email = $user->email;
        $user->delete();
        AuditLogger::log('user.deleted', 'Deleted user '.$email);

        return back()->with('success', 'User deleted.');
    }

    private function assignableRoles()
    {
        return Role::orderBy('id')->get()->filter(fn ($r) => auth()->user()->can('assignRole', [User::class, $r]))->values();
    }

    private function validated(Request $request, ?User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user?->id)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $role = Role::findOrFail($data['role_id']);
        abort_unless($request->user()->can('assignRole', [User::class, $role]), 403, 'Only a Super Administrator can assign that role.');
        $data['is_active'] = $request->boolean('is_active');
        if ($user && $user->is($request->user())) {
            $data['is_active'] = true;          // you cannot lock yourself out
            $data['role_id'] = $user->role_id;  // or change your own role
        }
        $data['name'] = strip_tags($data['name']);

        return $data;
    }
}

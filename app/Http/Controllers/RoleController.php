<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->requireView();

        return view('roles.index', [
            'dataScopes' => Role::DATA_SCOPES,
        ]);
    }

    /** JSON feed for the DataTable on the index page. */
    public function data(): JsonResponse
    {
        $this->requireView();
        $canManage = $this->canManage();

        $rows = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('id')
            ->get()
            ->map(function (Role $r) use ($canManage) {
                $deleteHint = match (true) {
                    $r->isProtected()    => __('The super admin role cannot be deleted.'),
                    $r->users_count > 0  => __('Reassign its :count user(s) before deleting.', ['count' => $r->users_count]),
                    default              => null,
                };

                return [
                    'id'                => $r->id,
                    'name'              => $r->name,
                    'code'              => $r->code,
                    'description'       => $r->description,
                    'data_scope'        => $r->data_scope,
                    'data_scope_label'  => Role::DATA_SCOPES[$r->data_scope] ?? (string) $r->data_scope,
                    'require_otp'       => (bool) $r->require_otp_every_login,
                    'permissions_count' => $r->permissions_count,
                    'users_count'       => $r->users_count,
                    'can_manage'        => $canManage,
                    'can_delete'        => $canManage && $deleteHint === null,
                    'delete_hint'       => $deleteHint,
                    'edit_url'          => route('roles.edit', $r),
                    'delete_url'        => route('roles.destroy', $r),
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function create(): View
    {
        $this->requireManage();

        return view('roles.create', [
            'role'             => new Role,
            'permissionGroups' => $this->permissionGroups(),
            'dataScopes'       => Role::DATA_SCOPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireManage();

        $data = $request->validate($this->rules());

        $role = DB::transaction(function () use ($data, $request) {
            $role = new Role(collect($data)->except('permissions')->all());
            $role->require_otp_every_login = $request->boolean('require_otp_every_login');
            $role->save();

            $role->permissions()->sync($data['permissions'] ?? []);

            return $role;
        });

        return redirect()
            ->route('roles.index')
            ->with('status', __('Role :name was created.', ['name' => $role->name]));
    }

    public function edit(Role $role): View
    {
        $this->requireManage();

        return view('roles.edit', [
            'role'             => $role,
            'permissionGroups' => $this->permissionGroups(),
            'dataScopes'       => Role::DATA_SCOPES,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->requireManage();

        $data = $request->validate($this->rules($role));

        DB::transaction(function () use ($data, $request, $role) {
            $attributes = collect($data)->except('permissions');

            // The super admin role keeps its code and scope, and always has every permission.
            if ($role->isProtected()) {
                $attributes = $attributes->except(['code', 'data_scope']);
            }

            $role->fill($attributes->all());
            $role->require_otp_every_login = $request->boolean('require_otp_every_login');
            $role->save();

            $role->permissions()->sync(
                $role->isProtected()
                    ? Permission::query()->pluck('id')->all()
                    : ($data['permissions'] ?? [])
            );
        });

        return redirect()
            ->route('roles.index')
            ->with('status', __('Role :name was updated.', ['name' => $role->name]));
    }

    /** Roles that are still assigned to users, and the super admin role, cannot be deleted. */
    public function destroy(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->requireManage();

        $error = match (true) {
            $role->isProtected()       => __('The super admin role cannot be deleted.'),
            $role->users()->exists()   => __('This role is still assigned to users. Reassign them first.'),
            default                    => null,
        };

        if ($error) {
            return $request->expectsJson()
                ? response()->json(['message' => $error], 422)
                : back()->with('error', $error);
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->delete();
        });

        $message = __('Role :name was deleted.', ['name' => $role->name]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('roles.index')->with('status', $message);
    }

    /* ------------------------------------------------------------------ */

    private function rules(?Role $role = null): array
    {
        return [
            'name'                    => ['required', 'string', 'max:60'],
            'code'                    => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'code')->ignore($role?->id)],
            'description'             => ['nullable', 'string', 'max:255'],
            'data_scope'              => ['required', Rule::in(array_keys(Role::DATA_SCOPES))],
            'require_otp_every_login' => ['sometimes', 'boolean'],
            'permissions'             => ['nullable', 'array'],
            'permissions.*'           => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    /** All permissions grouped by module ("user", "role", ...), for the checkbox picker. */
    private function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('code')
            ->get()
            ->groupBy(fn (Permission $p) => $p->module());
    }

    private function requireView(): void
    {
        abort_unless(auth()->user()?->hasPermission('role.view'), 403);
    }

    /**
     * Managing roles means being able to grant permissions, so on top of the
     * `role.manage` permission it is limited to super admins. This stops a
     * delegated admin from giving their own role extra permissions.
     * Remove the isSuperAdmin() check here if you want to delegate it.
     */
    private function canManage(): bool
    {
        $me = auth()->user();

        return $me && $me->hasPermission('role.manage') && $me->isSuperAdmin();
    }

    private function requireManage(): void
    {
        abort_unless($this->canManage(), 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        $this->requireView();

        return view('permissions.index', [
            'modules' => Permission::query()
                ->pluck('code')
                ->map(fn (string $code) => Permission::moduleOf($code))
                ->unique()
                ->sort()
                ->values(),
        ]);
    }

    /** JSON feed for the DataTable on the index page. */
    public function data(): JsonResponse
    {
        $this->requireView();
        $canManage = $this->canManage();

        $rows = Permission::query()
            ->withCount('roles')
            ->orderBy('code')
            ->get()
            ->map(fn (Permission $p) => [
                'id'          => $p->id,
                'code'        => $p->code,
                'description' => $p->description,
                'module'      => $p->module(),
                'roles_count' => $p->roles_count,
                'is_builtin'  => $p->isProtected(),
                'can_manage'  => $canManage,
                'can_delete'  => $canManage && ! $p->isProtected(),
                'delete_hint' => $p->isProtected()
                    ? __('Built-in permissions are used by the application and cannot be deleted.')
                    : null,
                'edit_url'    => route('permissions.edit', $p),
                'delete_url'  => route('permissions.destroy', $p),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function create(): View
    {
        $this->requireManage();

        return view('permissions.create', ['permission' => new Permission]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireManage();

        $data = $request->validate($this->rules());

        $permission = DB::transaction(function () use ($data) {
            $permission = Permission::create($data);

            // The super admin role always holds every permission.
            Role::query()
                ->where('code', Role::SUPER_ADMIN_CODE)
                ->first()
                ?->permissions()
                ->syncWithoutDetaching([$permission->id]);

            return $permission;
        });

        return redirect()
            ->route('permissions.index')
            ->with('status', __('Permission :code was created.', ['code' => $permission->code]));
    }

    public function edit(Permission $permission): View
    {
        $this->requireManage();

        return view('permissions.edit', ['permission' => $permission]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $this->requireManage();

        $data = $request->validate($this->rules($permission));

        // Built-in codes are referenced from the application code, so only the description may change.
        if ($permission->isProtected()) {
            unset($data['code']);
        }

        $permission->update($data);

        return redirect()
            ->route('permissions.index')
            ->with('status', __('Permission :code was updated.', ['code' => $permission->code]));
    }

    /** Deleting a permission also removes it from every role that had it. */
    public function destroy(Request $request, Permission $permission): RedirectResponse|JsonResponse
    {
        $this->requireManage();

        if ($permission->isProtected()) {
            $message = __('Built-in permissions are used by the application and cannot be deleted.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        DB::transaction(function () use ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        });

        $message = __('Permission :code was deleted.', ['code' => $permission->code]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('permissions.index')->with('status', $message);
    }

    /* ------------------------------------------------------------------ */

    private function rules(?Permission $permission = null): array
    {
        return [
            'code'        => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_.\-]+$/', Rule::unique('permissions', 'code')->ignore($permission?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function requireView(): void
    {
        abort_unless(auth()->user()?->hasPermission('permission.view'), 403);
    }

    /**
     * Creating permissions is part of access control, so on top of the
     * `permission.manage` permission it is limited to super admins.
     * Remove the isSuperAdmin() check here if you want to delegate it.
     */
    private function canManage(): bool
    {
        $me = auth()->user();

        return $me && $me->hasPermission('permission.manage') && $me->isSuperAdmin();
    }

    private function requireManage(): void
    {
        abort_unless($this->canManage(), 403);
    }
}

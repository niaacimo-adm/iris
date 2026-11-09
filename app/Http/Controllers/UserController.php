<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission('user.view');

        return view('users.index', [
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    /** JSON feed for the DataTable on the index page. */
    public function data(Request $request): JsonResponse
    {
        $this->requirePermission('user.view');
        $me = $request->user();

        $rows = User::query()
            ->with(['office', 'role'])
            ->when(! $me->isSuperAdmin(), fn ($q) => $q->where('office_id', $me->office_id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'username'   => $u->username,
                'position'   => $u->position_title,
                'email'      => $u->email,
                'office'     => $u->office?->code,
                'role'       => $u->role?->name,
                'is_active'  => (bool) $u->is_active,
                'last_login' => $u->last_login_at?->format('M d, Y h:i A'),
                'can_manage' => $me->canManageUser($u),
                'is_self'    => $u->is($me),
                'edit_url'   => route('users.edit', $u),
                'status_url' => route('users.status', $u),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('user.manage');
        $me = $request->user();

        return view('users.create', [
            'user'    => new User,
            'offices' => $this->assignableOffices($me),
            'roles'   => $this->assignableRoles($me),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('user.manage');
        $me = $request->user();

        $data = $request->validate($this->rules($me));

        $user = new User(collect($data)->except(['password', 'password_confirmation'])->all());
        $user->password_hash = $data['password'];   // hashed by the model cast
        $user->is_active = $request->boolean('is_active');
        $user->must_change_password = $request->boolean('must_change_password');
        $user->forceFill([
            'email_verified_at'   => now(),
            'password_changed_at' => now(),
            'created_by'          => $me->id,
        ]);
        $user->save();

        return redirect()
            ->route('users.index')
            ->with('status', __('User :name was created.', ['name' => $user->name]));
    }

    public function edit(Request $request, User $user): View
    {
        $me = $request->user();
        $this->requireManage($me, $user);

        return view('users.edit', [
            'user'    => $user,
            'offices' => $this->assignableOffices($me),
            'roles'   => $this->assignableRoles($me),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $this->requireManage($me, $user);

        $data = $request->validate($this->rules($me, $user));

        $user->fill(collect($data)->except(['password', 'password_confirmation'])->all());
        $user->is_active = $request->boolean('is_active');
        $user->must_change_password = $request->boolean('must_change_password');

        if (! empty($data['password'])) {
            $user->password_hash = $data['password'];
            $user->password_changed_at = now();
        }

        // Admins can't lock themselves out or change their own role here.
        if ($user->is($me)) {
            $user->is_active = true;
            $user->role_id = $user->getOriginal('role_id');
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with('status', __('User :name was updated.', ['name' => $user->name]));
    }

    /** Activate / deactivate an account (accounts are never hard-deleted). */
    public function toggleStatus(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $me = $request->user();
        $this->requireManage($me, $user);

        if ($user->is($me)) {
            $message = __('You cannot deactivate your own account.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $message = $user->is_active
            ? __('User :name was activated.', ['name' => $user->name])
            : __('User :name was deactivated.', ['name' => $user->name]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    /* ------------------------------------------------------------------ */

    private function rules(User $me, ?User $user = null): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'middle_name'    => ['nullable', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'username'       => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user?->id)],
            'position_title' => ['nullable', 'string', 'max:100'],
            'phone_number'   => ['nullable', 'string', 'max:20'],
            'office_id'      => ['required', Rule::in($this->assignableOffices($me)->pluck('id')->all())],
            'role_id'        => ['required', Rule::in($this->assignableRoles($me)->pluck('id')->all())],
            'password'       => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    /** Offices the current user may assign accounts to. */
    private function assignableOffices(User $me): Collection
    {
        return Office::query()
            ->where('is_active', true)
            ->when(! $me->isSuperAdmin(), fn ($q) => $q->where('id', $me->office_id))
            ->orderBy('name')
            ->get();
    }

    /** Roles the current user may assign. Only super admins can hand out region-wide roles. */
    private function assignableRoles(User $me): Collection
    {
        return Role::query()
            ->when(! $me->isSuperAdmin(), fn ($q) => $q->where('data_scope', 'office'))
            ->orderBy('id')
            ->get();
    }

    private function requirePermission(string $code): void
    {
        abort_unless(auth()->user()?->hasPermission($code), 403);
    }

    private function requireManage(User $me, User $target): void
    {
        abort_unless($me->canManageUser($target), 403);
    }
}

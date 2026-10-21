<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Store;
use App\Models\User;
use App\Models\UserAdmin;
use App\Services\BranchAccess;
use App\Services\MasterDataService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * User management: members (user store) dan user admins.
 */
class UserManagementController extends Controller
{
    public function members(AdminIndexRequest $request): JsonResponse
    {
        $query = User::query()->where('type', AccountType::Store)->with('customer');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status === 'Active Member' ? 'Active' : 'Inactive');
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $members = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($members->map(fn (User $user) => $this->memberPayload($user))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function updateMember(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStore(), 404);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'nullable', 'in:Active Member,Suspended'],
            'verifiedEmail' => ['sometimes', 'boolean'],
        ]);

        $user->forceFill([
            'name' => $validated['name'] ?? $user->name,
            'phone' => $validated['phone'] ?? $user->phone,
            'status' => isset($validated['status'])
                ? ($validated['status'] === 'Active Member' ? 'Active' : 'Inactive')
                : $user->status,
            'email_verified_at' => array_key_exists('verifiedEmail', $validated)
                ? ($validated['verifiedEmail'] ? now() : null)
                : $user->email_verified_at,
        ])->save();

        return ApiResponse::success($this->memberPayload($user->refresh()->load('customer')));
    }

    public function destroyMember(User $user): JsonResponse
    {
        abort_unless($user->isStore(), 404);
        MasterDataService::assertDeletable($user);
        $user->delete();

        return ApiResponse::success(['id' => (string) $user->id, 'removed' => true]);
    }

    public function userAdmins(AdminIndexRequest $request): JsonResponse
    {
        $query = UserAdmin::query()->with('user');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('admin_code', 'like', $term)
                ->orWhereHas('user', fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $admins = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($admins->map(fn (UserAdmin $admin) => $this->userAdminPayload($admin))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function storeUserAdmin(Request $request): JsonResponse
    {
        $validated = $this->validateUserAdmin($request);

        $admin = DB::transaction(function () use ($request, $validated): UserAdmin {
            $role = $this->resolveRole($request, $validated['role']);
            $user = User::query()->create([
                'email' => mb_strtolower($validated['email']), 'name' => $validated['name'],
                'password' => $validated['password'], 'type' => AccountType::Admin,
                'phone' => $validated['phone'] ?? null, 'status' => $validated['status'] ?? 'Active', 'join_date' => now(),
            ]);
            $user->syncRoles([$role]);

            return UserAdmin::query()->create([
                'user_id' => $user->id,
                'admin_code' => $validated['adminCode'] ?? DocumentNumber::next('user_admin', UserAdmin::query(), 'admin_code', 3),
                'role' => $role->name, 'stores' => array_map('strval', $validated['stores']),
                'status' => $validated['status'] ?? 'Active', 'descriptions' => $validated['descriptions'] ?? null,
            ]);
        });

        return ApiResponse::success($this->userAdminPayload($admin->load('user')), [], 201);
    }

    public function updateUserAdmin(Request $request, UserAdmin $userAdmin): JsonResponse
    {
        abort_if($userAdmin->user_id === $request->user()->id && ($request->input('status') === 'Inactive'), 422, 'Tidak dapat menonaktifkan akun sendiri.');
        abort_if($userAdmin->user?->hasRole('super-admin') && ! $request->user()->hasRole('super-admin'), 403);
        abort_if($userAdmin->user_id === $request->user()->id && $request->filled('role') && $request->input('role') !== $userAdmin->role, 422, 'Tidak dapat mengganti role sendiri.');
        $validated = $this->validateUserAdmin($request, false);

        DB::transaction(function () use ($request, $userAdmin, $validated): void {
            if ($userAdmin->user !== null) {
                $userAdmin->user->forceFill([
                    'email' => $validated['email'] ?? $userAdmin->user->email,
                    'status' => $validated['status'] ?? $userAdmin->user->status,
                    'name' => $validated['name'] ?? $userAdmin->user->name,
                    'phone' => $validated['phone'] ?? $userAdmin->user->phone,
                ])->save();
            }

            if (isset($validated['role'])) {
                $userAdmin->user->syncRoles([$this->resolveRole($request, $validated['role'])]);
            }
            if (($validated['status'] ?? null) === 'Inactive') {
                $userAdmin->user->tokens()->delete();
            }
            $userAdmin->forceFill([
                'role' => $validated['role'] ?? $userAdmin->role,
                'stores' => $validated['stores'] ?? $userAdmin->stores,
                'status' => $validated['status'] ?? $userAdmin->status,
                'descriptions' => $validated['descriptions'] ?? $userAdmin->descriptions,
            ])->save();
        });

        return ApiResponse::success($this->userAdminPayload($userAdmin->refresh()->load('user')));
    }

    public function destroyUserAdmin(UserAdmin $userAdmin): JsonResponse
    {
        abort_if($userAdmin->user_id === auth()->id(), 422, 'Tidak dapat menghapus akun sendiri.');
        abort_if($userAdmin->user?->hasRole('super-admin'), 422, 'Admin utama tidak dapat dihapus.');
        DB::transaction(function () use ($userAdmin): void {
            $userAdmin->user?->forceFill(['status' => 'Inactive'])->save();
            $userAdmin->user?->tokens()->delete();
            $userAdmin->user?->syncRoles([]);
            $userAdmin->delete();
        });

        return ApiResponse::success(['id' => (string) $userAdmin->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUserAdmin(Request $request, bool $creating = true): array
    {
        if ($request->filled('email')) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->route('userAdmin')?->user_id)],
            'password' => [$creating ? 'required' : 'prohibited', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'adminCode' => ['sometimes', 'nullable', 'string', 'max:60', Rule::unique('user_admins', 'admin_code')->ignore($request->route('userAdmin'))],
            'role' => [$creating ? 'required' : 'sometimes', 'string', 'max:60', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'stores' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'stores.*' => ['required', 'integer', 'distinct', Rule::exists('stores', 'id')->where('status', 'Active')],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'descriptions' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        foreach ($validated['stores'] ?? [] as $storeId) {
            BranchAccess::assertAllowed($storeId);
        }

        return $validated;
    }

    private function resolveRole(Request $request, string $name): Role
    {
        abort_if($name === 'super-admin' && ! $request->user()->hasRole('super-admin'), 403);

        return Role::findByName($name, 'web');
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'customerId' => $user->customer?->code,
            'name' => $user->name,
            'email' => $user->email,
            'verifiedEmail' => $user->email_verified_at !== null,
            'subscription' => $user->customer !== null,
            'status' => $user->status === 'Active' ? 'Active Member' : 'Suspended',
            'phone' => $user->phone,
            'avatar' => $user->avatar_path,
            'createdAt' => $user->created_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userAdminPayload(UserAdmin $admin): array
    {
        return [
            'id' => (string) $admin->id,
            'userId' => (string) $admin->user_id,
            'adminCode' => $admin->admin_code,
            'name' => $admin->user?->name,
            'email' => $admin->user?->email,
            'role' => $admin->role,
            'stores' => $admin->stores ?? [],
            'storeNames' => Store::query()->whereIn('id', $admin->stores ?? [])->pluck('store_name')->all(),
            'status' => $admin->status,
            'phone' => $admin->user?->phone,
            'avatar' => $admin->user?->avatar_path,
            'descriptions' => $admin->descriptions,
            'createdAt' => $admin->created_at?->toDateString(),
        ];
    }
}

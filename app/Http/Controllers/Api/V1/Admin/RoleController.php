<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRolesRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => (string) $role->id,
                'name' => $role->name,
                'createdOn' => $role->created_at?->toDateString(),
                'description' => $role->description,
                'permissionsCount' => $role->permissions_count,
            ]);

        return ApiResponse::success($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::query()->create([
            'name' => $request->string('name')->toString(),
            'guard_name' => 'web',
            'description' => $request->input('description'),
        ]);

        if (is_array($request->input('permissions'))) {
            $role->syncPermissions($request->input('permissions'));
        }

        return ApiResponse::success($this->payload($role), [], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role->forceFill([
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
        ])->save();

        if (is_array($request->input('permissions'))) {
            $role->syncPermissions($request->input('permissions'));
        }

        return ApiResponse::success($this->payload($role->refresh()));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'super-admin') {
            return ApiResponse::error('Role super-admin tidak dapat dihapus.', 422);
        }

        $role->delete();

        return ApiResponse::success(['id' => (string) $role->id, 'removed' => true]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission) => [
                'id' => (string) $permission->id,
                'name' => $permission->name,
            ]);

        return ApiResponse::success($permissions);
    }

    public function assignToUser(AssignRolesRequest $request, string $id): JsonResponse
    {
        $user = User::query()->findOrFail((int) $id);
        $user->syncRoles($request->input('roles', []));

        return ApiResponse::success([
            'userId' => (string) $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Role $role): array
    {
        return [
            'id' => (string) $role->id,
            'name' => $role->name,
            'createdOn' => $role->created_at?->toDateString(),
            'description' => $role->description,
            'permissions' => $role->permissions->pluck('name'),
        ];
    }
}

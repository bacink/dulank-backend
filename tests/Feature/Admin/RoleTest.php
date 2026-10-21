<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('manages roles and assigns them to users', function () {
    foreach (['orders.manage', 'sales.manage'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $token = adminToken(adminWithPermissions('users.manage'));

    $created = $this->withToken($token)->postJson('/api/v1/admin/roles', [
        'name' => 'Sales Lead',
        'description' => 'Memimpin tim sales',
        'permissions' => ['orders.manage', 'sales.manage'],
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'Sales Lead')
        ->assertJsonPath('data.permissions.0', 'orders.manage')
        ->assertJsonStructure(['data' => ['id', 'name', 'createdOn', 'description', 'permissions']]);

    $roleId = $created->json('data.id');

    $this->withToken($token)->getJson('/api/v1/admin/roles')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Sales Lead']);

    $this->withToken($token)->getJson('/api/v1/admin/permissions')
        ->assertOk()
        ->assertJsonFragment(['name' => 'orders.manage']);

    $staff = User::factory()->admin()->create();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$staff->id}/roles", ['roles' => ['Sales Lead']])
        ->assertOk()
        ->assertJsonPath('data.roles.0', 'Sales Lead');

    expect($staff->fresh()->hasRole('Sales Lead'))->toBeTrue();

    $this->withToken($token)->putJson("/api/v1/admin/roles/{$roleId}", [
        'name' => 'Sales Supervisor',
        'description' => 'Diperbarui',
        'permissions' => ['orders.manage'],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Sales Supervisor');

    $this->withToken($token)->deleteJson("/api/v1/admin/roles/{$roleId}")->assertConflict();
    $staff->syncRoles([]);
    $this->withToken($token)->deleteJson("/api/v1/admin/roles/{$roleId}")
        ->assertOk()
        ->assertJsonPath('data.removed', true);

    // Role super-admin tidak boleh dihapus.
    $superAdmin = Role::findOrCreate('super-admin', 'web');

    $this->withToken($token)->deleteJson("/api/v1/admin/roles/{$superAdmin->id}")
        ->assertStatus(422);
});

it('requires users.manage permission for role endpoints', function () {
    $this->withToken(adminToken(User::factory()->admin()->create()))
        ->getJson('/api/v1/admin/roles')
        ->assertStatus(403);
});

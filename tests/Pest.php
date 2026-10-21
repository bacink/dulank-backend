<?php

use App\Models\User;
use App\Models\UserAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Admin dengan satu atau lebih permission modul (untuk endpoint /api/v1/admin/*).
 */
function adminWithPermissions(string ...$permissions): User
{
    $admin = User::factory()->admin()->create();
    UserAdmin::query()->create(['user_id' => $admin->id, 'admin_code' => 'TEST-'.$admin->id, 'role' => '', 'stores' => [], 'all_stores' => true, 'status' => 'Active']);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->givePermissionTo($permissions);

    return $admin;
}

/**
 * Token ability admin untuk user tertentu.
 */
function adminToken(User $user): string
{
    return $user->createToken('admin-test', ['admin'])->plainTextToken;
}

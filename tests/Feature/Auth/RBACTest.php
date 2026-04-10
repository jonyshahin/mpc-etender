<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user without project access cannot view tender', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);
    $tender = Tender::factory()->create();

    $this->actingAs($user);

    expect($user->can('view', $tender))->toBeFalse();
});

test('user with project access can view tender', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);
    $project = Project::factory()->create();
    $tender = Tender::factory()->create(['project_id' => $project->id]);

    $user->projects()->attach($project->id, [
        'project_role' => 'project_manager',
    ]);

    $this->actingAs($user);

    expect($user->can('view', $tender))->toBeTrue();
});

test('user with permission can create tender', function () {
    $role = Role::factory()->create();
    $permission = Permission::create([
        'name' => 'Create Tenders',
        'slug' => 'tenders.create',
        'module' => 'tenders',
    ]);
    $role->permissions()->attach($permission->id);

    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user);

    expect($user->can('create', Tender::class))->toBeTrue();
});

test('user without permission cannot create tender', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user);

    expect($user->can('create', Tender::class))->toBeFalse();
});

test('hasPermission returns true for assigned permission', function () {
    $role = Role::factory()->create();
    $permission = Permission::create([
        'name' => 'View Vendors',
        'slug' => 'vendors.view',
        'module' => 'vendors',
    ]);
    $role->permissions()->attach($permission->id);

    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->hasPermission('vendors.view'))->toBeTrue();
    expect($user->hasPermission('vendors.delete'))->toBeFalse();
});

test('isAssignedToProject returns correct result', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);
    $project = Project::factory()->create();

    expect($user->isAssignedToProject($project->id))->toBeFalse();

    $user->projects()->attach($project->id, [
        'project_role' => 'viewer',
    ]);

    expect($user->isAssignedToProject($project->id))->toBeTrue();
});

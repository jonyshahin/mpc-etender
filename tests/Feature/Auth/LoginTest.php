<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can login', function () {
    $role = Role::factory()->create(['slug' => 'super_admin']);
    $user = User::factory()->create(['role_id' => $role->id]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('vendor can login via vendor guard', function () {
    $vendor = Vendor::factory()->create();

    $this->assertGuest('vendor');

    // Manually authenticate via vendor guard to verify guard config works
    auth('vendor')->login($vendor);

    $this->assertAuthenticatedAs($vendor, 'vendor');
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('user with wrong password cannot login', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

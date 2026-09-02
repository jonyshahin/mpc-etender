<?php

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Vendor hides `qualified_by` so the profile page cannot leak which MPC user
 * signed a vendor off. The admin vendor page renders that same person's name
 * through the `qualifiedBy` relation, which serialises under the identical
 * key — the two only coexist because relations are filtered by their own
 * (un-snaked) name. Both halves are pinned here so an upstream change to that
 * order fails loudly rather than either leaking or blanking the admin page.
 */
test('the raw qualified_by column is never serialised', function () {
    $reviewer = User::factory()->create();
    $vendor = Vendor::factory()->qualified()->create(['qualified_by' => $reviewer->id]);

    expect($vendor->fresh()->toArray())->not->toHaveKey('qualified_by');
});

test('the qualifiedBy relation still serialises for the admin page', function () {
    $reviewer = User::factory()->create(['name' => 'Reviewer Name']);
    $vendor = Vendor::factory()->qualified()->create(['qualified_by' => $reviewer->id]);

    $array = $vendor->load('qualifiedBy:id,name')->toArray();

    expect($array)->toHaveKey('qualified_by')
        ->and($array['qualified_by'])->toBeArray()
        ->and($array['qualified_by']['name'])->toBe('Reviewer Name');
});

test('reading the column directly still works for server-side code', function () {
    $reviewer = User::factory()->create();
    $vendor = Vendor::factory()->qualified()->create(['qualified_by' => $reviewer->id]);

    // $hidden governs serialisation only; nothing server-side loses access.
    expect($vendor->fresh()->qualified_by)->toBe($reviewer->id);
});

test('the password is never serialised', function () {
    expect(Vendor::factory()->create()->fresh()->toArray())
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

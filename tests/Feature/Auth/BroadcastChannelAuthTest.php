<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

/**
 * Who may listen on a user's private channel.
 *
 * The starter kit authorised `App.Models.User.{id}` with
 * `(int) $user->id === (int) $id` — written for integer keys. Every key here is
 * a UUID, and casting one to int keeps only its leading digits: production ids
 * `01a042e0-…` and `01a06807-…` both become 1, and any id that opens with a
 * letter becomes 0. Users whose ids shared a leading digit run were each
 * authorised on the other's private channel.
 *
 * Latent rather than live when found — nothing broadcast on the channel yet —
 * but it would have become a leak of whatever was first sent there.
 *
 * The callback is called directly: the `log` broadcaster the suite uses has no
 * HTTP auth handshake to drive.
 */
function userChannelAuthorizer(): Closure
{
    $authorize = Broadcast::driver()->getChannels()->get('App.Models.User.{id}');

    expect($authorize)->toBeInstanceOf(Closure::class);

    return $authorize;
}

test('a user may listen on their own private channel', function () {
    $user = User::factory()->create();

    expect(userChannelAuthorizer()($user, $user->id))->toBeTrue();
});

test('ids sharing a leading digit run do not open each other\'s channel', function () {
    // Both cast to 1 under (int).
    $owner = User::factory()->create(['id' => '01a00000-0000-7000-8000-000000000001']);
    $other = User::factory()->create(['id' => '01b00000-0000-7000-8000-000000000002']);

    expect(userChannelAuthorizer()($other, $owner->id))->toBeFalse();
});

test('ids opening with a letter do not open each other\'s channel', function () {
    // Both cast to 0 under (int).
    $owner = User::factory()->create(['id' => 'a0000000-0000-7000-8000-000000000001']);
    $other = User::factory()->create(['id' => 'b0000000-0000-7000-8000-000000000002']);

    expect(userChannelAuthorizer()($other, $owner->id))->toBeFalse();
});

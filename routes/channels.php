<?php

use Illuminate\Support\Facades\Broadcast;

// Compared as strings, not ints. The starter kit cast both sides to int, which
// is right for auto-increment keys and wrong for these: casting a UUID keeps
// only its leading digits, so `01a042e0-…` and `01a06807-…` both became 1 and
// each user was authorised on the other's private channel. See
// BroadcastChannelAuthTest.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

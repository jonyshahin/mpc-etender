<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Project Timezone
    |--------------------------------------------------------------------------
    |
    | The zone the business operates in. Every date shown to a user, and every
    | wall clock they type into a datetime control, is interpreted in this zone
    | — so a submission deadline reads the same to an evaluator in Mosul and a
    | vendor in Dubai.
    |
    | This is deliberately NOT `app.timezone`, which stays UTC. Storage remains
    | in UTC, which is what the `useCurrent()` column defaults in the migrations
    | already write; moving the application zone would leave those defaults and
    | Eloquent's own writes three hours apart in the same column. None of the
    | server-side date logic is calendar-bound — it is all instant comparison
    | (`isPast()`, `where('submission_deadline', '<=', now())`) — so the zone
    | only ever needed to apply at the display and input boundary.
    |
    */

    'timezone' => env('MPC_TIMEZONE', 'Asia/Baghdad'),

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Admin Account
    |--------------------------------------------------------------------------
    |
    | Password used by AdminUserSeeder when it creates the first super-admin.
    | It is read only on creation — the seeder never rewrites the password of
    | an account that already exists, so re-running `db:seed` on a live
    | environment cannot reset a working admin's credentials.
    |
    | Leave unset outside local/testing and the seeder generates a strong
    | password and prints it once. A seeded production account must never
    | carry a guessable default.
    |
    */

    'admin' => [
        'bootstrap_password' => env('MPC_ADMIN_PASSWORD'),
    ],

];

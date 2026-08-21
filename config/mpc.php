<?php

return [

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

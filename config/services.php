<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | The Decision Engine — scores tender bids and returns a ranking. eTender is
    | its only caller. See INTEGRATION-DESIGN-etender.md.
    |
    | The token is read from the environment ONLY. It lives in eTender's Laravel
    | Cloud environment secrets as DECISION_ENGINE_TOKEN and deliberately has no
    | line in .env.example: it never goes in the repository in any form.
    */
    'decision_engine' => [
        'url' => env('DECISION_ENGINE_URL', 'https://decision-engine-production-uzgtll.laravel.cloud'),
        'token' => env('DECISION_ENGINE_TOKEN'),
        // The engine scales to zero: a cold start is ~1.7 s, so these leave
        // room for a wake-up without letting a dead engine hang a request.
        'connect_timeout' => (int) env('DECISION_ENGINE_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('DECISION_ENGINE_TIMEOUT', 15),
        // How long a batch may sit in queued/processing before the results
        // screen stops waiting and shows it as stuck (design §6).
        'stuck_after_seconds' => (int) env('DECISION_ENGINE_STUCK_AFTER', 120),
        // How long a second "Run" waits for one already talking to the engine
        // before being told an evaluation is in progress.
        'submit_lock_wait_seconds' => (int) env('DECISION_ENGINE_SUBMIT_LOCK_WAIT', 10),
    ],

];

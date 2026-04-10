<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Override Horizon's default authorization to bypass the environment check.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request) {
            $user = $request->user();
            if (! $user) {
                return false;
            }

            return in_array($user->email, [
                'admin@mpc-group.com',
            ]);
        });
    }

    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return in_array($user->email, [
                'admin@mpc-group.com',
            ]);
        });
    }
}

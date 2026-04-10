<?php

namespace App\Providers;

use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function authorization(): void
    {
        // Handled in AppServiceProvider
    }

    protected function gate(): void
    {
        // Handled in AppServiceProvider
    }
}

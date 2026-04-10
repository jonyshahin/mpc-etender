<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\Bid;
use App\Models\EvaluationReport;
use App\Models\EvaluationScore;
use App\Models\Project;
use App\Models\Tender;
use App\Models\Vendor;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\BidPolicy;
use App\Policies\EvaluationReportPolicy;
use App\Policies\EvaluationScorePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TenderPolicy;
use App\Policies\VendorPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPolicies();
        $this->registerDashboardGates();
    }

    protected function registerDashboardGates(): void
    {
        Gate::define('viewPulse', function ($user) {
            return in_array($user->email, [
                'admin@mpc-group.com',
            ]);
        });
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Tender::class, TenderPolicy::class);
        Gate::policy(Bid::class, BidPolicy::class);
        Gate::policy(EvaluationScore::class, EvaluationScorePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(EvaluationReport::class, EvaluationReportPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use App\Http\Responses\LoginResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Schema\Blueprint;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind our custom LoginResponse so Fortify uses it after successful login
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Schema::defaultStringLength(191);

        // Implicitly grant "Super Administrator" and "Saas Owner" roles all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Platform Admin') || $user->hasRole('Saas Owner') ? true : null;
        });

        // Global Auditing Columns Standard macro
        Blueprint::macro('auditColumns', function () {
            $this->timestamp('created_at')->nullable();
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamp('updated_at')->nullable();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->softDeletes(); // adds deleted_at
            $this->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
}

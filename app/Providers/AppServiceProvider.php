<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use App\Http\Responses\LoginResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

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

        // Record last_login, ip_address, location and set login_status = true on every successful login
        Event::listen(Login::class, function (Login $event) {
            $ip = request()->ip();
            $location = 'Local / Unknown';
            
            // Try to resolve location for non-local IPs
            if ($ip !== '127.0.0.1' && $ip !== '::1') {
                try {
                    $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}");
                    if ($response->successful()) {
                        $data = $response->json();
                        if (($data['status'] ?? '') === 'success') {
                            $location = ($data['city'] ?? '') . ', ' . ($data['regionName'] ?? '') . ', ' . ($data['country'] ?? '');
                        }
                    }
                } catch (\Exception $e) {
                    // Fail silently, location stays as 'Local / Unknown'
                }
            }

            $event->user->forceFill([
                'last_login'     => now(),
                'login_status'   => true,
                'ip_address'     => $ip,
                'login_location' => $location,
            ])->saveQuietly();
        });

        // Set login_status = false and clear ip_address when user logs out
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                $event->user->forceFill([
                    'login_status' => false,
                ])->saveQuietly();
            }
        });

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'Invoice' => \App\Models\Invoice::class,
        ]);
    }
}

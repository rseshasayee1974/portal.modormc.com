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
use Illuminate\Validation\Rules\Password;
use App\Services\PlantContextService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind our custom LoginResponse so Fortify uses it after successful login
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);

        // Centralised plant/entity context — resolves session → user default → null.
        // Singleton per request ensures one session read + re-hydration per lifecycle.
        $this->app->singleton(PlantContextService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Schema::defaultStringLength(191);

        // Define global password defaults for "strong" format
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        // Implicitly grant "Super Administrator" and "Saas Owner" roles all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Platform Admin') || $user->hasRole('Saas Owner') ? true : null;
        });

        // Register policy guessing for automatic mapping
        Gate::guessPolicyNamesUsing(function ($modelClass) {
            $modelName = class_basename($modelClass);

            // Option to exclude specific models from automatic policy resolution
            $excludedModels = [
                // 'User',
            ];

            if (in_array($modelName, $excludedModels)) {
                return null;
            }

            $specificPolicy = 'App\\Policies\\' . $modelName . 'Policy';

            if (class_exists($specificPolicy)) {
                return $specificPolicy;
            }

            // Fallback to generic policy if specific policy does not exist
            \App\Policies\GenericPolicy::$currentModelClass = $modelClass;
            return \App\Policies\GenericPolicy::class;
        });

        // Global Auditing Columns Standard macro
        Blueprint::macro('auditColumns', function () {
            $this->timestamp('created_at')->nullable();
            $this->foreignId('created_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $this->timestamp('updated_at')->nullable();
            $this->foreignId('updated_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $this->softDeletes(); // adds deleted_at
            $this->foreignId('deleted_by')->nullable()->constrained('mm_users')->nullOnDelete();
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

        // Register custom ZeptoMail HTTP API mail driver to bypass port blocks on production
        \Illuminate\Support\Facades\Mail::extend('zeptomail_api', function (array $config = []) {
            return new class extends \Symfony\Component\Mailer\Transport\AbstractTransport {
                protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
                {
                    $email = \Symfony\Component\Mime\MessageConverter::toEmail($message->getOriginalMessage());
                    
                    $token = config('mail.mailers.zeptomail_api.token'); 
                    $fromAddress = config('mail.from.address', 'noreply@modormc.com');
                    $fromName = config('mail.from.name', 'ModoRmc');

                    $to = [];
                    foreach ($email->getTo() as $address) {
                        $to[] = [
                            'email_address' => [
                                'address' => $address->getAddress(),
                                'name' => $address->getName() ?: null,
                            ]
                        ];
                    }

                    $cc = [];
                    foreach ($email->getCc() as $address) {
                        $cc[] = [
                            'email_address' => [
                                'address' => $address->getAddress(),
                                'name' => $address->getName() ?: null,
                            ]
                        ];
                    }

                    $bcc = [];
                    foreach ($email->getBcc() as $address) {
                        $bcc[] = [
                            'email_address' => [
                                'address' => $address->getAddress(),
                                'name' => $address->getName() ?: null,
                            ]
                        ];
                    }

                    $payload = [
                        'from' => [
                            'address' => $fromAddress,
                            'name' => $fromName,
                        ],
                        'to' => $to,
                        'subject' => $email->getSubject(),
                        'htmlbody' => $email->getHtmlBody() ?: $email->getTextBody(),
                    ];

                    if (!empty($cc)) {
                        $payload['cc'] = $cc;
                    }
                    if (!empty($bcc)) {
                        $payload['bcc'] = $bcc;
                    }

                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Authorization' => 'Zoho-enczapikey ' . $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->post('https://api.zeptomail.com/v1.1/email', $payload);

                    if (!$response->successful()) {
                        throw new \Exception('ZeptoMail API send failed: ' . $response->body());
                    }
                }

                public function __toString(): string
                {
                    return 'zeptomail_api';
                }
            };
        });
    }
}
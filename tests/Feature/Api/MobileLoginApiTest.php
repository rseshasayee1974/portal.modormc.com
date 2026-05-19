<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileLoginApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('mm_users');

        Schema::create('mm_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('username')->nullable();
            $table->string('password');
            $table->string('api_key')->nullable();
            $table->string('plan')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_otp_enabled')->default(false);
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('last_login')->nullable();
            $table->string('last_visit_page')->nullable();
            $table->boolean('login_status')->default(false);
            $table->string('login_location')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('lockout_until')->nullable();
            $table->string('otp_secret')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->unsignedBigInteger('default_entity_id')->nullable();
            $table->unsignedBigInteger('default_plant_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_email_user_can_login_and_receive_a_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@modomines.com',
            'mobile' => '9876543210',
            'password' => Hash::make('password'),
            'default_entity_id' => 7,
            'default_plant_id' => 11,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo@modomines.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Login Success',
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->username,
                    'email' => $user->email,
                    'mobile' => '9876543210',
                    'default_entity_id' => 7,
                    'default_plant_id' => 11,
                ],
            ])
            ->assertJsonStructure([
                'access_token',
                'token',
                'user' => [
                    'profile_photo_url',
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'email-login',
        ]);

        $user->refresh();

        $this->assertTrue($user->login_status);
        $this->assertNotNull($user->last_login);
        $this->assertSame('127.0.0.1', $user->ip_address);
    }

    public function test_mobile_login_without_otp_sends_otp(): void
    {
        User::factory()->create([
            'mobile' => '6767676767',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'mobile' => '6767676767',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'OTP sent successfully',
                'login_type' => 'mobile_otp',
                'otp_required' => true,
                'mobile' => '6767676767',
                'expires_in' => 300,
            ]);
    }

    public function test_mobile_login_with_valid_otp_logs_in_user(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@modomines.com',
            'mobile' => '6767676767',
            'default_entity_id' => 5,
            'default_plant_id' => 9,
            'is_active' => true,
        ]);

        Cache::put('mobile_otp:6767676767', Hash::make('123456'), now()->addMinutes(5));

        $response = $this->postJson('/api/auth/login', [
            'mobile' => '6767676767',
            'otp' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Login Success',
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'email' => 'demo@modomines.com',
                    'mobile' => '6767676767',
                    'default_entity_id' => 5,
                    'default_plant_id' => 9,
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile-login',
        ]);
    }

    public function test_login_requires_email_or_mobile(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response
            ->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation Error',
            ]);
    }

    public function test_email_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'demo@modomines.com',
            'mobile' => '9876543210',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo@modomines.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'status' => false,
                'message' => 'Invalid email or password',
            ]);
    }

    public function test_mobile_otp_login_rejects_invalid_otp(): void
    {
        User::factory()->create([
            'mobile' => '6767676767',
            'is_active' => true,
        ]);

        Cache::put('mobile_otp:6767676767', Hash::make('123456'), now()->addMinutes(5));

        $response = $this->postJson('/api/auth/login', [
            'mobile' => '6767676767',
            'otp' => '654321',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'The OTP is invalid or expired.',
            ]);
    }

    public function test_authenticated_mobile_user_can_logout(): void
    {
        $user = User::factory()->create([
            'login_status' => true,
        ]);

        $token = $user->createToken('mobile-login');

        $response = $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Logged out successfully',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);

        $this->assertFalse($user->fresh()->login_status);
    }
}

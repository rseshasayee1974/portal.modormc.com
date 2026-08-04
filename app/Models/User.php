<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use App\Helpers\DateTimeHelper;

/**
 * Class User
 * 
 * @property int $id
 * @property string $email
 * @property string $username
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $email_verified_at
 * @property int $is_active
 * @property int $is_otp_enabled
 * @property int $failed_attempts
 * @property Carbon|null $last_login
 * @property string|null $last_visit_page
 * @property bool $login_status
 * @property string|null $login_location
 * @property string|null $ip_address
 * @property Carbon|null $lockout_until
 * @property string|null $otp_secret
 * @property string|null $profile_photo_path
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class User extends Authenticatable implements MustVerifyEmail
{
	use HasFactory, Notifiable, HasApiTokens, HasProfilePhoto, TwoFactorAuthenticatable, HasRoles;
	    use SoftDeletes;
	protected $table = 'mm_users';

	protected $casts = [
		'two_factor_confirmed_at' => 'datetime',
		'email_verified_at' => 'datetime',
		'is_active' => 'boolean',
		'is_otp_enabled' => 'boolean',
		'failed_attempts' => 'int',
		'last_login' => 'datetime',
		'lockout_until' => 'datetime',
		'login_status' => 'boolean',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $hidden = [
		'password',
		'two_factor_secret',
		'remember_token',
		'otp_secret'
	];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

	protected $fillable = [
		'email',
		'mobile',
		'username',
		'password',
		'api_key',
		'plan',
		'two_factor_secret',
		'two_factor_recovery_codes',
		'two_factor_confirmed_at',
		'remember_token',
		'email_verified_at',
		'is_active',
		'is_otp_enabled',
		'failed_attempts',
		'last_login',
		'last_visit_page',
		'login_status',
		'login_location',
		'ip_address',
		'lockout_until',
		'otp_secret',
		'profile_photo_path',
		'default_entity_id',
		'default_plant_id',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function entityUsers()
	{
		return $this->hasMany(EntityUser::class, 'user_id');
	}

	public function defaultEntity()
	{
		return $this->belongsTo(Entity::class, 'default_entity_id');
	}

	public function defaultPlant()
	{
		return $this->belongsTo(Plant::class, 'default_plant_id');
	}

	public function personnel()
	{
		return $this->hasOne(Personnel::class, 'user_id');
	}

	public static function saveWithRelations(array $data)
	{
		return DB::transaction(function () use ($data) {
			if (isset($data['profile_photo_path']) && $data['profile_photo_path'] instanceof \Illuminate\Http\UploadedFile) {
				$path = $data['profile_photo_path']->store('profile_photos', 'public');
				$data['profile_photo_path'] = $path;
			}

            $data['password'] = bcrypt($data['password']);

			$user = self::create($data);

			// disabled temporarily
            // Dispatch Registered event to trigger email verification notification
            event(new \Illuminate\Auth\Events\Registered($user));

			if (isset($data['entity_users']) && is_array($data['entity_users']) && count($data['entity_users']) > 0) {
				foreach ($data['entity_users'] as $eu) {
					$user->entityUsers()->create([
						'entity_id' => $eu['entity_id'],
						'plant_id'  => $eu['plant_id'] ?? null,
						'role_id'   => $eu['role_id'],
					]);
				}
            } elseif (isset($data['role_id']) && !empty($data['role_id'])) {
                $user->entityUsers()->create([
                    'entity_id' => session('active_entity_id'),
                    'plant_id'  => session('active_plant_id'),
                    'role_id'   => $data['role_id'],
                ]);
            }

			// Create Personnel record
			$personnel = Personnel::create([
				'user_id' => $user->id,
				'first_name' => $data['first_name'] ?? $user->username,
				'last_name' => $data['last_name'] ?? null,
				'entity_id' => $user->default_entity_id ?? session('active_entity_id'),
				'plant_id' => $user->default_plant_id ?? session('active_plant_id'),
				'status' => 'active',
			]);

            $contactRecord = \App\Models\Contact::create([
                'plant_id' => $personnel->plant_id,
                'name' => 'null',
                'mobile' => '',
                'contact_type_id' => 1,
                'is_primary' => 1,
                'status' => 1,
            ]);

            $personnel->contacts()->create([
                'contact_id' => (string) $contactRecord->id,
                'contact_type_id' => '1',
                'contact_value' => '',
                'is_primary' => 1,
            ]);

			return $user;
		});
	}

	public function updateWithRelations(array $data)
	{
		return DB::transaction(function () use ($data) {
			if (isset($data['profile_photo_path'])) {
				if ($data['profile_photo_path'] instanceof \Illuminate\Http\UploadedFile) {
					if ($this->profile_photo_path) {
						Storage::disk('public')->delete($this->profile_photo_path);
					}
					$path = $data['profile_photo_path']->store('profile_photos', 'public');
					$data['profile_photo_path'] = $path;
				} elseif ($data['profile_photo_path'] === null) {
                    if ($this->profile_photo_path) {
						Storage::disk('public')->delete($this->profile_photo_path);
					}
                }
			}

            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }

			$this->update($data);

			if (isset($data['entity_users']) && is_array($data['entity_users'])) {
				$this->entityUsers()->forceDelete(); // Fully replace existing roles

				foreach ($data['entity_users'] as $eu) {
					$this->entityUsers()->create([
						'entity_id' => $eu['entity_id'],
						'plant_id'  => $eu['plant_id'] ?? null,
						'role_id'   => $eu['role_id'],
					]);
				}
			}

			return $this;
		});
	}
	public function isSystemAdmin(): bool
	{
		return $this->hasAnyRole([
			'Saas Owner',
			'Platform Admin',
			'Super Admin',
			'Super Administrator'
		]);
	}

    /**
     * Get the codes of all roles assigned to the user.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRoleCode()
    {
        return $this->roles->pluck('code');
    }
}
<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\AuditFields;

/**
 * Class Entity
 * 
 * @property int $id
 * @property int $entity_type
 * @property int|null $parent_id
 * @property string $legal_name
 * @property string|null $alias
 * @property string|null $email
 * @property string|null $url
 * @property string|null $logo_file
 * @property string|null $description
 * @property string|null $time_zone
 * @property int $is_active
 * @property int $is_suspended
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class Entity extends Model
{
	use HasFactory, HasRoles, AuditFields;

	use SoftDeletes;
	protected $table = 'mm_entities';

	protected $casts = [
		'entity_type' => 'int',
		'parent_id' => 'int',
		'is_active' => 'int',
		'is_suspended' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'entity_type',
		'parent_id',
		'legal_name',
		'alias',
		'email',
		'url',
		'api_key',
		'logo_file',
		'description',
		'time_zone',
		'is_active',
		'is_suspended',
		'created_by',
		'updated_by',
		'deleted_by',
        'created_at',
		'updated_at',
		'deleted_at'
	];

    /**
     * Get the addresses for the entity.
     */
    public function addresses()
    {
        return $this->hasMany(EntityAddress::class, 'entity_id');
    }

    /**
     * Get the contacts for the entity.
     */
    public function contacts()
    {
        return $this->hasMany(EntityContact::class, 'entity_id');
    }

    /**
     * Get the bank accounts for the entity.
     */
    public function bankAccounts()
    {
        return $this->hasMany(EntityBankAccount::class, 'entity_id');
    }

    /**
     * Get the taxes for the entity.
     */
    public function taxes()
    {
        return $this->hasMany(EntityTax::class, 'entity_id');
    }

    public function plants()
    {
        return $this->hasMany(Plant::class, 'entity_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(EntitySubscription::class, 'entity_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(EntitySubscription::class, 'entity_id')->latestOfMany('started_at');
    }

    /**
     * Store new entity and its nested relations inside a transaction.
     */
    public static function saveWithRelations(array $data)
    {
        DB::beginTransaction();
        try {
            if (isset($data['logo_file']) && $data['logo_file'] instanceof UploadedFile) {
                $data['logo_file'] = $data['logo_file']->store('entity_logos', 'public');
            }

            $entity = self::create($data);

            if (!empty($data['addresses']) && is_array($data['addresses'])) {
                $entity->addresses()->createMany($data['addresses']);
            }

            if (!empty($data['contacts']) && is_array($data['contacts'])) {
                $entity->contacts()->createMany($data['contacts']);
            }

            if (!empty($data['bank_accounts']) && is_array($data['bank_accounts'])) {
                $entity->bankAccounts()->createMany($data['bank_accounts']);
            }

            if (!empty($data['taxes']) && is_array($data['taxes'])) {
                $entity->taxes()->createMany($data['taxes']);
            }

            DB::commit();
            return $entity->fresh(['addresses', 'contacts', 'bankAccounts', 'taxes']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update existing entity and sync its relations inside a transaction.
     */
    public function updateWithRelations(array $data)
    {
        DB::beginTransaction();
        try {
            if (isset($data['logo_file']) && $data['logo_file'] instanceof UploadedFile) {
                if ($this->logo_file) {
                    Storage::disk('public')->delete($this->logo_file);
                }
                $data['logo_file'] = $data['logo_file']->store('entity_logos', 'public');
            } elseif (array_key_exists('logo_file', $data) && empty($data['logo_file'])) {
                 // Explicit deletion request from frontend
                 if ($this->logo_file) {
                    Storage::disk('public')->delete($this->logo_file);
                }
                $data['logo_file'] = null;
            }

            $this->update($data);

            $this->syncRelations($this->addresses(), $data['addresses'] ?? [], EntityAddress::class);
            $this->syncRelations($this->contacts(), $data['contacts'] ?? [], EntityContact::class);
            $this->syncRelations($this->bankAccounts(), $data['bank_accounts'] ?? [], EntityBankAccount::class);
            $this->syncRelations($this->taxes(), $data['taxes'] ?? [], EntityTax::class);

            DB::commit();
            return $this->fresh(['addresses', 'contacts', 'bankAccounts', 'taxes']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Sync one-to-many relationship helper.
     */
    private function syncRelations($relation, $data, $modelClass)
    {
        if (!is_array($data)) return;

        $existingIds = $relation->pluck('id')->toArray();
        $providedIds = collect($data)->pluck('id')->filter()->toArray();

        $toDelete = array_diff($existingIds, $providedIds);
        
        if ($toDelete) {
            $modelClass::whereIn('id', $toDelete)->delete();
        }

        foreach ($data as $item) {
            if (isset($item['id']) && in_array($item['id'], $existingIds)) {
                $existing = $modelClass::find($item['id']);
                $existing->update($item);
            } else {
                $relation->create($item);
            }
        }
    }

    /**
     * Get active entities that are within the allowed IDs for the current context.
     */
    public static function getAllowedEntities(array $allowedEntityIds)
    {
        return self::whereIn('id', $allowedEntityIds)
            ->where('is_active', 1)
            // ->where('is_suspended', 0)
            // ->whereNull('deleted_at')
            ->orderBy('legal_name', 'asc')
            ->select('id', 'legal_name')
            ->get();
    }
}

<?php

namespace App\Console\Commands;

use App\Models\EntityUser;
use App\Models\Role;
use App\Models\User;
use App\Services\PlantContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DiagnoseUserAccess extends Command
{
    protected $signature = 'access:diagnose {account? : User email or username}';

    protected $description = 'Read-only audit of role grants and workspace assignments; does not print credentials';

    public function handle(): int
    {
        foreach (['mm_roles', 'mm_permissions', 'mm_role_has_permissions', 'mm_users', 'mm_entity_users', 'mm_model_has_roles'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Missing table: {$table}. Check the configured database and migrations.");
                return self::FAILURE;
            }
        }

        $roles = Role::withCount('permissions')->orderBy('name')->get();
        $this->table(['Role ID', 'Role', 'Code', 'Guard', 'Permissions'], $roles->map(fn ($role) => [
            $role->id, $role->name, $role->code, $role->guard_name, $role->permissions_count,
        ])->all());

        if (!$account = $this->argument('account')) {
            $this->info('Pass a username or email to inspect account and workspace role assignments.');
            return self::SUCCESS;
        }

        $user = User::where(fn ($query) => $query->where('email', $account)->orWhere('username', $account))->first();
        if (!$user) {
            $this->error('No active user record matched this username or email.');
            return self::FAILURE;
        }

        $user->load('roles.permissions', 'permissions');
        $this->table(['Account role', 'Guard', 'Permissions'], $user->roles->map(fn ($role) => [
            $role->name, $role->guard_name, $role->permissions->count(),
        ])->all());
        $this->line('Direct account permissions: '.$user->permissions->count());

        $assignments = EntityUser::with('entity', 'plant', 'role.permissions')->where('user_id', $user->id)->get();
        $this->table(['Entity ID', 'Plant ID', 'Role', 'Permissions', 'Entity suspended', 'Plant active'], $assignments->map(fn ($assignment) => [
            $assignment->entity_id,
            $assignment->plant_id ?? 'All plants',
            $assignment->role?->name ?? 'MISSING ROLE',
            $assignment->role?->permissions->count() ?? 0,
            $assignment->entity?->is_suspended ?? 'MISSING ENTITY',
            $assignment->plant_id ? ($assignment->plant?->is_active ?? 'MISSING PLANT') : 'All plants',
        ])->all());

        $context = app(PlantContextService::class);
        $this->line('Visible workspaces: '.($context->hasWorkspaces($user) ? 'yes' : 'no'));
        $defaultPlant = $context->validDefaultPlant($user);
        $this->line('Saved workspace: '.($defaultPlant ? "entity {$defaultPlant->entity_id}, plant {$defaultPlant->id}" : 'none or invalid'));
        if ($defaultPlant) {
            $role = EntityUser::forContext($user->id, $defaultPlant->entity_id, $defaultPlant->id)?->role;
            $this->line('Resolved workspace role: '.($role?->name ?? 'none'));
            $this->line('Resolved workspace permissions: '.($role?->permissions->count() ?? 0));
        }

        if (!$user->roles->count() && !$assignments->count()) {
            $this->warn('This account has no persisted account roles or workspace role assignments.');
        }
        if ($assignments->contains(fn ($assignment) => !$assignment->role || $assignment->role->permissions->isEmpty())) {
            $this->warn('At least one workspace assignment has a missing role or zero explicit permissions.');
        }
        $this->info('Read-only audit completed. No grants, roles, or caches were changed.');

        return self::SUCCESS;
    }
}

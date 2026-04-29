<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Gate;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Gate::before(fn () => true);
    }

    protected function assertModelMatchesDatabase(Model $model, array $except = []): void
    {
        $volatileColumns = array_filter(array_merge([
            $model::CREATED_AT,
            $model::UPDATED_AT,
            method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null,
            'email_verified_at',
            'last_login',
            'lockout_until',
            'two_factor_confirmed_at',
        ], $except));

        $attributes = collect($model->fresh()->getAttributes())
            ->except($volatileColumns)
            ->all();

        $this->assertDatabaseHas($model->getTable(), $attributes);
    }
}

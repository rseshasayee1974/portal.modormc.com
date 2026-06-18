<?php

namespace App\Policies;

class GenericPolicy extends BasePolicy
{
    public static ?string $currentModelClass = null;

    public function __construct()
    {
        if (self::$currentModelClass) {
            $this->modelClass = self::$currentModelClass;
        }
    }
}

<?php

namespace App\Support;


use App\Models\User;

class BatchNumberAccess
{
    public static function allows(?User $user): bool
    {
        return DocumentNumberAccess::allows($user, 'BATCH');
    }
}

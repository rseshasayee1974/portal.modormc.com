<?php

namespace App\Services;

use App\Models\ConcreteGrade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ConcreteGradeService extends BaseService
{
    public function delete(ConcreteGrade $concreteGrade): void
    {
        if ($concreteGrade->mixDesigns()->exists()) {
            throw ValidationException::withMessages([
                'concrete_grade' => 'This concrete grade cannot be deleted because it is linked to one or more mix designs.'
            ]);
        }

        $this->transaction(function () use ($concreteGrade) {
            $concreteGrade->delete();
        });
    }
}

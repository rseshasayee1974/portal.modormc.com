<?php

namespace Database\Factories;

use App\Models\PatronBankAccount;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\BankAccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatronBankAccountFactory extends Factory
{
    protected $model = PatronBankAccount::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'patron_id' => Patron::factory(),
            'bank_account_type' => BankAccountType::factory(),
            'account_holder_name' => $this->faker->name(),
            'account_number' => $this->faker->bankAccountNumber(),
            'bank_name' => $this->faker->company() . ' Bank',
            'branch_name' => $this->faker->city() . ' Branch',
            'ifsc_code' => strtoupper($this->faker->bothify('????0######')),
            'is_primary' => true,
            'status' => true,
            'displayed' => true,
        ];
    }
}

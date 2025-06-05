<?php

namespace Database\Factories;

use App\Models\OperationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperationLogFactory extends Factory
{
    protected $model = OperationLog::class;

    public function definition(): array
    {
        return [
            'category' => $this->faker->randomElement(['frontend', 'backend']),
            'action' => $this->faker->word(),
            'description' => $this->faker->sentence(),
        ];
    }
}

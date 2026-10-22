<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(6)),
            'title' => fake()->sentence(3),
            'status' => Room::STATUS_WAITING,
            'current_question_index' => 0,
            'seconds_per_question' => 30,
            'question_started_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => Room::STATUS_ACTIVE,
            'question_started_at' => now(),
        ]);
    }

    public function finished(): static
    {
        return $this->state(['status' => Room::STATUS_FINISHED]);
    }
}

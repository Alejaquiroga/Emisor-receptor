<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'question_text' => fake()->sentence(),
            'options' => [
                fake()->word(),
                fake()->word(),
                fake()->word(),
                fake()->word(),
            ],
            'correct_option' => fake()->numberBetween(0, 3),
        ];
    }
}

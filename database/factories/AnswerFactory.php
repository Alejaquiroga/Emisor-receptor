<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Participant;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_id' => Participant::factory(),
            'question_id' => Question::factory(),
            'selected_option' => fake()->numberBetween(0, 3),
            'is_correct' => fake()->boolean(),
        ];
    }
}

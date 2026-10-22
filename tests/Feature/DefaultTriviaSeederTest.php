<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Room;
use Database\Seeders\DefaultTriviaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultTriviaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_default_room_with_ten_questions(): void
    {
        $this->seed(DefaultTriviaSeeder::class);

        $room = Room::query()
            ->where('code', DefaultTriviaSeeder::ROOM_CODE)
            ->first();

        $this->assertNotNull($room);
        $this->assertSame('Trivia: códigos de estado HTTP y WebSocket', $room->title);
        $this->assertSame(Room::STATUS_WAITING, $room->status);
        $this->assertSame(0, $room->current_question_index);
        $this->assertSame(10, $room->questions()->count());
        $this->assertTrue($room->adminPasswordMatches(DefaultTriviaSeeder::ADMIN_PIN));

        foreach ($room->questions as $question) {
            $this->assertCount(4, $question->options);
            $this->assertGreaterThanOrEqual(0, $question->correct_option);
            $this->assertLessThanOrEqual(3, $question->correct_option);
        }
    }

    public function test_it_resets_the_default_room_when_seeded_again(): void
    {
        $this->seed(DefaultTriviaSeeder::class);

        $room = Room::query()
            ->where('code', DefaultTriviaSeeder::ROOM_CODE)
            ->firstOrFail();

        $room->participants()->create(['name' => 'Ana']);

        $this->seed(DefaultTriviaSeeder::class);

        $this->assertSame(
            1,
            Room::query()->where('code', DefaultTriviaSeeder::ROOM_CODE)->count(),
        );

        $room = Room::query()
            ->where('code', DefaultTriviaSeeder::ROOM_CODE)
            ->firstOrFail();

        $this->assertSame(10, $room->questions()->count());
        $this->assertSame(0, Participant::query()->count());
    }
}

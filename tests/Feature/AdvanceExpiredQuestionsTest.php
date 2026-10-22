<?php

namespace Tests\Feature;

use App\Events\QuestionBroadcast;
use App\Events\ScoreboardUpdated;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdvanceExpiredQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_advances_an_expired_question(): void
    {
        Event::fake([QuestionBroadcast::class]);

        $room = Room::factory()->active()->create(['seconds_per_question' => 20]);
        $questions = Question::factory()->count(2)->for($room)->create();
        $room->update(['question_started_at' => now()->subSeconds(21)]);

        $this->artisan('trivia:advance-expired')->assertSuccessful();

        $room->refresh();

        $this->assertSame(Room::STATUS_ACTIVE, $room->status);
        $this->assertSame(1, $room->current_question_index);
        $this->assertSame($questions[1]->id, $room->currentQuestion()?->id);
        Event::assertDispatched(QuestionBroadcast::class);
    }

    public function test_it_does_not_advance_before_time_is_up(): void
    {
        Event::fake([QuestionBroadcast::class]);

        $room = Room::factory()->active()->create(['seconds_per_question' => 20]);
        Question::factory()->count(2)->for($room)->create();
        $room->update(['question_started_at' => now()->subSeconds(5)]);

        $this->artisan('trivia:advance-expired')->assertSuccessful();

        $this->assertSame(0, $room->fresh()->current_question_index);
        Event::assertNotDispatched(QuestionBroadcast::class);
    }

    public function test_it_finishes_the_room_after_the_last_question(): void
    {
        Event::fake([ScoreboardUpdated::class]);

        $room = Room::factory()->active()->create(['seconds_per_question' => 20]);
        Question::factory()->for($room)->create();
        $room->update(['question_started_at' => now()->subSeconds(21)]);

        $this->artisan('trivia:advance-expired')->assertSuccessful();

        $this->assertSame(Room::STATUS_FINISHED, $room->fresh()->status);
        Event::assertDispatched(ScoreboardUpdated::class);
    }

    public function test_it_leaves_waiting_rooms_untouched(): void
    {
        $room = Room::factory()->create();
        Question::factory()->for($room)->create();

        $this->artisan('trivia:advance-expired')->assertSuccessful();

        $this->assertSame(Room::STATUS_WAITING, $room->fresh()->status);
        $this->assertSame(0, $room->fresh()->current_question_index);
    }
}

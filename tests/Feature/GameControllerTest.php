<?php

namespace Tests\Feature;

use App\Events\QuestionBroadcast;
use App\Events\ScoreboardUpdated;
use App\Http\Controllers\GameController;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_broadcasts_first_question_and_activates_room(): void
    {
        Event::fake([QuestionBroadcast::class, ScoreboardUpdated::class]);
        $room = Room::factory()->create(['status' => Room::STATUS_WAITING]);
        $question = Question::factory()->for($room)->create(['correct_option' => 1]);

        $response = $this->post(route('game.start', $room));

        $response->assertRedirect();
        $this->assertSame(Room::STATUS_ACTIVE, $room->refresh()->status);
        $this->assertSame(0, $room->current_question_index);
        Event::assertDispatched(
            QuestionBroadcast::class,
            fn (QuestionBroadcast $event) => $event->question->is($question) && $event->room->is($room),
        );
    }

    public function test_cannot_start_room_without_questions(): void
    {
        Event::fake([QuestionBroadcast::class]);
        $room = Room::factory()->create(['status' => Room::STATUS_WAITING]);

        $response = $this->post(route('game.start', $room));

        $response->assertSessionHasErrors('room');
        $this->assertSame(Room::STATUS_WAITING, $room->refresh()->status);
        Event::assertNotDispatched(QuestionBroadcast::class);
    }

    public function test_next_broadcasts_following_question(): void
    {
        Event::fake([QuestionBroadcast::class]);
        $room = Room::factory()->active()->create(['current_question_index' => 0]);
        Question::factory()->for($room)->create();
        $secondQuestion = Question::factory()->for($room)->create();

        $response = $this->post(route('game.next', $room));

        $response->assertRedirect();
        $this->assertSame(1, $room->refresh()->current_question_index);
        Event::assertDispatched(
            QuestionBroadcast::class,
            fn (QuestionBroadcast $event) => $event->question->is($secondQuestion),
        );
    }

    public function test_next_on_last_question_finishes_room(): void
    {
        Event::fake([QuestionBroadcast::class, ScoreboardUpdated::class]);
        $room = Room::factory()->active()->create(['current_question_index' => 0]);
        Question::factory()->for($room)->create();

        $response = $this->post(route('game.next', $room));

        $response->assertRedirect();
        $this->assertSame(Room::STATUS_FINISHED, $room->refresh()->status);
        Event::assertNotDispatched(QuestionBroadcast::class);
        Event::assertDispatched(ScoreboardUpdated::class);
    }

    public function test_correct_answer_awards_points_and_broadcasts_scoreboard(): void
    {
        Event::fake([ScoreboardUpdated::class]);
        $room = Room::factory()->active()->create();
        $question = Question::factory()->for($room)->create(['correct_option' => 2]);
        $participant = Participant::factory()->for($room)->create(['score' => 0]);

        $response = $this->postJson(
            route('game.answer', [$room, $participant, $question]),
            ['selected_option' => 2],
        );

        $response->assertOk()
            ->assertJson([
                'is_correct' => true,
                'correct_option' => 2,
                'points' => GameController::POINTS_PER_CORRECT,
                'score' => GameController::POINTS_PER_CORRECT,
                'position' => 1,
            ]);

        $this->assertSame(GameController::POINTS_PER_CORRECT, $participant->refresh()->score);
        $this->assertDatabaseHas('answers', [
            'participant_id' => $participant->id,
            'question_id' => $question->id,
            'selected_option' => 2,
            'is_correct' => true,
        ]);
        Event::assertDispatched(ScoreboardUpdated::class);
    }

    public function test_wrong_answer_awards_no_points(): void
    {
        Event::fake([ScoreboardUpdated::class]);
        $room = Room::factory()->active()->create();
        $question = Question::factory()->for($room)->create(['correct_option' => 0]);
        $participant = Participant::factory()->for($room)->create(['score' => 50]);

        $response = $this->postJson(
            route('game.answer', [$room, $participant, $question]),
            ['selected_option' => 3],
        );

        $response->assertOk()
            ->assertJson([
                'is_correct' => false,
                'points' => 0,
                'score' => 50,
            ]);

        $this->assertSame(50, $participant->refresh()->score);
        $this->assertDatabaseHas('answers', [
            'participant_id' => $participant->id,
            'question_id' => $question->id,
            'selected_option' => 3,
            'is_correct' => false,
        ]);
    }

    public function test_answer_with_invalid_option_returns_422(): void
    {
        $room = Room::factory()->active()->create();
        $question = Question::factory()->for($room)->create();
        $participant = Participant::factory()->for($room)->create();

        $response = $this->postJson(
            route('game.answer', [$room, $participant, $question]),
            ['selected_option' => 9],
        );

        $response->assertUnprocessable()->assertJsonValidationErrors('selected_option');
        $this->assertDatabaseCount('answers', 0);
    }

    public function test_cannot_answer_twice_the_same_question(): void
    {
        Event::fake([ScoreboardUpdated::class]);
        $room = Room::factory()->active()->create();
        $question = Question::factory()->for($room)->create(['correct_option' => 1]);
        $participant = Participant::factory()->for($room)->create(['score' => 0]);

        $this->postJson(route('game.answer', [$room, $participant, $question]), ['selected_option' => 1])
            ->assertOk();

        $this->postJson(route('game.answer', [$room, $participant, $question]), ['selected_option' => 1])
            ->assertConflict();

        $this->assertDatabaseCount('answers', 1);
        $this->assertSame(100, $participant->refresh()->score);
    }

    public function test_cannot_answer_when_room_is_not_active(): void
    {
        $room = Room::factory()->create(['status' => Room::STATUS_WAITING]);
        $question = Question::factory()->for($room)->create();
        $participant = Participant::factory()->for($room)->create();

        $response = $this->postJson(
            route('game.answer', [$room, $participant, $question]),
            ['selected_option' => 0],
        );

        $response->assertConflict();
        $this->assertDatabaseCount('answers', 0);
    }

    public function test_cannot_answer_question_from_another_room(): void
    {
        $room = Room::factory()->active()->create();
        $otherRoom = Room::factory()->active()->create();
        $question = Question::factory()->for($otherRoom)->create();
        $participant = Participant::factory()->for($room)->create();

        $response = $this->postJson(
            route('game.answer', [$room, $participant, $question]),
            ['selected_option' => 0],
        );

        $response->assertNotFound();
        $this->assertDatabaseCount('answers', 0);
    }

    public function test_play_view_renders_current_question_for_participant(): void
    {
        $room = Room::factory()->active()->create();
        $question = Question::factory()->for($room)->create(['question_text' => '¿Cuál es la capital de Perú?']);
        $participant = Participant::factory()->for($room)->create(['name' => 'Ana']);

        $response = $this->get(route('game.play', [$room, $participant]));

        $response->assertOk()
            ->assertSee('Ana')
            ->assertSee('¿Cuál es la capital de Perú?')
            ->assertSee('option-button', false)
            ->assertSee('answerBaseUrl', false)
            ->assertSee('submitAnswer', false)
            ->assertSee('fetch(', false);
    }

    public function test_play_view_returns_404_for_participant_of_another_room(): void
    {
        $room = Room::factory()->active()->create();
        $otherRoom = Room::factory()->active()->create();
        $participant = Participant::factory()->for($otherRoom)->create();

        $this->get(route('game.play', [$room, $participant]))->assertNotFound();
    }
}

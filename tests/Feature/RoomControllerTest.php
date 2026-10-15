<?php

namespace Tests\Feature;

use App\Events\ScoreboardUpdated;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_view_renders(): void
    {
        $this->get(route('rooms.create'))
            ->assertOk()
            ->assertSee('Crear sala')
            ->assertSee('name="questions[0][question_text]"', false)
            ->assertSee('name="questions[0][options][0]"', false)
            ->assertSee('name="questions[0][correct_option]"', false)
            ->assertSee('question-template', false)
            ->assertSee('add-question', false);
    }

    public function test_join_view_renders(): void
    {
        $this->get(route('rooms.join'))
            ->assertOk()
            ->assertSee('Unirse a una sala');
    }

    public function test_admin_dashboard_renders_room_details(): void
    {
        $room = Room::factory()->create(['title' => 'Trivia de PHP', 'code' => 'ABC123']);
        Question::factory()->for($room)->create(['question_text' => '¿Qué es un trait?']);
        $room->update(['status' => Room::STATUS_ACTIVE, 'current_question_index' => 0]);

        $this->get(route('rooms.show', $room->refresh()))
            ->assertOk()
            ->assertSee('Trivia de PHP')
            ->assertSee('ABC123')
            ->assertSee('¿Qué es un trait?')
            ->assertSee('subscribeToRoom', false);
    }

    public function test_creates_room_with_questions_and_redirects_to_dashboard(): void
    {
        $response = $this->post(route('rooms.store'), [
            'title' => 'Trivia de Laravel',
            'questions' => [
                [
                    'question_text' => '¿Qué es Eloquent?',
                    'options' => ['Un ORM', 'Una CLI', 'Un caché', 'Un router'],
                    'correct_option' => 0,
                ],
                [
                    'question_text' => '¿Qué versión de PHP usa el proyecto?',
                    'options' => ['7.4', '8.0', '8.2', '8.4'],
                    'correct_option' => 3,
                ],
            ],
        ]);

        $room = Room::sole();

        $response->assertRedirect(route('rooms.show', $room));
        $this->assertSame('Trivia de Laravel', $room->title);
        $this->assertSame(Room::STATUS_WAITING, $room->status);
        $this->assertSame(6, strlen($room->code));
        $this->assertSame(2, $room->questions()->count());
        $this->assertDatabaseHas('questions', [
            'room_id' => $room->id,
            'question_text' => '¿Qué es Eloquent?',
            'correct_option' => 0,
        ]);
    }

    public function test_rejects_room_without_questions(): void
    {
        $response = $this->post(route('rooms.store'), ['title' => 'Sala vacía']);

        $response->assertSessionHasErrors('questions');
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_rejects_question_with_wrong_number_of_options(): void
    {
        $response = $this->post(route('rooms.store'), [
            'title' => 'Trivia',
            'questions' => [
                [
                    'question_text' => '¿Pregunta?',
                    'options' => ['Solo una'],
                    'correct_option' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('questions.0.options');
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_joins_existing_room_and_creates_participant(): void
    {
        Event::fake([ScoreboardUpdated::class]);
        $room = Room::factory()->create(['code' => 'ABC123']);

        $response = $this->post(route('rooms.join.submit'), [
            'code' => 'abc123',
            'name' => 'Ana',
        ]);

        $participant = Participant::sole();

        $response->assertRedirect(route('game.play', [$room, $participant]));
        $this->assertSame($room->id, $participant->room_id);
        $this->assertSame('Ana', $participant->name);
        $this->assertSame(0, $participant->score);
        Event::assertDispatched(ScoreboardUpdated::class);
    }

    public function test_reuses_participant_when_name_is_taken_in_room(): void
    {
        Event::fake([ScoreboardUpdated::class]);
        $room = Room::factory()->create(['code' => 'ABC123']);
        $participant = Participant::factory()->for($room)->create(['name' => 'Ana', 'score' => 250]);

        $response = $this->post(route('rooms.join.submit'), [
            'code' => 'ABC123',
            'name' => 'Ana',
        ]);

        $response->assertRedirect(route('game.play', [$room, $participant]));
        $this->assertDatabaseCount('participants', 1);
        Event::assertNotDispatched(ScoreboardUpdated::class);
    }

    public function test_unknown_code_returns_validation_error(): void
    {
        $response = $this->post(route('rooms.join.submit'), [
            'code' => 'ZZZZZZ',
            'name' => 'Ana',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('participants', 0);
    }

    public function test_participants_endpoint_returns_ranking_ordered_by_score(): void
    {
        $room = Room::factory()->create();
        Participant::factory()->for($room)->create(['name' => 'Beto', 'score' => 100]);
        Participant::factory()->for($room)->create(['name' => 'Ana', 'score' => 300]);

        $response = $this->getJson(route('rooms.participants', $room));

        $response->assertOk()
            ->assertJsonPath('status', Room::STATUS_WAITING)
            ->assertJsonPath('ranking.0.name', 'Ana')
            ->assertJsonPath('ranking.0.score', 300)
            ->assertJsonPath('ranking.0.position', 1)
            ->assertJsonPath('ranking.1.name', 'Beto')
            ->assertJsonPath('ranking.1.position', 2);
    }
}

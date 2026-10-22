<?php

namespace Tests\Feature;

use App\Events\ScoreboardUpdated;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_view_renders(): void
    {
        $this->get(route('rooms.create'))
            ->assertOk()
            ->assertSee('Crear sala')
            ->assertSee('name="title"', false)
            ->assertSee('name="admin_password"', false)
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

    public function test_join_view_prefills_code_from_query_string(): void
    {
        $this->get(route('rooms.join', ['code' => 'ABC123']))
            ->assertOk()
            ->assertSee('value="ABC123"', false);
    }

    public function test_admin_dashboard_renders_room_details(): void
    {
        $room = Room::factory()->create([
            'title' => 'Trivia de PHP',
            'code' => 'ABC123',
            'admin_password' => 'secret',
        ]);
        Question::factory()->for($room)->create(['question_text' => '¿Qué es un trait?']);
        $room->update(['status' => Room::STATUS_ACTIVE, 'current_question_index' => 0]);

        $this->withSession([$room->adminSessionKey() => true])
            ->get(route('rooms.show', $room->refresh()))
            ->assertOk()
            ->assertSee('Trivia de PHP')
            ->assertSee('ABC123')
            ->assertSee('¿Qué es un trait?')
            ->assertSee('join-qr', false)
            ->assertSee('subscribeToRoom', false);
    }

    public function test_admin_dashboard_asks_for_pin_without_session(): void
    {
        $room = Room::factory()->create(['admin_password' => 'secret']);

        $this->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('PIN de administrador')
            ->assertSee('Desbloquear panel')
            ->assertDontSee('subscribeToRoom', false);
    }

    public function test_unlock_with_correct_pin_grants_access(): void
    {
        $room = Room::factory()->create(['admin_password' => 'secret']);

        $response = $this->post(route('rooms.unlock', $room), ['password' => 'secret']);

        $response->assertRedirect(route('rooms.show', $room));
        $response->assertSessionHas($room->adminSessionKey(), true);
    }

    public function test_unlock_with_wrong_pin_fails(): void
    {
        $room = Room::factory()->create(['admin_password' => 'secret']);

        $response = $this->post(route('rooms.unlock', $room), ['password' => 'nope']);

        $response->assertSessionHasErrors('password');
        $response->assertSessionMissing($room->adminSessionKey());
    }

    public function test_lock_removes_admin_session(): void
    {
        $room = Room::factory()->create(['admin_password' => 'secret']);

        $this->withSession([$room->adminSessionKey() => true])
            ->post(route('rooms.lock', $room))
            ->assertRedirect(route('rooms.show', $room))
            ->assertSessionMissing($room->adminSessionKey());
    }

    public function test_creates_room_with_questions_and_redirects_to_dashboard(): void
    {
        $response = $this->post(route('rooms.store'), [
            'title' => 'Trivia de Laravel',
            'admin_password' => 'secret',
            'seconds_per_question' => 25,
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
        $response->assertSessionHas($room->adminSessionKey(), true);
        $this->assertSame('Trivia de Laravel', $room->title);
        $this->assertSame(Room::STATUS_WAITING, $room->status);
        $this->assertSame(6, strlen($room->code));
        $this->assertSame(25, $room->seconds_per_question);
        $this->assertTrue(Hash::check('secret', $room->admin_password));
        $this->assertSame(2, $room->questions()->count());
        $this->assertDatabaseHas('questions', [
            'room_id' => $room->id,
            'question_text' => '¿Qué es Eloquent?',
            'correct_option' => 0,
        ]);
    }

    public function test_rejects_room_without_questions(): void
    {
        $response = $this->post(route('rooms.store'), [
            'title' => 'Sala vacía',
            'admin_password' => 'secret',
        ]);

        $response->assertSessionHasErrors('questions');
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_rejects_room_without_admin_password(): void
    {
        $response = $this->post(route('rooms.store'), [
            'title' => 'Sin PIN',
            'questions' => [
                [
                    'question_text' => '¿Pregunta?',
                    'options' => ['A', 'B', 'C', 'D'],
                    'correct_option' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('admin_password');
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_rejects_question_with_wrong_number_of_options(): void
    {
        $response = $this->post(route('rooms.store'), [
            'title' => 'Trivia',
            'admin_password' => 'secret',
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
        $response->assertSessionHas($room->participantSessionKey(), $participant->id);
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
        $response->assertSessionHas($room->participantSessionKey(), $participant->id);
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

    public function test_screen_view_renders_projector_mode(): void
    {
        $room = Room::factory()->create(['title' => 'Trivia final', 'code' => 'SCREEN']);
        Question::factory()->for($room)->create();
        Participant::factory()->for($room)->create(['name' => 'Ana', 'score' => 100]);

        $this->get(route('rooms.screen', $room))
            ->assertOk()
            ->assertSee('Trivia final')
            ->assertSee('SCREEN')
            ->assertSee('Podio final')
            ->assertSee('sound-toggle', false)
            ->assertSee('screen-options', false)
            ->assertSee('join-qr', false);
    }

    public function test_state_endpoint_hides_correct_answer_before_timeout(): void
    {
        $room = Room::factory()->active()->create(['seconds_per_question' => 30]);
        $question = Question::factory()->for($room)->create([
            'question_text' => '¿Cuál es la capital de Perú?',
            'options' => ['Quito', 'Lima', 'Bogotá', 'Santiago'],
            'correct_option' => 1,
        ]);
        $participant = Participant::factory()->for($room)->create();
        $participant->answers()->create([
            'question_id' => $question->id,
            'selected_option' => 1,
            'is_correct' => true,
        ]);

        $response = $this->getJson(route('rooms.state', $room));

        $response->assertOk()
            ->assertJsonPath('status', Room::STATUS_ACTIVE)
            ->assertJsonPath('question.question_text', '¿Cuál es la capital de Perú?')
            ->assertJsonPath('revealed', false)
            ->assertJsonPath('correct_option', null)
            ->assertJsonPath('answered_count', 1)
            ->assertJsonPath('option_counts', [0, 1, 0, 0]);
    }

    public function test_state_endpoint_reveals_answer_after_timeout(): void
    {
        $room = Room::factory()->active()->create(['seconds_per_question' => 10]);
        $question = Question::factory()->for($room)->create([
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 2,
        ]);
        $participant = Participant::factory()->for($room)->create();
        $participant->answers()->create([
            'question_id' => $question->id,
            'selected_option' => 2,
            'is_correct' => true,
        ]);

        $this->travel(11)->seconds();

        $response = $this->getJson(route('rooms.state', $room));

        $response->assertOk()
            ->assertJsonPath('revealed', true)
            ->assertJsonPath('correct_option', 2)
            ->assertJsonPath('answered_count', 1)
            ->assertJsonPath('option_counts.2', 1);
    }

    public function test_state_endpoint_returns_waiting_room_without_question(): void
    {
        $room = Room::factory()->create();

        $this->getJson(route('rooms.state', $room))
            ->assertOk()
            ->assertJsonPath('status', Room::STATUS_WAITING)
            ->assertJsonPath('question', null);
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

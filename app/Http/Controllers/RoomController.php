<?php

namespace App\Http\Controllers;

use App\Events\QuestionBroadcast;
use App\Events\ScoreboardUpdated;
use App\Http\Requests\StoreRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class RoomController extends Controller
{
    public function create(): View
    {
        return view('rooms.create');
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $room = DB::transaction(function () use ($data): Room {
            $room = Room::create([
                'code' => $this->generateUniqueCode(),
                'title' => $data['title'],
                'admin_password' => $data['admin_password'],
                'status' => Room::STATUS_WAITING,
                'current_question_index' => 0,
                'seconds_per_question' => $data['seconds_per_question'],
            ]);

            foreach ($data['questions'] as $question) {
                $room->questions()->create([
                    'question_text' => $question['question_text'],
                    'options' => array_values($question['options']),
                    'correct_option' => $question['correct_option'],
                ]);
            }

            return $room;
        });

        session()->put($room->adminSessionKey(), true);

        return redirect()
            ->route('rooms.show', $room)
            ->with('ok', "Sala creada. Comparte el código {$room->code} o el enlace de unión para que se unan los participantes.");
    }

    public function join(Request $request): View
    {
        return view('rooms.join', [
            'code' => $request->query('code'),
        ]);
    }

    public function joinRoom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'name' => ['required', 'string', 'max:40'],
        ]);

        $room = Room::where('code', Str::upper($data['code']))->first();

        if ($room === null) {
            return back()
                ->withErrors(['code' => 'No existe ninguna sala con ese código.'])
                ->onlyInput('code');
        }

        $participant = $room->participants()
            ->where('name', $data['name'])
            ->first();

        if ($participant === null) {
            $participant = $room->participants()->create(['name' => $data['name']]);

            // The first participant starts the game automatically; subsequent
            // joins only update the scoreboard.
            if ($room->participants()->count() === 1) {
                $question = $room->startGame();

                if ($question !== null) {
                    $this->broadcastSafely(new QuestionBroadcast($room, $question));
                }
            }

            $this->broadcastSafely(new ScoreboardUpdated($room));
        }

        session()->put($room->participantSessionKey(), $participant->id);

        return redirect()->route('game.play', [$room, $participant]);
    }

    public function show(Room $room): View
    {
        if (! session()->get($room->adminSessionKey())) {
            return view('rooms.unlock', [
                'room' => $room,
            ]);
        }

        return view('rooms.admin', [
            'room' => $room,
            'ranking' => $room->ranking(),
            'currentQuestion' => $room->currentQuestion(),
            'joinPath' => route('rooms.join', ['code' => $room->code], false),
        ]);
    }

    public function unlock(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! $room->adminPasswordMatches($data['password'])) {
            return back()->withErrors(['password' => 'El PIN no es correcto.']);
        }

        session()->put($room->adminSessionKey(), true);

        return redirect()
            ->route('rooms.show', $room)
            ->with('ok', 'Panel de administración desbloqueado.');
    }

    public function lock(Room $room): RedirectResponse
    {
        session()->forget($room->adminSessionKey());

        return redirect()
            ->route('rooms.show', $room)
            ->with('ok', 'Cerraste la sesión de administrador en este dispositivo.');
    }

    public function participants(Room $room): JsonResponse
    {
        return response()->json([
            'room_id' => $room->id,
            'code' => $room->code,
            'status' => $room->status,
            'ranking' => $room->ranking(),
        ]);
    }

    /**
     * Full-screen view for the projector: lobby, question, countdown and podium.
     */
    public function screen(Room $room): View
    {
        return view('rooms.screen', [
            'room' => $room,
            'joinPath' => route('rooms.join', ['code' => $room->code], false),
            'ranking' => $room->ranking(),
        ]);
    }

    /**
     * Compact snapshot used by the projector to recover from missed events.
     */
    public function state(Room $room): JsonResponse
    {
        $question = $room->currentQuestion();
        $stats = $question === null
            ? ['counts' => [], 'total' => 0]
            : $room->answerStats($question);

        $revealed = $question !== null
            && ($room->status === Room::STATUS_FINISHED || $room->isQuestionExpired());

        return response()->json([
            'room_id' => $room->id,
            'title' => $room->title,
            'code' => $room->code,
            'status' => $room->status,
            'question_index' => $room->current_question_index,
            'total_questions' => $room->totalQuestions(),
            'seconds_per_question' => $room->seconds_per_question,
            'ends_at' => $room->questionEndsAt()?->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'participants_count' => $room->participants()->count(),
            'answered_count' => $stats['total'],
            'option_counts' => $stats['counts'],
            'revealed' => $revealed,
            'correct_option' => $revealed ? $question->correct_option : null,
            'question' => $question === null ? null : [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => $question->options,
            ],
            'ranking' => $room->ranking(),
        ]);
    }

    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = Str::upper(Str::random(6));

            if (Room::where('code', $code)->doesntExist()) {
                return $code;
            }
        }

        throw new RuntimeException('No se pudo generar un código de sala único.');
    }
}

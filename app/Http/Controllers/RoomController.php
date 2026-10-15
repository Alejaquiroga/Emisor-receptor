<?php

namespace App\Http\Controllers;

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
                'status' => Room::STATUS_WAITING,
                'current_question_index' => 0,
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

        return redirect()
            ->route('rooms.show', $room)
            ->with('ok', "Sala creada. Comparte el código {$room->code} para que se unan los participantes.");
    }

    public function join(): View
    {
        return view('rooms.join');
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

            broadcast(new ScoreboardUpdated($room));
        }

        return redirect()->route('game.play', [$room, $participant]);
    }

    public function show(Room $room): View
    {
        return view('rooms.admin', [
            'room' => $room,
            'ranking' => $room->ranking(),
            'currentQuestion' => $room->currentQuestion(),
        ]);
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

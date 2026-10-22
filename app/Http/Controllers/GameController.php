<?php

namespace App\Http\Controllers;

use App\Events\QuestionBroadcast;
use App\Events\ScoreboardUpdated;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GameController extends Controller
{
    public const POINTS_PER_CORRECT = 100;

    public const MAX_SPEED_BONUS = 100;

    public function play(Room $room, Participant $participant): View
    {
        abort_unless($participant->room_id === $room->id, 404);

        $currentQuestion = $room->currentQuestion();

        $currentAnswer = $currentQuestion === null
            ? null
            : $participant->answers()->where('question_id', $currentQuestion->id)->first();

        return view('rooms.play', [
            'room' => $room,
            'participant' => $participant,
            'currentQuestion' => $currentQuestion,
            'currentAnswer' => $currentAnswer,
            'ranking' => $room->ranking(),
            'position' => $participant->position(),
        ]);
    }

    public function start(Room $room): RedirectResponse
    {
        if ($room->questions()->count() === 0) {
            return back()->withErrors(['room' => 'La sala no tiene preguntas para jugar.']);
        }

        if ($room->status !== Room::STATUS_WAITING) {
            return back()->withErrors(['room' => 'La trivia ya fue iniciada.']);
        }

        $room->update([
            'status' => Room::STATUS_ACTIVE,
            'current_question_index' => 0,
            'question_started_at' => now(),
        ]);

        $question = $room->currentQuestion();

        if ($question === null) {
            return back()->withErrors(['room' => 'No se pudo cargar la primera pregunta.']);
        }

        $this->broadcastSafely(new QuestionBroadcast($room, $question));
        $this->broadcastSafely(new ScoreboardUpdated($room));

        return back()->with('ok', 'Trivia iniciada.');
    }

    public function next(Room $room): RedirectResponse
    {
        if ($room->status !== Room::STATUS_ACTIVE) {
            return back()->withErrors(['room' => 'La trivia no está en curso.']);
        }

        $nextIndex = $room->current_question_index + 1;

        if ($nextIndex >= $room->totalQuestions()) {
            $room->update(['status' => Room::STATUS_FINISHED]);

            $this->broadcastSafely(new ScoreboardUpdated($room));

            return back()->with('ok', 'Trivia finalizada. Podio definitivo publicado.');
        }

        $room->update([
            'current_question_index' => $nextIndex,
            'question_started_at' => now(),
        ]);

        $question = $room->currentQuestion();

        if ($question === null) {
            return back()->withErrors(['room' => 'No se pudo cargar la siguiente pregunta.']);
        }

        $this->broadcastSafely(new QuestionBroadcast($room, $question));

        return back()->with('ok', 'Siguiente pregunta publicada.');
    }

    public function answer(Request $request, Room $room, Participant $participant, Question $question): JsonResponse
    {
        $data = $request->validate([
            'selected_option' => ['required', 'integer', 'between:0,3'],
        ]);

        abort_unless($participant->room_id === $room->id, 404);
        abort_unless($question->room_id === $room->id, 404);

        if ($room->status !== Room::STATUS_ACTIVE) {
            return response()->json(['message' => 'La trivia no está activa.'], 409);
        }

        $currentQuestion = $room->currentQuestion();

        if ($currentQuestion === null || $currentQuestion->id !== $question->id) {
            return response()->json(['message' => 'Esta pregunta ya no está activa.'], 409);
        }

        if ($room->isQuestionExpired()) {
            return response()->json(['message' => 'Se acabó el tiempo para esta pregunta.'], 409);
        }

        if ($participant->hasAnswered($question)) {
            return response()->json(['message' => 'Ya respondiste esta pregunta.'], 409);
        }

        $isCorrect = $data['selected_option'] === $question->correct_option;
        $points = $isCorrect ? $this->pointsForCorrectAnswer($room) : 0;

        try {
            DB::transaction(function () use ($participant, $question, $data, $isCorrect, $points): void {
                $participant->answers()->create([
                    'question_id' => $question->id,
                    'selected_option' => $data['selected_option'],
                    'is_correct' => $isCorrect,
                ]);

                if ($isCorrect) {
                    $participant->increment('score', $points);
                }
            });
        } catch (QueryException) {
            return response()->json(['message' => 'Ya respondiste esta pregunta.'], 409);
        }

        $participant->refresh();

        $this->broadcastSafely(new ScoreboardUpdated($room));

        return response()->json([
            'is_correct' => $isCorrect,
            'correct_option' => $question->correct_option,
            'points' => $points,
            'score' => $participant->score,
            'position' => $participant->position(),
        ]);
    }

    /**
     * Correct answers are worth a base amount plus a bonus that shrinks as the
     * question window runs out: answering earlier is worth more.
     */
    private function pointsForCorrectAnswer(Room $room): int
    {
        $endsAt = $room->questionEndsAt();

        if ($endsAt === null) {
            return self::POINTS_PER_CORRECT;
        }

        $totalMs = max(1000, $room->seconds_per_question * 1000);
        $remainingMs = max(0, $endsAt->getTimestampMs() - now()->getTimestampMs());
        $ratio = min(1, $remainingMs / $totalMs);

        return self::POINTS_PER_CORRECT + (int) round(self::MAX_SPEED_BONUS * $ratio);
    }
}

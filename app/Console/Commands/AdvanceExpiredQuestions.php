<?php

namespace App\Console\Commands;

use App\Events\QuestionBroadcast;
use App\Events\ScoreboardUpdated;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdvanceExpiredQuestions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trivia:advance-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Avanza la pregunta actual cuando vence el tiempo o todos los participantes responden.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Room::query()
            ->where('status', Room::STATUS_ACTIVE)
            ->whereNotNull('question_started_at')
            ->get()
            ->each(function (Room $room): void {
                $room->refresh();

                $question = $room->currentQuestion();

                if ($question === null) {
                    return;
                }

                if (! $room->isQuestionExpired() && ! $room->allParticipantsAnswered($question)) {
                    return;
                }

                $room->advanceQuestion();

                $this->broadcastRoom($room);
            });

        return self::SUCCESS;
    }

    private function broadcastRoom(Room $room): void
    {
        if ($room->status === Room::STATUS_FINISHED) {
            $this->broadcastSafely(new ScoreboardUpdated($room));

            return;
        }

        $question = $room->currentQuestion();

        if ($question !== null) {
            $this->broadcastSafely(new QuestionBroadcast($room, $question));
        }
    }

    private function broadcastSafely(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('No se pudo emitir el cambio automático de pregunta.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

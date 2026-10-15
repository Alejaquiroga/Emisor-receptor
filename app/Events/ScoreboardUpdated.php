<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ScoreboardUpdated implements ShouldBroadcastNow
{
    public function __construct(public Room $room) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->room->id,
            'status' => $this->room->status,
            'question_index' => $this->room->current_question_index,
            'total_questions' => $this->room->totalQuestions(),
            'ranking' => $this->room->ranking(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('room.'.$this->room->id)];
    }

    public function broadcastAs(): string
    {
        return 'ScoreboardUpdated';
    }
}

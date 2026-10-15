<?php

namespace App\Events;

use App\Models\Question;
use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class QuestionBroadcast implements ShouldBroadcastNow
{
    public function __construct(
        public Room $room,
        public Question $question,
    ) {}

    /**
     * The correct option is never included: participants must not see it.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->room->id,
            'status' => $this->room->status,
            'question_index' => $this->room->current_question_index,
            'total_questions' => $this->room->totalQuestions(),
            'question' => [
                'id' => $this->question->id,
                'question_text' => $this->question->question_text,
                'options' => $this->question->options,
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('room.'.$this->room->id)];
    }

    public function broadcastAs(): string
    {
        return 'QuestionBroadcast';
    }
}

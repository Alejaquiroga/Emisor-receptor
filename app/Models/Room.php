<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Room extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_ACTIVE,
        self::STATUS_FINISHED,
    ];

    protected $fillable = [
        'code',
        'title',
        'status',
        'current_question_index',
    ];

    protected function casts(): array
    {
        return [
            'current_question_index' => 'integer',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function answers(): HasManyThrough
    {
        return $this->hasManyThrough(Answer::class, Participant::class);
    }

    public function currentQuestion(): ?Question
    {
        if ($this->status === self::STATUS_WAITING) {
            return null;
        }

        return $this->questions()
            ->skip($this->current_question_index)
            ->first();
    }

    public function totalQuestions(): int
    {
        return $this->questions()->count();
    }

    /**
     * Participants ordered for the podium: highest score first, name as tie-breaker.
     *
     * @return array<int, array{id: int, name: string, score: int, position: int}>
     */
    public function ranking(): array
    {
        return $this->participants()
            ->orderByDesc('score')
            ->orderBy('name')
            ->get()
            ->map(fn (Participant $participant, int $index): array => [
                'id' => $participant->id,
                'name' => $participant->name,
                'score' => $participant->score,
                'position' => $index + 1,
            ])
            ->all();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'name',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'room_id' => 'integer',
            'score' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function hasAnswered(Question $question): bool
    {
        return $this->answers()->where('question_id', $question->id)->exists();
    }

    /**
     * Current position in the room podium, starting at 1.
     */
    public function position(): int
    {
        return $this->room->participants()
            ->where(function ($query) {
                $query->where('score', '>', $this->score)
                    ->orWhere(function ($tie) {
                        $tie->where('score', $this->score)
                            ->where('name', '<', $this->name);
                    });
            })
            ->count() + 1;
    }
}

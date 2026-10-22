<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Hash;

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

    /**
     * Medals for the top three places, in order.
     *
     * @var array<int, string>
     */
    private const MEDALS = ['🥇', '🥈', '🥉'];

    /**
     * @var array<int, string>
     */
    private const MEDAL_LABELS = ['Oro', 'Plata', 'Bronce'];

    protected $fillable = [
        'code',
        'title',
        'admin_password',
        'status',
        'current_question_index',
        'seconds_per_question',
        'question_started_at',
    ];

    protected $hidden = [
        'admin_password',
    ];

    protected function casts(): array
    {
        return [
            'admin_password' => 'hashed',
            'current_question_index' => 'integer',
            'seconds_per_question' => 'integer',
            'question_started_at' => 'datetime',
        ];
    }

    /**
     * Session key that marks the current visitor as this room's administrator.
     */
    public function adminSessionKey(): string
    {
        return "room.{$this->id}.admin";
    }

    /**
     * Session key that binds the current visitor to their participant row.
     */
    public function participantSessionKey(): string
    {
        return "room.{$this->id}.participant";
    }

    public function hasAdminPassword(): bool
    {
        return filled($this->admin_password);
    }

    public function adminPasswordMatches(string $password): bool
    {
        return $this->hasAdminPassword() && Hash::check($password, $this->admin_password);
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
     * Start the game when the room is waiting and has questions.
     *
     * @return Question|null The first question, or null when the room cannot start.
     */
    public function startGame(): ?Question
    {
        if ($this->status !== self::STATUS_WAITING || $this->questions()->count() === 0) {
            return null;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'current_question_index' => 0,
            'question_started_at' => now(),
        ]);

        return $this->currentQuestion();
    }

    /**
     * Move to the next question, or finish the room when there are no more.
     *
     * @return bool Whether the room changed state.
     */
    public function advanceQuestion(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $nextIndex = $this->current_question_index + 1;

        if ($nextIndex >= $this->totalQuestions()) {
            $this->update(['status' => self::STATUS_FINISHED]);

            return true;
        }

        $this->update([
            'current_question_index' => $nextIndex,
            'question_started_at' => now(),
        ]);

        return true;
    }

    /**
     * Moment when the current question stops accepting answers.
     */
    public function questionEndsAt(): ?CarbonInterface
    {
        if ($this->question_started_at === null) {
            return null;
        }

        return $this->question_started_at->copy()->addSeconds($this->seconds_per_question);
    }

    /**
     * Whether the current question window has already closed.
     */
    public function isQuestionExpired(): bool
    {
        $endsAt = $this->questionEndsAt();

        if ($this->status !== self::STATUS_ACTIVE || $endsAt === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo($endsAt);
    }

    /**
     * Counts of answers per option for a question, plus the total.
     *
     * @return array{counts: array<int, int>, total: int}
     */
    public function answerStats(Question $question): array
    {
        $counts = array_fill(0, max(1, count($question->options)), 0);

        $rows = Answer::query()
            ->where('question_id', $question->id)
            ->whereIn('participant_id', $this->participants()->select('id'))
            ->selectRaw('selected_option, count(*) as aggregate')
            ->groupBy('selected_option')
            ->pluck('aggregate', 'selected_option');

        foreach ($rows as $option => $total) {
            $option = (int) $option;

            if (array_key_exists($option, $counts)) {
                $counts[$option] = (int) $total;
            }
        }

        return [
            'counts' => $counts,
            'total' => array_sum($counts),
        ];
    }

    /**
     * Participants ordered for the podium: highest score first, name as tie-breaker.
     *
     * Medals are awarded to the top three places, limited by the number of
     * participants: one participant gets gold, two get gold and silver, and
     * three or more get the full podium.
     *
     * @return array<int, array{id: int, name: string, score: int, position: int, medal: string|null, medal_label: string|null}>
     */
    public function ranking(): array
    {
        $participants = $this->participants()
            ->orderByDesc('score')
            ->orderBy('name')
            ->get();

        $medalCount = min(count(self::MEDALS), $participants->count());

        return $participants
            ->map(function (Participant $participant, int $index) use ($medalCount): array {
                $medal = $index < $medalCount ? self::MEDALS[$index] : null;

                return [
                    'id' => $participant->id,
                    'name' => $participant->name,
                    'score' => $participant->score,
                    'position' => $index + 1,
                    'medal' => $medal,
                    'medal_label' => $medal === null ? null : self::MEDAL_LABELS[$index],
                ];
            })
            ->all();
    }
}

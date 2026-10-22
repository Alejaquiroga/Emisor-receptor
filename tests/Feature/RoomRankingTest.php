<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_participant_gets_only_gold(): void
    {
        $room = Room::factory()->create();
        Participant::factory()->for($room)->create(['name' => 'Ana', 'score' => 100]);

        $ranking = $room->ranking();

        $this->assertCount(1, $ranking);
        $this->assertSame('🥇', $ranking[0]['medal']);
        $this->assertSame('Oro', $ranking[0]['medal_label']);
    }

    public function test_two_participants_get_gold_and_silver(): void
    {
        $room = Room::factory()->create();
        Participant::factory()->for($room)->create(['name' => 'Ana', 'score' => 300]);
        Participant::factory()->for($room)->create(['name' => 'Luis', 'score' => 200]);

        $ranking = $room->ranking();

        $this->assertSame(['🥇', '🥈'], array_column($ranking, 'medal'));
        $this->assertSame(['Oro', 'Plata'], array_column($ranking, 'medal_label'));
    }

    public function test_three_or_more_participants_get_the_full_podium(): void
    {
        $room = Room::factory()->create();

        foreach ([400, 300, 200, 100] as $index => $score) {
            Participant::factory()->for($room)->create([
                'name' => 'Jugador '.($index + 1),
                'score' => $score,
            ]);
        }

        $ranking = $room->ranking();

        $this->assertSame(['🥇', '🥈', '🥉', null], array_column($ranking, 'medal'));
        $this->assertSame(['Oro', 'Plata', 'Bronce', null], array_column($ranking, 'medal_label'));
    }

    public function test_ranking_is_ordered_by_score_and_uses_name_as_tie_breaker(): void
    {
        $room = Room::factory()->create();
        Participant::factory()->for($room)->create(['name' => 'Zoe', 'score' => 100]);
        Participant::factory()->for($room)->create(['name' => 'Ana', 'score' => 100]);
        Participant::factory()->for($room)->create(['name' => 'Luis', 'score' => 300]);

        $ranking = $room->ranking();

        $this->assertSame(['Luis', 'Ana', 'Zoe'], array_column($ranking, 'name'));
        $this->assertSame([1, 2, 3], array_column($ranking, 'position'));
    }
}

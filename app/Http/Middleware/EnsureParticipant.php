<?php

namespace App\Http\Middleware;

use App\Models\Participant;
use App\Models\Room;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureParticipant
{
    /**
     * Only let the visitor play as the participant bound to this session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $room = $request->route('room');
        $participant = $request->route('participant');

        abort_unless($room instanceof Room && $participant instanceof Participant, 404);

        abort_unless($participant->room_id === $room->id, 404);

        abort_unless(
            $request->session()->get($room->participantSessionKey()) === $participant->id,
            403,
        );

        return $next($request);
    }
}

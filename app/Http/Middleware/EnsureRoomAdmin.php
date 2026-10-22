<?php

namespace App\Http\Middleware;

use App\Models\Room;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoomAdmin
{
    /**
     * Only let the visitor manage a room when they unlocked it in this session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $room = $request->route('room');

        abort_unless($room instanceof Room, 404);

        abort_unless(
            $request->session()->get($room->adminSessionKey()) === true,
            403,
        );

        return $next($request);
    }
}

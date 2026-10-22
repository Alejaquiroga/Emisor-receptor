<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Throwable;

abstract class Controller
{
    /**
     * Broadcast an event without letting a websocket outage break the game.
     * The projector and players degrade gracefully instead of getting a 500.
     */
    protected function broadcastSafely(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('No se pudo emitir el evento en tiempo real.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

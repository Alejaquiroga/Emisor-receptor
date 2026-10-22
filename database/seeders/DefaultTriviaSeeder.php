<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class DefaultTriviaSeeder extends Seeder
{
    /**
     * Fixed code so the room can be shared and reached at a predictable URL.
     */
    public const ROOM_CODE = 'HTTPWS';

    public const ADMIN_PIN = 'demo';

    /**
     * Create (or reset) the room that ships with the app and is ready to play.
     */
    public function run(): void
    {
        Room::where('code', self::ROOM_CODE)->delete();

        $room = Room::create([
            'code' => self::ROOM_CODE,
            'title' => 'Trivia: códigos de estado HTTP y WebSocket',
            'admin_password' => self::ADMIN_PIN,
            'status' => Room::STATUS_WAITING,
            'current_question_index' => 0,
            'seconds_per_question' => 20,
        ]);

        $room->questions()->createMany([
            [
                'question_text' => '¿Qué indica un código de estado HTTP 200?',
                'options' => [
                    'La petición se completó correctamente',
                    'El recurso solicitado no existe',
                    'El servidor tuvo un error interno',
                    'El recurso fue creado',
                ],
                'correct_option' => 0,
            ],
            [
                'question_text' => '¿Qué código HTTP indica que el recurso solicitado no existe?',
                'options' => ['200', '301', '404', '500'],
                'correct_option' => 2,
            ],
            [
                'question_text' => '¿Qué código HTTP indica un error interno del servidor?',
                'options' => ['400', '401', '403', '500'],
                'correct_option' => 3,
            ],
            [
                'question_text' => '¿Qué código HTTP confirma que un recurso se creó correctamente?',
                'options' => ['200', '201', '204', '304'],
                'correct_option' => 1,
            ],
            [
                'question_text' => '¿Qué código HTTP indica que se superó el límite de peticiones?',
                'options' => ['404', '429', '503', '504'],
                'correct_option' => 1,
            ],
            [
                'question_text' => '¿Qué esquema de URL se usa para una conexión WebSocket segura?',
                'options' => ['http://', 'https://', 'ws://', 'wss://'],
                'correct_option' => 3,
            ],
            [
                'question_text' => '¿Qué código HTTP confirma el cambio de protocolo a WebSocket?',
                'options' => ['100 Continue', '101 Switching Protocols', '200 OK', '304 Not Modified'],
                'correct_option' => 1,
            ],
            [
                'question_text' => '¿Qué característica define a WebSocket frente a HTTP tradicional?',
                'options' => [
                    'Una conexión persistente y bidireccional',
                    'Solo permite peticiones del cliente al servidor',
                    'No puede usarse desde un navegador',
                    'Solo funciona sobre el puerto 80',
                ],
                'correct_option' => 0,
            ],
            [
                'question_text' => '¿Qué servicio de Laravel usa este proyecto para WebSocket?',
                'options' => ['Reverb', 'Horizon', 'Nova', 'Pennant'],
                'correct_option' => 0,
            ],
            [
                'question_text' => '¿Qué código de cierre WebSocket indica un cierre normal?',
                'options' => ['1000', '1001', '1011', '4040'],
                'correct_option' => 0,
            ],
        ]);

        $this->command?->info("Sala por defecto lista · código: {$room->code} · PIN: ".self::ADMIN_PIN);
        $this->command?->line('  Unirse:    '.route('rooms.join', ['code' => $room->code]));
        $this->command?->line('  Panel:     '.route('rooms.show', $room));
        $this->command?->line('  Proyector: '.route('rooms.screen', $room));
    }
}

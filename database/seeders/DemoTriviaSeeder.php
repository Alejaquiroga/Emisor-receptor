<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class DemoTriviaSeeder extends Seeder
{
    /**
     * Seed a ready-to-play room for demos and presentations.
     */
    public function run(): void
    {
        Room::where('code', 'DEMO01')->delete();

        $room = Room::create([
            'code' => 'DEMO01',
            'title' => 'Trivia demo · Cultura general y Laravel',
            'admin_password' => 'demo',
            'status' => Room::STATUS_WAITING,
            'current_question_index' => 0,
            'seconds_per_question' => 20,
        ]);

        $room->questions()->createMany([
            [
                'question_text' => '¿Cuál es el resultado de 2 + 2 * 2?',
                'options' => ['8', '6', '4', '10'],
                'correct_option' => 1,
            ],
            [
                'question_text' => '¿Qué lenguaje de programación usa Laravel?',
                'options' => ['Python', 'Ruby', 'PHP', 'JavaScript'],
                'correct_option' => 2,
            ],
            [
                'question_text' => '¿Qué componente de Laravel maneja la base de datos con modelos?',
                'options' => ['Eloquent', 'Blade', 'Artisan', 'Vite'],
                'correct_option' => 0,
            ],
            [
                'question_text' => '¿Cuál es la capital de Perú?',
                'options' => ['Quito', 'Lima', 'Bogotá', 'Santiago'],
                'correct_option' => 1,
            ],
            [
                'question_text' => '¿Qué comando de Artisan crea una migración?',
                'options' => ['php artisan make:migration', 'php artisan migrate:new', 'php artisan db:make', 'php artisan new:migration'],
                'correct_option' => 0,
            ],
            [
                'question_text' => '¿Qué motor de plantillas usa Laravel por defecto?',
                'options' => ['Twig', 'Blade', 'Smarty', 'Handlebars'],
                'correct_option' => 1,
            ],
            [
                'question_text' => '¿Cuántos bits tiene un byte?',
                'options' => ['4', '16', '8', '32'],
                'correct_option' => 2,
            ],
            [
                'question_text' => '¿Qué servicio transmite eventos en tiempo real por WebSockets en Laravel?',
                'options' => ['Reverb', 'Horizon', 'Nova', 'Pennant'],
                'correct_option' => 0,
            ],
            [
                'question_text' => '¿Cuál de estas herramientas es un sistema de control de versiones?',
                'options' => ['Git', 'Docker', 'Nginx', 'Redis'],
                'correct_option' => 0,
            ],
            [
                'question_text' => '¿Qué archivo define las dependencias de PHP de un proyecto?',
                'options' => ['package.json', 'composer.json', 'vite.config.js', '.env'],
                'correct_option' => 1,
            ],
        ]);

        $this->command?->info("Sala demo lista → código: {$room->code} · PIN de admin: demo");
        $this->command?->info('Panel de administración: '.route('rooms.show', $room));
        $this->command?->info('Enlace para participantes: '.route('rooms.join', ['code' => $room->code]));
    }
}

<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/trivia');

Route::get('/trivia', [RoomController::class, 'create'])->name('rooms.create');
Route::post('/trivia', [RoomController::class, 'store'])->name('rooms.store');
Route::get('/trivia/join', [RoomController::class, 'join'])->name('rooms.join');
Route::post('/trivia/join', [RoomController::class, 'joinRoom'])->name('rooms.join.submit');

Route::get('/trivia/{room}', [RoomController::class, 'show'])->name('rooms.show');
Route::post('/trivia/{room}/unlock', [RoomController::class, 'unlock'])->name('rooms.unlock');
Route::post('/trivia/{room}/lock', [RoomController::class, 'lock'])->name('rooms.lock');
Route::get('/trivia/{room}/participants', [RoomController::class, 'participants'])->name('rooms.participants');
Route::get('/trivia/{room}/state', [RoomController::class, 'state'])->name('rooms.state');
Route::get('/trivia/{room}/screen', [RoomController::class, 'screen'])->name('rooms.screen');

Route::middleware('room.admin')->group(function (): void {
    Route::post('/trivia/{room}/start', [GameController::class, 'start'])->name('game.start');
    Route::post('/trivia/{room}/next', [GameController::class, 'next'])->name('game.next');
});

Route::middleware('room.participant')->group(function (): void {
    Route::get('/trivia/{room}/play/{participant}', [GameController::class, 'play'])->name('game.play');
    Route::post('/trivia/{room}/play/{participant}/questions/{question}', [GameController::class, 'answer'])->name('game.answer');
});

<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\RemoteStyleController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/trivia'));
Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

Route::get('/trivia', [RoomController::class, 'create'])->name('rooms.create');
Route::post('/trivia', [RoomController::class, 'store'])->name('rooms.store');
Route::get('/trivia/join', [RoomController::class, 'join'])->name('rooms.join');
Route::post('/trivia/join', [RoomController::class, 'joinRoom'])->name('rooms.join.submit');

Route::get('/trivia/{room}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/trivia/{room}/participants', [RoomController::class, 'participants'])->name('rooms.participants');

Route::post('/trivia/{room}/start', [GameController::class, 'start'])->name('game.start');
Route::post('/trivia/{room}/next', [GameController::class, 'next'])->name('game.next');
Route::get('/trivia/{room}/play/{participant}', [GameController::class, 'play'])->name('game.play');
Route::post('/trivia/{room}/play/{participant}/questions/{question}', [GameController::class, 'answer'])->name('game.answer');

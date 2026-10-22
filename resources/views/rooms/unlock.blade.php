@extends('layouts.app')

@section('title', $room->title . ' · Acceso de administrador')

@section('content')
    <div class="mx-auto max-w-md space-y-6">
        <header class="text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-indigo-400">Panel de control</p>
            <h1 class="text-2xl font-semibold">{{ $room->title }}</h1>
            <p class="mt-1 text-sm text-slate-400">
                Sala <span class="font-mono text-indigo-300">{{ $room->code }}</span>. Ingresa el PIN de administrador para gestionarla.
            </p>
        </header>

        <form method="POST" action="{{ route('rooms.unlock', $room) }}" class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            @csrf

            <div>
                <label for="password" class="mb-1 block text-sm text-slate-300">PIN de administrador</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="off"
                    placeholder="••••"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-indigo-500 px-4 py-3 font-semibold text-white transition hover:bg-indigo-400"
            >
                Desbloquear panel
            </button>
        </form>

        <p class="text-center text-sm text-slate-500">
            ¿Vas a jugar?
            <a href="{{ route('rooms.join', ['code' => $room->code]) }}" class="text-indigo-400 underline-offset-4 hover:underline">Entra como participante</a>
        </p>
    </div>
@endsection

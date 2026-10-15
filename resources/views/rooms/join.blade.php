@extends('layouts.app')

@section('title', 'Unirse a la trivia')

@section('content')
    <div class="mx-auto max-w-md space-y-6">
        <header class="text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-indigo-400">Trivia en vivo</p>
            <h1 class="text-2xl font-semibold">Unirse a una sala</h1>
            <p class="mt-1 text-sm text-slate-400">Ingresa el código de 6 caracteres que muestra el administrador.</p>
        </header>

        <form method="POST" action="{{ route('rooms.join.submit') }}" class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            @csrf

            <div>
                <label for="code" class="mb-1 block text-sm text-slate-300">Código de la sala</label>
                <input
                    id="code"
                    type="text"
                    name="code"
                    value="{{ old('code') }}"
                    required
                    maxlength="6"
                    minlength="6"
                    autocomplete="off"
                    autocapitalize="characters"
                    placeholder="ABC123"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-center font-mono text-2xl uppercase tracking-[0.4em] text-white placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"
                >
            </div>

            <div>
                <label for="name" class="mb-1 block text-sm text-slate-300">Tu nombre</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    maxlength="40"
                    autocomplete="nickname"
                    placeholder="Ej: Ana"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-indigo-500 px-4 py-3 font-semibold text-white transition hover:bg-indigo-400"
            >
                Entrar a la sala
            </button>
        </form>

        <p class="text-center text-sm text-slate-500">
            ¿Eres el organizador?
            <a href="{{ route('rooms.create') }}" class="text-indigo-400 underline-offset-4 hover:underline">Crea una sala</a>
        </p>
    </div>
@endsection

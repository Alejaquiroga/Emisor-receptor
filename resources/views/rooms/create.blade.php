@extends('layouts.app')

@section('title', 'Crear sala · Trivia en vivo')

@section('content')
    @php($questionsForForm = old('questions') ?: [['question_text' => '', 'options' => ['', '', '', ''], 'correct_option' => 0]])

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-indigo-400">Trivia en vivo</p>
            <h1 class="text-2xl font-semibold">Crear sala</h1>
            <p class="mt-1 text-sm text-slate-400">Define las preguntas y comparte el código con los participantes.</p>
        </div>
        <a href="{{ route('rooms.join') }}"
           class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:bg-slate-900">
            Unirse a una sala
        </a>
    </header>

    <form method="POST" action="{{ route('rooms.store') }}" class="space-y-6">
        @csrf

        <div>
            <label for="title" class="mb-1 block text-sm text-slate-300">Título de la sala</label>
            <input
                id="title"
                type="text"
                name="title"
                value="{{ old('title') }}"
                maxlength="120"
                required
                placeholder="Ej: Trivia de Laravel"
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"
            >
        </div>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Preguntas</h2>
                <button
                    type="button"
                    id="add-question"
                    class="rounded-xl border border-indigo-500/60 px-3 py-2 text-sm text-indigo-300 transition hover:bg-indigo-500/10"
                >
                    + Añadir pregunta
                </button>
            </div>

            <div id="questions" class="space-y-4">
                @foreach ($questionsForForm as $index => $question)
                    @include('rooms.partials.question-fields', ['index' => $index, 'question' => $question])
                @endforeach
            </div>

            <template id="question-template">
                @include('rooms.partials.question-fields', ['index' => '__INDEX__', 'question' => []])
            </template>
        </section>

        <button
            type="submit"
            class="w-full rounded-xl bg-indigo-500 px-4 py-3 font-semibold text-white transition hover:bg-indigo-400"
        >
            Crear sala
        </button>
    </form>
@endsection

@push('scripts')
    <script>
        const container = document.getElementById('questions');
        const template = document.getElementById('question-template');
        const existingIndexes = Array.from(container.querySelectorAll('input[name^="questions["]'), (input) => {
            const match = input.name.match(/^questions\[(\d+)\]/);
            return match ? Number(match[1]) : -1;
        });
        let counter = Math.max(0, ...existingIndexes) + 1;

        document.getElementById('add-question').addEventListener('click', () => {
            const html = template.innerHTML.replaceAll('__INDEX__', String(counter++));
            container.insertAdjacentHTML('beforeend', html);
        });

        container.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-question]');
            if (removeButton) {
                removeButton.closest('.question-card').remove();
            }
        });
    </script>
@endpush

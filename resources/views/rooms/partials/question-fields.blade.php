@php($question = $question ?? [])

<div class="question-card space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
    <div class="flex items-start justify-between gap-3">
        <span class="text-xs font-semibold uppercase tracking-widest text-slate-500">Pregunta</span>
        <button type="button" data-remove-question class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-400 hover:border-rose-500/60 hover:text-rose-300">
            Eliminar
        </button>
    </div>

    <div>
        <label class="mb-1 block text-sm text-slate-300">Enunciado</label>
        <input
            type="text"
            name="questions[{{ $index }}][question_text]"
            value="{{ old("questions.$index.question_text", $question['question_text'] ?? '') }}"
            maxlength="255"
            required
            placeholder="¿Cuál es el resultado de 2 + 2 * 2?"
            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"
        >
    </div>

    <fieldset>
        <legend class="mb-2 text-sm text-slate-300">Opciones</legend>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (range(0, 3) as $option)
                <div class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-950 px-3 py-2">
                    <input
                        type="radio"
                        name="questions[{{ $index }}][correct_option]"
                        value="{{ $option }}"
                        @checked((int) old("questions.$index.correct_option", $question['correct_option'] ?? 0) === $option)
                        title="Marcar como respuesta correcta"
                        class="h-4 w-4 shrink-0 accent-emerald-500"
                    >
                    <input
                        type="text"
                        name="questions[{{ $index }}][options][{{ $option }}]"
                        value="{{ old("questions.$index.options.$option", $question['options'][$option] ?? '') }}"
                        maxlength="120"
                        required
                        placeholder="Opción {{ chr(65 + $option) }}"
                        class="w-full bg-transparent text-sm text-white placeholder:text-slate-600 focus:outline-none"
                    >
                </div>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-500">Marca con el círculo la opción correcta.</p>
    </fieldset>
</div>

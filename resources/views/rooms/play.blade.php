@extends('layouts.app')

@section('title', $room->title . ' · Jugando')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Sala {{ $room->code }}</p>
                <h1 class="text-xl font-semibold">{{ $room->title }}</h1>
                <p class="text-sm text-slate-400">{{ $participant->name }}</p>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span id="my-score" class="rounded-xl bg-indigo-500/15 px-3 py-2 font-mono text-indigo-300">
                    {{ $participant->score }} pts
                </span>
                <span id="my-position" class="rounded-xl bg-slate-800 px-3 py-2 text-slate-300">
                    #{{ $position }}
                </span>
            </div>
        </header>

        <section id="waiting-state" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center @if ($room->status !== \App\Models\Room::STATUS_WAITING) hidden @endif">
            <p class="text-lg font-medium">Esperando a que el administrador inicie la trivia…</p>
            <p class="mt-2 text-sm text-slate-400">
                Mantén esta pantalla abierta. El código de la sala es
                <span class="font-mono text-indigo-300">{{ $room->code }}</span>.
            </p>
        </section>

        <section id="question-state" class="space-y-4 @if ($currentQuestion === null) hidden @endif">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
                <p id="question-progress" class="text-xs font-semibold uppercase tracking-widest text-indigo-400">
                    @if ($currentQuestion !== null)
                        Pregunta {{ $room->current_question_index + 1 }} / {{ $room->totalQuestions() }}
                    @endif
                </p>
                <p id="question-text" class="mt-2 text-2xl font-semibold leading-snug">
                    {{ $currentQuestion?->question_text }}
                </p>

                <div id="options" class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($currentQuestion?->options ?? [] as $index => $option)
                        <button
                            type="button"
                            data-option="{{ $index }}"
                            class="option-button flex min-h-20 w-full items-center gap-3 rounded-2xl border-2 border-slate-700 bg-slate-950 px-4 py-4 text-left text-lg font-medium transition hover:border-indigo-500 hover:bg-indigo-500/10 disabled:cursor-not-allowed"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-sm font-bold">
                                {{ chr(65 + $index) }}
                            </span>
                            <span>{{ $option }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="finished-state" class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-8 text-center @if ($room->status !== \App\Models\Room::STATUS_FINISHED) hidden @endif">
            <p class="text-2xl font-semibold text-emerald-300">¡Trivia finalizada!</p>
            <p id="finished-message" class="mt-2 text-sm text-emerald-200/80">
                Revisa el podio para ver los resultados finales.
            </p>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="flex items-baseline justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-500">Podio en vivo</h2>
                <span id="participants-count" class="text-xs text-slate-400">{{ count($ranking) }} participantes</span>
            </div>
            <ol id="podium" class="mt-3 space-y-2"></ol>
            <p id="podium-empty" class="mt-3 text-sm text-slate-500 @if (count($ranking) > 0) hidden @endif">
                Aún no hay participantes.
            </p>
        </section>
    </div>

    <div
        id="toast"
        class="pointer-events-none fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-xl border px-5 py-3 text-sm font-semibold opacity-0 shadow-lg transition-opacity duration-300"
    ></div>
@endsection

@push('scripts')
    <script>
        const room = {{ Js::from([
            'id' => $room->id,
            'status' => $room->status,
            'totalQuestions' => $room->totalQuestions(),
        ]) }};
        const participant = {{ Js::from(['id' => $participant->id, 'name' => $participant->name]) }};
        const answerBaseUrl = {{ Js::from(route('game.play', [$room, $participant])) }} + '/questions/';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        let question = {{ Js::from($currentQuestion === null ? null : [
            'id' => $currentQuestion->id,
            'question_text' => $currentQuestion->question_text,
            'options' => $currentQuestion->options,
        ]) }};
        let myAnswer = {{ Js::from($currentAnswer === null ? null : [
            'selected_option' => $currentAnswer->selected_option,
            'is_correct' => $currentAnswer->is_correct,
        ]) }};
        let correctOption = myAnswer !== null && myAnswer.is_correct ? myAnswer.selected_option : null;
        let ranking = {{ Js::from($ranking) }};
        let submitting = false;

        const waitingState = document.getElementById('waiting-state');
        const questionState = document.getElementById('question-state');
        const finishedState = document.getElementById('finished-state');
        const optionsEl = document.getElementById('options');
        const podium = document.getElementById('podium');
        const podiumEmpty = document.getElementById('podium-empty');
        const participantsCount = document.getElementById('participants-count');
        const toast = document.getElementById('toast');

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function positionClass(index) {
            if (index === 0) return 'text-yellow-400';
            if (index === 1) return 'text-slate-200';
            if (index === 2) return 'text-amber-600';
            return 'text-slate-500';
        }

        function showState(state) {
            waitingState.classList.toggle('hidden', state !== 'waiting');
            questionState.classList.toggle('hidden', state !== 'question');
            finishedState.classList.toggle('hidden', state !== 'finished');
        }

        function showToast(message, kind) {
            toast.textContent = message;
            toast.className = 'pointer-events-none fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-xl border px-5 py-3 text-sm font-semibold shadow-lg transition-opacity duration-300 '
                + (kind === 'success'
                    ? 'border-emerald-500/50 bg-emerald-500/20 text-emerald-200'
                    : 'border-rose-500/50 bg-rose-500/20 text-rose-200')
                + ' opacity-100';

            clearTimeout(toast.hideTimer);
            toast.hideTimer = setTimeout(() => {
                toast.classList.remove('opacity-100');
                toast.classList.add('opacity-0');
            }, 2600);
        }

        function updateScore(score, position) {
            document.getElementById('my-score').textContent = `${score} pts`;
            if (position) {
                document.getElementById('my-position').textContent = `#${position}`;
            }
        }

        function renderPodium() {
            podiumEmpty.classList.toggle('hidden', ranking.length > 0);
            participantsCount.textContent = `${ranking.length} participantes`;
            podium.innerHTML = ranking.map((entry, index) => `
                <li class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 ${
                    entry.id === participant.id
                        ? 'border-indigo-500/60 bg-indigo-500/10'
                        : 'border-slate-800 bg-slate-950/70'
                }">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="w-6 text-center font-semibold ${positionClass(index)}">${index + 1}</span>
                        <span class="truncate">${escapeHtml(entry.name)}${entry.id === participant.id ? ' (tú)' : ''}</span>
                    </span>
                    <span class="font-mono text-sm text-slate-300">${entry.score} pts</span>
                </li>
            `).join('');
        }

        function applyAnsweredStyles() {
            if (myAnswer === null) {
                return;
            }

            optionsEl.querySelectorAll('.option-button').forEach((button) => {
                const index = Number(button.dataset.option);
                button.disabled = true;
                button.classList.remove('hover:border-indigo-500', 'hover:bg-indigo-500/10');

                if (myAnswer && index === myAnswer.selected_option) {
                    button.classList.add(myAnswer.is_correct ? 'border-emerald-500' : 'border-rose-500');
                } else if (index === correctOption) {
                    button.classList.add('border-emerald-500/60');
                }
            });
        }

        function renderQuestion() {
            if (question === null) {
                return;
            }

            myAnswer = null;
            correctOption = null;
            document.getElementById('question-text').textContent = question.question_text;
            optionsEl.innerHTML = question.options.map((option, index) => `
                <button
                    type="button"
                    data-option="${index}"
                    class="option-button flex min-h-20 w-full items-center gap-3 rounded-2xl border-2 border-slate-700 bg-slate-950 px-4 py-4 text-left text-lg font-medium transition hover:border-indigo-500 hover:bg-indigo-500/10 disabled:cursor-not-allowed"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-sm font-bold">
                        ${String.fromCharCode(65 + index)}
                    </span>
                    <span>${escapeHtml(option)}</span>
                </button>
            `).join('');
        }

        async function submitAnswer(optionIndex) {
            if (submitting || question === null || myAnswer !== null) {
                return;
            }

            submitting = true;
            optionsEl.querySelectorAll('.option-button').forEach((button) => {
                button.disabled = true;
            });

            try {
                const response = await fetch(`${answerBaseUrl}${question.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ selected_option: optionIndex }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    showToast(payload.message ?? 'No se pudo registrar la respuesta', 'error');
                    optionsEl.querySelectorAll('.option-button').forEach((button) => {
                        button.disabled = false;
                    });
                    return;
                }

                myAnswer = { selected_option: optionIndex, is_correct: payload.is_correct };
                correctOption = payload.correct_option;
                updateScore(payload.score, payload.position);
                applyAnsweredStyles();
                showToast(
                    payload.is_correct ? `¡Correcto! +${payload.points} pts` : 'Respuesta incorrecta',
                    payload.is_correct ? 'success' : 'error',
                );
            } catch (error) {
                showToast('Error de conexión. Intenta de nuevo.', 'error');
                optionsEl.querySelectorAll('.option-button').forEach((button) => {
                    button.disabled = false;
                });
            } finally {
                submitting = false;
            }
        }

        optionsEl.addEventListener('click', (event) => {
            const button = event.target.closest('.option-button');
            if (button) {
                submitAnswer(Number(button.dataset.option));
            }
        });

        function subscribeToRoom() {
            if (typeof window.Echo === 'undefined') {
                console.warn('Laravel Echo no está disponible: la actualización en vivo queda desactivada.');
                return;
            }

            window.Echo.channel(`room.${room.id}`)
                .listen('.QuestionBroadcast', (payload) => {
                    question = payload.question;
                    document.getElementById('question-progress').textContent =
                        `Pregunta ${payload.question_index + 1} / ${payload.total_questions}`;
                    renderQuestion();
                    showState('question');
                })
                .listen('.ScoreboardUpdated', (payload) => {
                    ranking = payload.ranking;
                    renderPodium();

                    const mine = ranking.find((entry) => entry.id === participant.id);
                    if (mine) {
                        updateScore(mine.score, mine.position);
                    }

                    if (payload.status === 'finished') {
                        showState('finished');
                    } else if (payload.status === 'waiting') {
                        showState('waiting');
                    }
                });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', subscribeToRoom);
        } else {
            subscribeToRoom();
        }

        renderPodium();
        applyAnsweredStyles();
    </script>
@endpush

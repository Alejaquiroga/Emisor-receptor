@extends('layouts.app')

@section('title', $room->title . ' · Panel de control')

@section('content')
    @php($total = $room->totalQuestions())
    @php($statusText = match ($room->status) {
        \App\Models\Room::STATUS_WAITING => 'Esperando participantes',
        \App\Models\Room::STATUS_ACTIVE => 'Trivia en curso',
        default => 'Trivia finalizada',
    })

    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-400">Panel de control</p>
                <h1 class="text-2xl font-semibold">{{ $room->title }}</h1>
                <p id="status-label" class="mt-1 text-sm text-slate-400">{{ $statusText }} · {{ $room->seconds_per_question }} s por pregunta</p>
            </div>

            <form method="POST" action="{{ route('rooms.lock', $room) }}">
                @csrf
                <button
                    type="submit"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:bg-slate-900"
                >
                    Cerrar sesión de admin
                </button>
            </form>
        </header>

        <section class="grid gap-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-5 sm:grid-cols-[auto_1fr] sm:items-center">
            <div class="flex justify-center">
                <canvas
                    id="join-qr"
                    data-join-path="{{ $joinPath }}"
                    width="180"
                    height="180"
                    aria-label="Código QR para unirse a la trivia"
                    class="h-[180px] w-[180px] rounded-xl bg-slate-950"
                ></canvas>
            </div>

            <div class="space-y-2 text-center sm:text-left">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-500">Unirse a la sala</h2>
                <p class="font-mono text-3xl tracking-[0.3em] text-indigo-300">{{ $room->code }}</p>
                <p id="join-url" class="break-all text-sm text-slate-400">{{ $joinPath }}</p>
                <div class="flex flex-wrap justify-center gap-2 sm:justify-start">
                    <button
                        type="button"
                        id="copy-code"
                        class="rounded-xl border border-dashed border-indigo-500/60 px-4 py-2 text-sm text-indigo-300 transition hover:bg-indigo-500/10"
                    >
                        Copiar código
                    </button>
                    <button
                        type="button"
                        id="copy-join-url"
                        data-join-path="{{ $joinPath }}"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:bg-slate-900"
                    >
                        Copiar enlace
                    </button>
                </div>
                <p id="copy-feedback" class="h-4 text-xs text-emerald-400"></p>
            </div>
        </section>

        <section class="flex flex-wrap gap-3">
            @if ($room->status === \App\Models\Room::STATUS_WAITING)
                <form method="POST" action="{{ route('game.start', $room) }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-white transition hover:bg-emerald-400"
                    >
                        Iniciar trivia
                    </button>
                </form>
            @endif

            @if ($room->status === \App\Models\Room::STATUS_ACTIVE)
                <form method="POST" action="{{ route('game.next', $room) }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold text-white transition hover:bg-indigo-400"
                    >
                        {{ $room->current_question_index + 1 >= $total ? 'Finalizar trivia' : 'Siguiente pregunta' }}
                    </button>
                </form>
            @endif

            <a
                href="{{ route('rooms.screen', $room) }}"
                target="_blank"
                rel="noopener"
                class="rounded-xl border border-indigo-500/60 px-5 py-3 text-sm text-indigo-300 transition hover:bg-indigo-500/10"
            >
                Abrir proyector ↗
            </a>

            <button
                type="button"
                id="refresh-participants"
                class="rounded-xl border border-slate-700 px-5 py-3 text-sm text-slate-300 transition hover:bg-slate-900"
            >
                Actualizar participantes
            </button>
        </section>

        <section class="grid gap-6 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-500">Pregunta actual</h2>

                <div id="question-panel" class="mt-3 space-y-3 @if ($currentQuestion === null) hidden @endif">
                    <p id="question-index" class="text-xs font-semibold uppercase tracking-widest text-indigo-400">
                        @if ($currentQuestion !== null)
                            Pregunta {{ $room->current_question_index + 1 }} / {{ $total }}
                        @endif
                    </p>
                    <p id="question-text" class="text-lg font-medium">
                        {{ $currentQuestion?->question_text }}
                    </p>
                    <ol id="question-options" class="space-y-2">
                        @foreach ($currentQuestion?->options ?? [] as $index => $option)
                            <li class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-950 px-4 py-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20 text-xs font-semibold text-indigo-300">
                                    {{ chr(65 + $index) }}
                                </span>
                                <span>{{ $option }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <p id="question-empty" class="mt-3 text-sm text-slate-500 @if ($currentQuestion !== null) hidden @endif">
                    La trivia aún no ha iniciado. Comparte el código y pulsa «Iniciar trivia».
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-500">Podio en vivo</h2>
                    <span id="participants-count" class="text-xs text-slate-400">{{ count($ranking) }} participantes</span>
                </div>

                <ol id="podium" class="mt-3 space-y-2"></ol>

                <p id="podium-empty" class="mt-3 text-sm text-slate-500 @if (count($ranking) > 0) hidden @endif">
                    Todavía no hay participantes en la sala.
                </p>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        const room = {{ Js::from([
            'id' => $room->id,
            'status' => $room->status,
            'questionIndex' => $room->current_question_index,
            'totalQuestions' => $total,
            'participantsUrl' => route('rooms.participants', $room),
        ]) }};
        const initialRanking = {{ Js::from($ranking) }};

        const statusLabels = {
            waiting: 'Esperando participantes',
            active: 'Trivia en curso',
            finished: 'Trivia finalizada',
        };

        const podium = document.getElementById('podium');
        const podiumEmpty = document.getElementById('podium-empty');
        const participantsCount = document.getElementById('participants-count');
        const statusLabel = document.getElementById('status-label');
        const questionPanel = document.getElementById('question-panel');
        const questionEmpty = document.getElementById('question-empty');

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

        function renderPodium(ranking) {
            podiumEmpty.classList.toggle('hidden', ranking.length > 0);
            participantsCount.textContent = `${ranking.length} participantes`;
            podium.innerHTML = ranking.map((entry, index) => `
                <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="w-6 text-center font-semibold ${positionClass(index)}">${index + 1}</span>
                        <span class="truncate">${escapeHtml(entry.name)}</span>
                    </span>
                    <span class="font-mono text-sm text-slate-300">${entry.score} pts</span>
                </li>
            `).join('');
        }

        function renderQuestion(payload) {
            questionEmpty.classList.add('hidden');
            questionPanel.classList.remove('hidden');

            document.getElementById('question-index').textContent =
                `Pregunta ${payload.question_index + 1} / ${payload.total_questions}`;
            document.getElementById('question-text').textContent = payload.question.question_text;
            document.getElementById('question-options').innerHTML = payload.question.options
                .map((option, index) => `
                    <li class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-950 px-4 py-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20 text-xs font-semibold text-indigo-300">
                            ${String.fromCharCode(65 + index)}
                        </span>
                        <span>${escapeHtml(option)}</span>
                    </li>
                `).join('');
        }

        function renderStatus(status) {
            statusLabel.textContent = statusLabels[status] ?? status;
        }

        function showCopyFeedback(message) {
            const feedback = document.getElementById('copy-feedback');
            feedback.textContent = message;
            setTimeout(() => {
                feedback.textContent = '';
            }, 2000);
        }

        document.getElementById('copy-code').addEventListener('click', async () => {
            await navigator.clipboard.writeText({{ Js::from($room->code) }});
            showCopyFeedback('Código copiado');
        });

        document.getElementById('copy-join-url').addEventListener('click', async (event) => {
            const url = new URL(event.currentTarget.dataset.joinPath, window.location.origin).toString();
            await navigator.clipboard.writeText(url);
            showCopyFeedback('Enlace copiado');
        });

        document.getElementById('refresh-participants').addEventListener('click', async () => {
            const response = await fetch(room.participantsUrl, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            renderPodium(payload.ranking);
            renderStatus(payload.status);
        });

        function subscribeToRoom() {
            if (typeof window.Echo === 'undefined') {
                console.warn('Laravel Echo no está disponible: la actualización en vivo queda desactivada.');
                return;
            }

            window.Echo.channel(`room.${room.id}`)
                .listen('.QuestionBroadcast', (payload) => {
                    renderStatus(payload.status);
                    renderQuestion(payload);
                })
                .listen('.ScoreboardUpdated', (payload) => {
                    renderStatus(payload.status);
                    renderPodium(payload.ranking);
                });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', subscribeToRoom);
        } else {
            subscribeToRoom();
        }

        renderPodium(initialRanking);
    </script>
@endpush

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $room->title }} · Proyector</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes podium-rise {
            from { transform: scaleY(0); }
            to { transform: scaleY(1); }
        }

        @keyframes pop-in {
            from { opacity: 0; transform: translateY(16px) scale(0.97); }
            to { opacity: 1; transform: none; }
        }

        .podium-column {
            transform-origin: bottom;
            animation: podium-rise 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }

        .pop-in {
            animation: pop-in 0.45s ease both;
        }

        .option-bar {
            transition: width 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-[1400px] flex-col px-8 py-6">
        <header class="flex flex-wrap items-center justify-between gap-6 border-b border-slate-800 pb-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-400">Trivia en vivo</p>
                <h1 class="text-2xl font-semibold">{{ $room->title }}</h1>
            </div>

            <div class="flex items-center gap-6">
                <div class="text-right">
                    <p class="text-xs uppercase tracking-widest text-slate-500">Código</p>
                    <p class="font-mono text-xl tracking-[0.25em] text-indigo-300">{{ $room->code }}</p>
                </div>
                <button
                    id="sound-toggle"
                    type="button"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:bg-slate-900"
                >
                    🔊 Activar sonido
                </button>
            </div>
        </header>

        <main class="flex flex-1 flex-col justify-center py-6">
            <section id="screen-waiting" class="grid flex-1 items-center gap-10 lg:grid-cols-2 @if ($room->status !== \App\Models\Room::STATUS_WAITING) hidden @endif">
                <div class="text-center lg:text-left">
                    <h2 class="text-5xl font-bold leading-tight">Escaneá el QR<br>o ingresá el código</h2>
                    <p class="mt-5 text-xl text-slate-400">
                        Entrá desde tu celular a
                        <span id="join-url" class="text-indigo-300">{{ $joinPath }}</span>
                    </p>
                    <p class="mt-8 font-mono text-6xl tracking-[0.3em] text-indigo-300">{{ $room->code }}</p>
                    <p class="mt-8 text-2xl text-slate-300">
                        <span id="screen-participants">{{ count($ranking) }}</span> participantes conectados
                    </p>
                </div>

                <div class="flex justify-center">
                    <div class="rounded-3xl bg-white p-4 shadow-2xl shadow-indigo-500/20">
                        <canvas
                            id="join-qr"
                            data-join-path="{{ $joinPath }}"
                            width="340"
                            height="340"
                            aria-label="Código QR para unirse a la trivia"
                            class="h-[340px] w-[340px]"
                        ></canvas>
                    </div>
                </div>
            </section>

            <section id="screen-question" class="flex flex-1 flex-col justify-center gap-6 @if ($room->status !== \App\Models\Room::STATUS_ACTIVE) hidden @endif">
                <div class="flex items-center justify-between gap-6">
                    <p id="screen-progress" class="text-sm font-semibold uppercase tracking-[0.3em] text-indigo-400"></p>
                    <p id="screen-answered" class="text-sm text-slate-400"></p>
                </div>

                <div class="flex items-start justify-between gap-8">
                    <h2 id="screen-question-text" class="max-w-5xl text-4xl font-bold leading-tight"></h2>
                    <div class="shrink-0 text-center">
                        <p id="screen-countdown" class="font-mono text-7xl font-bold text-indigo-300">--</p>
                        <p class="text-xs uppercase tracking-widest text-slate-500">segundos</p>
                    </div>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-800">
                    <div id="screen-countdown-bar" class="h-full w-full rounded-full bg-indigo-500 transition-[width] duration-200 ease-linear"></div>
                </div>

                <ul id="screen-options" class="grid gap-4 sm:grid-cols-2"></ul>

                <p id="screen-reveal" class="hidden text-center text-xl font-semibold text-emerald-300">
                    ✅ Respuesta correcta: <span id="screen-reveal-option"></span>
                </p>
            </section>

            <section id="screen-finished" class="flex flex-1 flex-col items-center justify-center gap-8 @if ($room->status !== \App\Models\Room::STATUS_FINISHED) hidden @endif">
                <h2 class="text-5xl font-bold">🏆 Podio final</h2>
                <div id="screen-podium" class="flex w-full max-w-4xl items-end justify-center gap-6"></div>
                <ol id="screen-ranking" class="mt-4 w-full max-w-2xl space-y-2"></ol>
            </section>
        </main>
    </div>

    <script>
        const roomId = {{ Js::from($room->id) }};
        const stateUrl = {{ Js::from(route('rooms.state', $room)) }};

        const current = {
            status: {{ Js::from($room->status) }},
            question: null,
            questionIndex: {{ $room->current_question_index }},
            total: {{ $room->totalQuestions() }},
            secondsPerQuestion: {{ $room->seconds_per_question }},
            deadlineMs: null,
            participantsCount: {{ count($ranking) }},
            answeredCount: 0,
            optionCounts: [],
            ranking: {{ Js::from($ranking) }},
            revealed: false,
            correctOption: null,
        };

        let renderedQuestionId = null;
        let countdownTimer = null;
        let revealRequested = false;
        let revealSoundPlayed = false;
        let lastTickSecond = null;
        let finishedRendered = false;
        let soundEnabled = false;

        const sections = {
            waiting: document.getElementById('screen-waiting'),
            question: document.getElementById('screen-question'),
            finished: document.getElementById('screen-finished'),
        };

        const optionStyles = [
            { card: 'border-rose-500/50 bg-rose-500/10', bar: 'bg-rose-400', badge: 'bg-rose-500/25 text-rose-100' },
            { card: 'border-sky-500/50 bg-sky-500/10', bar: 'bg-sky-400', badge: 'bg-sky-500/25 text-sky-100' },
            { card: 'border-amber-500/50 bg-amber-500/10', bar: 'bg-amber-400', badge: 'bg-amber-500/25 text-amber-100' },
            { card: 'border-emerald-500/50 bg-emerald-500/10', bar: 'bg-emerald-400', badge: 'bg-emerald-500/25 text-emerald-100' },
        ];

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function showOnly(name) {
            Object.entries(sections).forEach(([key, section]) => {
                section.classList.toggle('hidden', key !== name);
            });
        }

        function optionCard(option, index) {
            const style = optionStyles[index] ?? optionStyles[0];

            return `
                <li data-option-card="${index}" class="pop-in relative overflow-hidden rounded-3xl border-2 ${style.card} p-6">
                    <div class="relative z-10 flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-xl font-bold ${style.badge}">
                                ${String.fromCharCode(65 + index)}
                            </span>
                            <span class="text-2xl font-semibold leading-snug">${escapeHtml(option)}</span>
                        </div>
                        <span data-option-count class="shrink-0 font-mono text-2xl font-bold text-white/90">0</span>
                    </div>
                    <div class="relative z-0 mt-4 h-2 w-full overflow-hidden rounded-full bg-black/30">
                        <div data-option-bar class="option-bar h-full w-0 rounded-full ${style.bar}"></div>
                    </div>
                </li>
            `;
        }

        function updateCounts() {
            const counts = current.optionCounts ?? [];
            const totalAnswers = counts.reduce((sum, count) => sum + count, 0);

            document.getElementById('screen-answered').textContent =
                `${current.answeredCount} / ${current.participantsCount} respuestas`;

            counts.forEach((count, index) => {
                const card = document.querySelector(`[data-option-card="${index}"]`);

                if (!card) {
                    return;
                }

                card.querySelector('[data-option-count]').textContent = count;
                card.querySelector('[data-option-bar]').style.width =
                    `${totalAnswers > 0 ? Math.round((count / totalAnswers) * 100) : 0}%`;
            });
        }

        function renderQuestion() {
            if (!current.question) {
                return;
            }

            document.getElementById('screen-progress').textContent =
                `Pregunta ${current.questionIndex + 1} / ${current.total}`;
            document.getElementById('screen-question-text').textContent = current.question.question_text;

            if (renderedQuestionId !== current.question.id) {
                renderedQuestionId = current.question.id;
                document.getElementById('screen-options').innerHTML =
                    current.question.options.map(optionCard).join('');
                clearReveal();
            }

            updateCounts();

            if (current.revealed) {
                revealCorrect();
            }
        }

        function revealCorrect() {
            if (current.correctOption === null || !current.question) {
                return;
            }

            const correct = current.correctOption;

            document.querySelectorAll('[data-option-card]').forEach((card) => {
                const index = Number(card.dataset.optionCard);
                const isCorrect = index === correct;

                card.classList.toggle('ring-4', isCorrect);
                card.classList.toggle('ring-white', isCorrect);
                card.classList.toggle('opacity-40', !isCorrect);
            });

            const letter = String.fromCharCode(65 + correct);
            document.getElementById('screen-reveal-option').textContent =
                `${letter} · ${current.question.options[correct]}`;
            document.getElementById('screen-reveal').classList.remove('hidden');

            if (!revealSoundPlayed) {
                revealSoundPlayed = true;

                if (soundEnabled) {
                    window.triviaSounds?.playReveal();
                }
            }
        }

        function clearReveal() {
            document.querySelectorAll('[data-option-card]').forEach((card) => {
                card.classList.remove('ring-4', 'ring-white', 'opacity-40');
            });
            document.getElementById('screen-reveal').classList.add('hidden');
        }

        function renderCountdown() {
            if (current.deadlineMs === null) {
                document.getElementById('screen-countdown').textContent = '--';
                document.getElementById('screen-countdown-bar').style.width = '100%';
                return;
            }

            const remaining = Math.max(0, current.deadlineMs - Date.now());
            const total = Math.max(1, current.secondsPerQuestion * 1000);
            const seconds = Math.ceil(remaining / 1000);
            const number = document.getElementById('screen-countdown');
            const bar = document.getElementById('screen-countdown-bar');

            number.textContent = seconds;
            number.classList.toggle('text-rose-400', seconds <= 5);
            number.classList.toggle('text-indigo-300', seconds > 5);
            bar.style.width = `${Math.max(0, Math.min(100, (remaining / total) * 100))}%`;
            bar.classList.toggle('bg-rose-500', seconds <= 5);
            bar.classList.toggle('bg-indigo-500', seconds > 5);

            if (soundEnabled && seconds > 0 && seconds <= 5 && seconds !== lastTickSecond) {
                lastTickSecond = seconds;
                window.triviaSounds?.playTick();
            }

            if (remaining <= 0 && !current.revealed && !revealRequested) {
                revealRequested = true;
                refresh().then(() => {
                    // The server may still consider the window open for a fraction
                    // of a second because of clock skew; retry shortly after.
                    setTimeout(() => {
                        revealRequested = false;
                    }, 1000);
                });
            }
        }

        function startCountdown() {
            stopCountdown();

            if (current.deadlineMs === null) {
                return;
            }

            renderCountdown();
            countdownTimer = setInterval(renderCountdown, 200);
        }

        function stopCountdown() {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }

        function renderPodium() {
            const [first, second, third] = current.ranking;
            const podium = document.getElementById('screen-podium');

            const column = (entry, place, heightClass, colorClass) => {
                if (!entry) {
                    return '<div class="w-44"></div>';
                }

                const medal = place === 1 ? '🥇' : place === 2 ? '🥈' : '🥉';

                return `
                    <div class="flex w-44 flex-col items-center gap-2">
                        <span class="text-3xl">${medal}</span>
                        <p class="max-w-full truncate text-xl font-semibold">${escapeHtml(entry.name)}</p>
                        <p class="font-mono text-sm text-slate-400">${entry.score} pts</p>
                        <div class="podium-column ${heightClass} ${colorClass} flex w-full items-start justify-center rounded-t-2xl pt-3 text-3xl font-bold text-white/80">
                            ${place}
                        </div>
                    </div>
                `;
            };

            podium.innerHTML = [
                column(second, 2, 'h-40', 'bg-slate-400/60'),
                column(first, 1, 'h-60', 'bg-amber-500/70'),
                column(third, 3, 'h-28', 'bg-amber-700/60'),
            ].join('');
        }

        function renderRanking() {
            const ranking = document.getElementById('screen-ranking');

            ranking.innerHTML = current.ranking.map((entry, index) => `
                <li class="flex items-center justify-between gap-4 rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="w-8 text-center font-mono text-slate-500">${index + 1}</span>
                        <span class="truncate">${escapeHtml(entry.name)}</span>
                    </span>
                    <span class="font-mono text-slate-300">${entry.score} pts</span>
                </li>
            `).join('');
        }

        function renderFinished() {
            const firstTime = !finishedRendered;

            if (firstTime) {
                finishedRendered = true;
                renderPodium();
                window.celebrate?.();

                if (soundEnabled) {
                    window.triviaSounds?.playFanfare();
                }
            }

            renderRanking();
        }

        function applyState(data) {
            current.status = data.status;
            current.total = data.total_questions;
            current.questionIndex = data.question_index;
            current.secondsPerQuestion = data.seconds_per_question ?? current.secondsPerQuestion;
            current.participantsCount = data.participants_count ?? 0;
            current.answeredCount = data.answered_count ?? 0;
            current.optionCounts = data.option_counts ?? [];
            current.ranking = data.ranking ?? [];
            current.revealed = Boolean(data.revealed);
            current.correctOption = data.correct_option;
            current.deadlineMs = data.ends_at
                ? Date.parse(data.ends_at) + (Date.now() - Date.parse(data.server_now))
                : null;

            document.getElementById('screen-participants').textContent = current.participantsCount;

            if (data.status === 'waiting') {
                stopCountdown();
                finishedRendered = false;
                showOnly('waiting');
                return;
            }

            if (data.status === 'finished') {
                stopCountdown();
                showOnly('finished');
                renderFinished();
                return;
            }

            if (!data.question) {
                stopCountdown();
                showOnly('waiting');
                return;
            }

            current.question = data.question;
            finishedRendered = false;
            showOnly('question');
            renderQuestion();
            startCountdown();
        }

        async function refresh() {
            try {
                const response = await fetch(stateUrl, { headers: { Accept: 'application/json' } });

                if (response.ok) {
                    applyState(await response.json());
                }
            } catch (error) {
                console.warn('No se pudo sincronizar el estado del proyector.', error);
            }
        }

        function subscribeToRoom() {
            if (typeof window.Echo === 'undefined') {
                console.warn('Laravel Echo no está disponible: el proyector usará solo polling.');
                return;
            }

            window.Echo.channel(`room.${roomId}`)
                .listen('.QuestionBroadcast', (payload) => {
                    current.status = 'active';
                    current.question = payload.question;
                    current.questionIndex = payload.question_index;
                    current.total = payload.total_questions;
                    current.secondsPerQuestion = payload.seconds_per_question ?? current.secondsPerQuestion;
                    current.deadlineMs = payload.ends_at
                        ? Date.parse(payload.ends_at) + (Date.now() - Date.parse(payload.server_now))
                        : null;
                    current.answeredCount = 0;
                    current.optionCounts = payload.question.options.map(() => 0);
                    current.revealed = false;
                    current.correctOption = null;

                    renderedQuestionId = null;
                    revealRequested = false;
                    revealSoundPlayed = false;
                    lastTickSecond = null;
                    finishedRendered = false;

                    showOnly('question');
                    renderQuestion();
                    startCountdown();
                })
                .listen('.ScoreboardUpdated', (payload) => {
                    current.ranking = payload.ranking ?? current.ranking;
                    current.participantsCount = payload.participants_count ?? current.participantsCount;
                    current.answeredCount = payload.answered_count ?? current.answeredCount;
                    current.optionCounts = payload.option_counts ?? current.optionCounts;
                    current.status = payload.status;

                    document.getElementById('screen-participants').textContent = current.participantsCount;

                    if (payload.status === 'finished') {
                        stopCountdown();
                        showOnly('finished');
                        renderFinished();
                    } else if (payload.status === 'waiting') {
                        stopCountdown();
                        finishedRendered = false;
                        showOnly('waiting');
                    } else if (current.question) {
                        updateCounts();
                    }
                });
        }

        document.getElementById('sound-toggle').addEventListener('click', (event) => {
            soundEnabled = !soundEnabled;

            if (soundEnabled) {
                window.triviaSounds?.unlock();
                event.currentTarget.textContent = '🔇 Silenciar';
            } else {
                event.currentTarget.textContent = '🔊 Activar sonido';
            }
        });

        function init() {
            subscribeToRoom();
            refresh();
            setInterval(refresh, 4000);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    </script>
</body>
</html>

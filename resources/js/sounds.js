/**
 * Minimal Web Audio helpers so the projector has feedback without audio files.
 * Browsers suspend audio until a user gesture, hence the explicit enable step.
 */
let audioContext = null;

function context() {
    if (typeof window === 'undefined') {
        return null;
    }

    const AudioContextClass = window.AudioContext || window.webkitAudioContext;

    if (!AudioContextClass) {
        return null;
    }

    audioContext ??= new AudioContextClass();

    return audioContext;
}

export function unlock() {
    const ctx = context();

    if (ctx && ctx.state === 'suspended') {
        ctx.resume();
    }
}

function tone(frequency, duration, { type = 'sine', gain = 0.06, delay = 0 } = {}) {
    const ctx = context();

    if (!ctx) {
        return;
    }

    const start = ctx.currentTime + delay;
    const oscillator = ctx.createOscillator();
    const amplifier = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, start);

    amplifier.gain.setValueAtTime(0.0001, start);
    amplifier.gain.exponentialRampToValueAtTime(gain, start + 0.02);
    amplifier.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    oscillator.connect(amplifier).connect(ctx.destination);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.02);
}

export function playTick() {
    tone(880, 0.08, { type: 'triangle', gain: 0.05 });
}

export function playReveal() {
    tone(523.25, 0.18);
    tone(659.25, 0.18, { delay: 0.12 });
    tone(783.99, 0.3, { delay: 0.24 });
}

export function playFanfare() {
    [523.25, 659.25, 783.99, 1046.5].forEach((frequency, index) => {
        tone(frequency, 0.35, { delay: index * 0.15, gain: 0.07, type: 'triangle' });
    });
}

/**
 * Tiny dependency-free confetti burst used when the final podium is shown.
 */
export function celebrate({ duration = 4500, particles = 170 } = {}) {
    if (typeof document === 'undefined') {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:60';
    document.body.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const width = window.innerWidth;
    const height = window.innerHeight;

    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);

    const colors = ['#f43f5e', '#38bdf8', '#f59e0b', '#22c55e', '#a855f7', '#e2e8f0'];

    const pieces = Array.from({ length: particles }, () => ({
        x: Math.random() * width,
        y: -20 - Math.random() * height * 0.4,
        vx: (Math.random() - 0.5) * 2.4,
        vy: 2 + Math.random() * 3,
        size: 5 + Math.random() * 7,
        rotation: Math.random() * Math.PI,
        spin: (Math.random() - 0.5) * 0.2,
        color: colors[Math.floor(Math.random() * colors.length)],
    }));

    const startedAt = performance.now();
    let frame = null;

    function draw(now) {
        ctx.clearRect(0, 0, width, height);

        pieces.forEach((piece) => {
            piece.x += piece.vx;
            piece.y += piece.vy;
            piece.vy += 0.03;
            piece.rotation += piece.spin;

            ctx.save();
            ctx.translate(piece.x, piece.y);
            ctx.rotate(piece.rotation);
            ctx.fillStyle = piece.color;
            ctx.fillRect(-piece.size / 2, -piece.size / 2, piece.size, piece.size * 0.6);
            ctx.restore();
        });

        if (now - startedAt < duration) {
            frame = requestAnimationFrame(draw);
        } else {
            cancelAnimationFrame(frame);
            canvas.remove();
        }
    }

    frame = requestAnimationFrame(draw);
}

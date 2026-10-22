import QRCode from 'qrcode';

import { celebrate } from './confetti';
import * as sounds from './sounds';
import './echo';

window.celebrate = celebrate;
window.triviaSounds = sounds;

/**
 * Build the absolute join URL from the current origin, so a QR scanned from a
 * phone works even when the panel is opened through the LAN IP.
 */
function absoluteJoinUrl(path) {
    return new URL(path, window.location.origin).toString();
}

/**
 * Draw the join QR shown in the admin dashboard and projector.
 */
function renderJoinQr() {
    const canvas = document.getElementById('join-qr');

    if (!canvas || !canvas.dataset.joinPath) {
        return;
    }

    const joinUrl = absoluteJoinUrl(canvas.dataset.joinPath);
    const label = document.getElementById('join-url');

    if (label) {
        label.textContent = joinUrl;
    }

    QRCode.toCanvas(canvas, joinUrl, {
        width: canvas.width || 180,
        margin: 2,
        color: {
            dark: '#020617',
            light: '#ffffff',
        },
    }).catch((error) => {
        console.error('No se pudo generar el código QR:', error);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderJoinQr);
} else {
    renderJoinQr();
}

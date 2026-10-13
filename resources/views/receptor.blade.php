<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptor · Cielo de mensajes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            background: linear-gradient(#3fa9f5 0%, #8fd0f7 55%, #d6efff 100%);
        }

        /* ---------- Cielo de fondo ---------- */
        .cielo {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
        }

        .cielo svg { width: 100%; height: 100%; display: block; }

        .nube {
            transform-box: fill-box;
            animation: deriva 22s ease-in-out infinite alternate;
        }

        .nube--2 { animation-duration: 28s; animation-delay: -7s; }
        .nube--3 { animation-duration: 34s; animation-delay: -14s; }

        @keyframes deriva {
            from { transform: translateX(-22px); }
            to   { transform: translateX(30px); }
        }

        /* Aves decorativas con aleteo */
        .pajaro .vuelo {
            transform-box: fill-box;
            transform-origin: 50% 100%;
            animation: planeo 3.4s ease-in-out infinite;
        }

        @keyframes planeo {
            0%, 100% { transform: translateY(0) rotate(-5deg); }
            50%      { transform: translateY(-8px) rotate(5deg); }
        }

        .pajaro .a1 {
            transform-box: fill-box;
            transform-origin: 100% 100%;
            animation: aleteoA .55s linear infinite alternate;
        }

        @keyframes aleteoA {
            from { transform: rotate(8deg); }
            to   { transform: rotate(-34deg); }
        }

        .pajaro .a2 {
            transform-box: fill-box;
            transform-origin: 0% 100%;
            animation: aleteoB .55s linear infinite alternate;
        }

        @keyframes aleteoB {
            from { transform: rotate(-8deg); }
            to   { transform: rotate(34deg); }
        }

        /* ---------- Capa de juego ---------- */
        .efecto {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .badge {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 30;
            margin: 0;
            padding: 6px 18px;
            border-radius: 999px;
            background: rgba(10, 30, 60, .55);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .25);
            backdrop-filter: blur(6px);
            font-family: Impact, "Arial Black", system-ui, sans-serif;
            font-size: clamp(.8rem, 1.8vw, 1.05rem);
            letter-spacing: 2px;
            text-transform: uppercase;
            text-shadow: 0 2px 6px rgba(0, 0, 0, .5);
        }

        /* ---------- Avión que cruza el cielo ---------- */
        .avion {
            position: absolute;
            left: -45%;
            width: 660px;
            height: 175px;
            animation: vuelo 6s linear forwards;
            will-change: transform;
        }

        @keyframes vuelo {
            0%   { transform: translateX(0) translateY(0)      rotate(-1deg); }
            12%  { transform: translateX(26vw) translateY(-12px) rotate(1.2deg); }
            28%  { transform: translateX(58vw) translateY(10px) rotate(-1.2deg); }
            44%  { transform: translateX(90vw) translateY(-12px) rotate(1.2deg); }
            62%  { transform: translateX(128vw) translateY(8px) rotate(1deg); }
            100% { transform: translateX(175vw) translateY(0)  rotate(0deg); }
        }

        .avion .plano {
            position: absolute;
            right: 0;
            top: 6px;
            width: 330px;
            height: 160px;
            display: block;
        }

        .avion .plano svg { width: 100%; height: 100%; display: block; }

        .helice {
            transform-box: fill-box;
            transform-origin: center;
            animation: giro .28s linear infinite;
        }

        @keyframes giro {
            to { transform: rotate(360deg); }
        }

        /* Banderín con el mensaje (remolcado por el avión) */
        .banderin {
            position: absolute;
            left: 0;
            top: 30px;
            width: 330px;
            padding: 12px 14px 13px;
            border-radius: 12px;
            border: 4px solid #b51f1a;
            background: linear-gradient(180deg, #ffffff 0%, #e8f0f8 100%);
            color: #113a6b;
            text-align: center;
            box-shadow: 0 16px 34px rgba(10, 30, 60, .35);
            overflow-wrap: anywhere;
        }

        /* Bandera triangular decorativa del extremo izquierdo */
        .banderin::before {
            content: '';
            position: absolute;
            left: -22px;
            top: -7px;
            border: 12px solid transparent;
            border-right-color: #b51f1a;
        }

        .banderin .marca {
            display: block;
            font-family: Impact, "Arial Black", sans-serif;
            font-size: 14px;
            letter-spacing: 2px;
            font-weight: 900;
            color: #b51f1a;
            margin-bottom: 3px;
        }

        .banderin .texto {
            display: block;
            font-size: 14.5px;
            font-weight: 800;
            line-height: 1.3;
            color: #113a6b;
            overflow-wrap: anywhere;
        }

        /* ---------- Mensajes entregados (caen y quedan en tierra) ---------- */
        .galeria {
            position: fixed;
            left: 3%;
            bottom: 4%;
            width: min(340px, 42vw);
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 20;
            pointer-events: none;
        }

        .galeria .tarjeta {
            --giro: 0deg;
            position: relative;
            width: 100%;
            margin-top: -10px;
            padding: 10px 14px;
            border-radius: 10px;
            border: 3px solid #b51f1a;
            background: linear-gradient(180deg, #ffffff 0%, #f2f6fc 100%);
            color: #113a6b;
            text-align: center;
            box-shadow:
                0 14px 28px rgba(10, 30, 60, .35),
                inset 0 2px 0 rgba(255, 255, 255, .8);
            animation: caer 1.5s cubic-bezier(.22, 1, .32, 1) both;
        }

        .galeria .tarjeta:nth-child(1) { z-index: 20; }
        .galeria .tarjeta:nth-child(2) { z-index: 19; transform: translateY(10px) rotate(1.5deg); }
        .galeria .tarjeta:nth-child(3) { z-index: 18; transform: translateY(8px) rotate(-1.5deg); }
        .galeria .tarjeta:nth-child(4) { z-index: 17; transform: translateY(12px) rotate(1deg); }

        @keyframes caer {
            0%   { opacity: 0; transform: translateY(-70vh) translateX(8vw) rotate(-14deg); }
            20%  { opacity: 1; }
            62%  { transform: translateY(0) translateX(-3vw) rotate(5deg); }
            78%  { transform: translateY(-18px) translateX(2vw) rotate(-3deg); }
            100% { opacity: 1; transform: translateY(0) translateX(0) rotate(var(--giro)); }
        }

        .tarjeta .marca {
            display: block;
            font-family: Impact, "Arial Black", sans-serif;
            font-size: 15px;
            letter-spacing: 2px;
            font-weight: 900;
            color: #b51f1a;
            border-bottom: 2px dashed rgba(181, 31, 26, .35);
            padding-bottom: 3px;
            margin-bottom: 4px;
        }

        .tarjeta .texto {
            display: block;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.3;
            color: #113a6b;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body>
    <h1 class="badge">Aerolínea ACME · Mensajes del cielo</h1>

    <!-- Cielo con sol, nubes y aves -->
    <div class="cielo" aria-hidden="true">
        <svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice">
            <defs>
                <linearGradient id="azul" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#2e9bf0"/>
                    <stop offset="60%" stop-color="#8fd0f7"/>
                    <stop offset="100%" stop-color="#e4f6ff"/>
                </linearGradient>
                <radialGradient id="solG" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#fffbe0"/>
                    <stop offset="55%" stop-color="#fff3a8" stop-opacity=".9"/>
                    <stop offset="100%" stop-color="#fff3a8" stop-opacity="0"/>
                </radialGradient>
                <linearGradient id="hierba" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#8fca6a"/>
                    <stop offset="100%" stop-color="#5f9c47"/>
                </linearGradient>
                <linearGradient id="hierba2" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#6db451"/>
                    <stop offset="100%" stop-color="#3f7d33"/>
                </linearGradient>

                <!-- Ave volando (dos alas en "M" con aleteo) -->
                <g id="pajaro">
                    <g class="vuelo">
                        <path class="a1" d="M0 0 C 12 -14, 26 -20, 42 -16"
                              stroke="#23324a" stroke-width="4" fill="none" stroke-linecap="round"/>
                        <path class="a2" d="M0 0 C -12 -14, -26 -20, -42 -16"
                              stroke="#23324a" stroke-width="4" fill="none" stroke-linecap="round"/>
                    </g>
                </g>
            </defs>

            <!-- Cielo -->
            <rect width="1600" height="900" fill="url(#azul)"/>

            <!-- Sol -->
            <circle cx="1350" cy="170" r="200" fill="url(#solG)"/>
            <circle cx="1350" cy="170" r="66" fill="#fff7c0"/>

            <!-- Nubes -->
            <g class="nube" fill="#ffffff" opacity=".95">
                <ellipse cx="260" cy="190" rx="95" ry="46"/>
                <ellipse cx="330" cy="160" rx="70" ry="42"/>
                <ellipse cx="200" cy="215" rx="62" ry="34"/>
            </g>
            <g class="nube nube--2" fill="#ffffff" opacity=".9">
                <ellipse cx="760" cy="120" rx="110" ry="52"/>
                <ellipse cx="850" cy="96" rx="80" ry="44"/>
                <ellipse cx="680" cy="140" rx="70" ry="38"/>
            </g>
            <g class="nube nube--3" fill="#ffffff" opacity=".85">
                <ellipse cx="1180" cy="330" rx="90" ry="42"/>
                <ellipse cx="1260" cy="310" rx="64" ry="34"/>
            </g>

            <!-- Colinas suaves en el horizonte -->
            <path d="M0 840 C 220 788 420 832 640 806 C 880 778 1080 836 1300 808 C 1440 792 1540 810 1600 800 L1600 900 L0 900 Z"
                  fill="url(#hierba)"/>
            <path d="M0 878 C 320 840 640 880 980 858 C 1240 842 1460 866 1600 852 L1600 900 L0 900 Z"
                  fill="url(#hierba2)"/>

            <!-- Aves decorativas distribuidas -->
            <use href="#pajaro" transform="translate(180,330) scale(1.1) rotate(6)"/>
            <use href="#pajaro" transform="translate(420,240) scale(.85) rotate(-4)" style="animation-delay:-1.2s"/>
            <use href="#pajaro" transform="translate(900,420) scale(1) rotate(8)"/>
            <use href="#pajaro" transform="translate(1120,260) scale(.8) rotate(-6)" style="animation-delay:-2.1s"/>
            <use href="#pajaro" transform="translate(620,500) scale(1.05) rotate(4)"/>
        </svg>
    </div>

    <!-- Capa de juego -->
    <div class="efecto" id="efecto">
        <div class="galeria" id="galeria"></div>
    </div>

    <!-- Plantilla de la avioneta -->
    <svg id="plantilla-avion" viewBox="0 0 330 160" style="display:none" aria-hidden="true">
        <defs>
            <linearGradient id="fusG" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#ffffff"/>
                <stop offset="60%" stop-color="#e8eef6"/>
                <stop offset="100%" stop-color="#c3cedb"/>
            </linearGradient>
            <linearGradient id="alaG" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#e94f43"/>
                <stop offset="100%" stop-color="#b51f1a"/>
            </linearGradient>
        </defs>

        <!-- Estabilizador horizontal (cola) -->
        <path d="M58 68 L24 60 L24 78 L60 74 Z" fill="#e0e6ee" stroke="#2b313c" stroke-width="2"/>
        <!-- Timón vertical -->
        <path d="M48 60 L32 22 L64 40 Z" fill="url(#alaG)" stroke="#2b313c" stroke-width="2"/>

        <!-- Ala superior lejana -->
        <path d="M104 40 L192 30 L198 50 L148 54 Z" fill="#aeb9c6" stroke="#2b313c" stroke-width="2"/>

        <!-- Fuselaje -->
        <ellipse cx="148" cy="72" rx="104" ry="19" fill="url(#fusG)" stroke="#2b313c" stroke-width="2.5"/>
        <!-- Faja decorativa -->
        <path d="M60 76 C 110 82, 180 82, 244 76 L244 68 L60 68 Z" fill="url(#alaG)"/>

        <!-- Cabina / parabrisas -->
        <ellipse cx="216" cy="60" rx="20" ry="12" fill="#aee3f7" stroke="#2b313c" stroke-width="2"/>
        <ellipse cx="224" cy="60" rx="10" ry="8" fill="#cfefff"/>

        <!-- Montantes del ala -->
        <path d="M126 70 L128 46 M180 72 L182 48" stroke="#7a8894" stroke-width="3" stroke-linecap="round"/>

        <!-- Ala superior cercana -->
        <path d="M100 44 L190 34 L198 56 L142 62 Z" fill="url(#alaG)" stroke="#2b313c" stroke-width="2"/>
        <!-- Borde de ataque claro -->
        <path d="M104 46 L188 38" stroke="rgba(255,255,255,.7)" stroke-width="3" fill="none"/>

        <!-- Tren de aterrizaje -->
        <path d="M118 90 L114 112" stroke="#3a3f47" stroke-width="5" stroke-linecap="round"/>
        <circle cx="114" cy="117" r="8" fill="#2b313c"/>
        <circle cx="114" cy="117" r="3" fill="#cfd8e3"/>
        <path d="M54 82 L52 96" stroke="#3a3f47" stroke-width="4" stroke-linecap="round"/>
        <circle cx="52" cy="99" r="4" fill="#2b313c"/>

        <!-- Hélice -->
        <g class="helice">
            <path d="M252 72 L260 46 L266 50 Z" fill="#7a8894"/>
            <path d="M252 72 L236 64 L240 56 Z" fill="#7a8894"/>
            <path d="M252 72 L266 86 L258 90 Z" fill="#7a8894"/>
        </g>
        <circle cx="252" cy="72" r="7" fill="#3a4150" stroke="#2b313c" stroke-width="1.5"/>
    </svg>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const efecto = document.getElementById('efecto');
            const galeria = document.getElementById('galeria');
            const plantilla = document.getElementById('plantilla-avion');

            const MAX_TARJETAS = 6;
            const CarrILES = [26, 34, 42];

            function quitarAvionActual() {
                const actual = efecto.querySelector('.avion');
                if (actual) actual.remove();
            }

            function crearBanderin(texto, estilo) {
                const banderin = document.createElement('div');
                banderin.className = 'banderin';

                const marca = document.createElement('span');
                marca.className = 'marca';
                marca.textContent = 'ACME® Airlines';

                const textoEl = document.createElement('span');
                textoEl.className = 'texto';
                textoEl.textContent = texto ?? '';

                if (estilo) {
                    banderin.style.cssText += ';' + estilo;
                }

                banderin.appendChild(marca);
                banderin.appendChild(textoEl);
                return banderin;
            }

            function crearTarjeta(texto, estilo) {
                const tarjeta = document.createElement('div');
                tarjeta.className = 'tarjeta';
                tarjeta.style.setProperty('--giro', (Math.random() * 6 - 3).toFixed(1) + 'deg');

                const marca = document.createElement('span');
                marca.className = 'marca';
                marca.textContent = 'ACME®';

                const textoEl = document.createElement('span');
                textoEl.className = 'texto';
                textoEl.textContent = texto ?? '';

                if (estilo) {
                    tarjeta.style.cssText += ';' + estilo;
                }

                tarjeta.appendChild(marca);
                tarjeta.appendChild(textoEl);
                galeria.prepend(tarjeta);

                while (galeria.children.length > MAX_TARJETAS) {
                    galeria.lastElementChild?.remove();
                }
            }

            function lanzarAvion(e) {
                quitarAvionActual();

                const avion = document.createElement('div');
                avion.className = 'avion';
                avion.style.top = CarrILES[Math.floor(Math.random() * CarrILES.length)] + '%';

                const plano = document.createElement('div');
                plano.className = 'plano';
                const svg = plantilla.cloneNode(true);
                svg.removeAttribute('id');
                svg.removeAttribute('style');
                svg.removeAttribute('aria-hidden');
                svg.setAttribute('role', 'img');
                svg.setAttribute('aria-label', 'Avioneta remolcando el mensaje');
                plano.appendChild(svg);

                const banderin = crearBanderin(e.newText, e.newStyle);

                avion.appendChild(plano);
                avion.appendChild(banderin);
                efecto.appendChild(avion);

                // El mensaje cae del avión en pleno vuelo y queda en tierra
                setTimeout(() => crearTarjeta(e.newText, e.newStyle), 1800);
                setTimeout(() => quitarAvionActual(), 6200);
            }

            // Suscripción al canal público y escucha del evento
            window.Echo.channel('style-channel')
                .listen('.RemoteStyleUpdate', function (e) {
                    // El punto (.) indica que se usa broadcastAs() en Laravel
                    lanzarAvion(e);
                });
        });
    </script>
</body>
</html>
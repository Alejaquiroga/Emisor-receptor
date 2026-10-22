#!/usr/bin/env bash

#
# Levanta la app de trivia y Reverb detrás de dos túneles rápidos de Cloudflare,
# prepara la sala por defecto con sus 10 preguntas, actualiza .env con los
# hostnames nuevos y reconstruye los assets de Vite.
#
# Uso:
#   bash scripts/tunnel.sh              # levanta todo, actualiza .env y compila
#   bash scripts/tunnel.sh --no-build   # igual, pero sin reconstruir assets
#
# Variables opcionales:
#   HTTP_PORT=8000   WS_PORT=8080   START_TIMEOUT=60
#
# En cada arranque se recrea la sala por defecto HTTPWS (PIN: demo) con sus
# 10 preguntas, lista para unirse.
#
# Los procesos quedan en primer plano: Ctrl+C detiene todo y limpia los túneles.
#

set -euo pipefail
set -m

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd -- "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_DIR"

HTTP_PORT="${HTTP_PORT:-8000}"
WS_PORT="${WS_PORT:-8080}"
START_TIMEOUT="${START_TIMEOUT:-60}"
LOG_DIR="${LOG_DIR:-$PROJECT_DIR/storage/logs/tunnel}"
BUILD_ASSETS=1

# Deben coincidir con Database\Seeders\DefaultTriviaSeeder.
DEFAULT_ROOM_CODE="HTTPWS"
DEFAULT_ROOM_PIN="demo"

usage() {
    sed -n '/^# Uso:/,/^# Los procesos/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

for argument in "$@"; do
    case "$argument" in
        --no-build) BUILD_ASSETS=0 ;;
        -h|--help) usage; exit 0 ;;
        *)
            echo "Opción desconocida: $argument (usa --help)" >&2
            exit 1
            ;;
    esac
done

for command_name in php npm cloudflared curl; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Error: no se encontró '$command_name' en el PATH." >&2
        exit 1
    fi
done

if [[ ! -f .env ]]; then
    echo "Error: no existe .env. Copia .env.example y ejecuta php artisan key:generate." >&2
    exit 1
fi

if ! grep -qE '^REVERB_APP_ID=.+' .env || \
   ! grep -qE '^REVERB_APP_KEY=.+' .env || \
   ! grep -qE '^REVERB_APP_SECRET=.+' .env; then
    echo "Error: faltan REVERB_APP_ID, REVERB_APP_KEY o REVERB_APP_SECRET en .env." >&2
    echo "Genera esas credenciales con: php artisan reverb:install" >&2
    exit 1
fi

port_in_use() {
    (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
}

for port in "$HTTP_PORT" "$WS_PORT"; do
    if port_in_use "$port"; then
        echo "Error: el puerto $port ya está en uso." >&2
        echo "Detén el proceso que lo ocupa o usa otro puerto, por ejemplo:" >&2
        echo "  HTTP_PORT=8001 WS_PORT=8081 bash scripts/tunnel.sh" >&2
        exit 1
    fi
done

mkdir -p "$LOG_DIR"

HTTP_PID=""
WS_PID=""
SCHEDULER_PID=""
APP_TUNNEL_PID=""
WS_TUNNEL_PID=""

stop_process() {
    local pid="$1"

    if [[ -z "$pid" ]] || ! kill -0 "$pid" 2>/dev/null; then
        return
    fi

    # Cada job se lanza en su propio grupo de procesos (set -m), así que
    # matamos el grupo completo para no dejar hijos huérfanos.
    kill -TERM -- "-$pid" 2>/dev/null || kill -TERM "$pid" 2>/dev/null || true
}

cleanup() {
    trap - EXIT INT TERM

    echo ""
    echo "Deteniendo servidores y túneles..."

    stop_process "$APP_TUNNEL_PID"
    stop_process "$WS_TUNNEL_PID"
    stop_process "$WS_PID"
    stop_process "$SCHEDULER_PID"
    stop_process "$HTTP_PID"

    wait 2>/dev/null || true

    echo "Listo."
}

trap cleanup EXIT INT TERM

wait_for_port() {
    local port="$1"
    local label="$2"
    local pid="$3"
    local deadline=$((SECONDS + START_TIMEOUT))

    while (( SECONDS < deadline )); do
        if port_in_use "$port"; then
            return 0
        fi

        if ! kill -0 "$pid" 2>/dev/null; then
            echo "Error: $label terminó antes de abrir el puerto $port. Revisa $LOG_DIR." >&2
            return 1
        fi

        sleep 0.3
    done

    echo "Error: $label no abrió el puerto $port en ${START_TIMEOUT}s. Revisa $LOG_DIR." >&2
    return 1
}

wait_for_tunnel_url() {
    local log_file="$1"
    local pid="$2"
    local deadline=$((SECONDS + START_TIMEOUT))
    local url=""

    while (( SECONDS < deadline )); do
        url="$(grep -Eo 'https://[a-z0-9-]+\.trycloudflare\.com' "$log_file" 2>/dev/null | head -n 1 || true)"

        if [[ -n "$url" ]]; then
            printf '%s\n' "$url"
            return 0
        fi

        if ! kill -0 "$pid" 2>/dev/null; then
            echo "Error: cloudflared terminó sin entregar una URL. Revisa $log_file." >&2
            return 1
        fi

        sleep 0.5
    done

    echo "Error: no se obtuvo una URL de Cloudflare en ${START_TIMEOUT}s. Revisa $log_file." >&2
    return 1
}

update_env() {
    local app_url="$1"
    local ws_host="$2"

    APP_URL_VALUE="$app_url" WS_HOST_VALUE="$ws_host" php -r '
        $path = ".env";
        $contents = file_get_contents($path);

        if ($contents === false) {
            fwrite(STDERR, "No se pudo leer .env\n");
            exit(1);
        }

        $values = [
            "APP_URL" => getenv("APP_URL_VALUE"),
            "REVERB_HOST" => getenv("WS_HOST_VALUE"),
            "REVERB_PORT" => "443",
            "REVERB_SCHEME" => "https",
            "VITE_REVERB_HOST" => getenv("WS_HOST_VALUE"),
            "VITE_REVERB_PORT" => "443",
            "VITE_REVERB_SCHEME" => "https",
        ];

        $quotedKeys = ["REVERB_HOST", "VITE_REVERB_HOST"];

        foreach ($values as $key => $value) {
            $rendered = in_array($key, $quotedKeys, true)
                ? "\"{$value}\""
                : $value;

            $pattern = "/^" . preg_quote($key, "/") . "=.*$/m";

            if (preg_match($pattern, $contents) === 1) {
                $contents = preg_replace_callback(
                    $pattern,
                    fn () => $key . "=" . $rendered,
                    $contents
                );
            } else {
                $contents = rtrim($contents, "\n") . "\n" . $key . "=" . $rendered . "\n";
            }
        }

        if (file_put_contents($path, $contents) === false) {
            fwrite(STDERR, "No se pudo escribir .env\n");
            exit(1);
        }
    '
}

echo "Iniciando servidor web en 127.0.0.1:$HTTP_PORT ..."
php artisan serve --host=127.0.0.1 --port="$HTTP_PORT" > "$LOG_DIR/serve.log" 2>&1 &
HTTP_PID=$!

echo "Iniciando Reverb en 127.0.0.1:$WS_PORT ..."
php artisan reverb:start --host=127.0.0.1 --port="$WS_PORT" > "$LOG_DIR/reverb.log" 2>&1 &
WS_PID=$!

wait_for_port "$HTTP_PORT" "el servidor web" "$HTTP_PID"
wait_for_port "$WS_PORT" "Reverb" "$WS_PID"

echo "Abriendo túnel para la app ..."
cloudflared tunnel --url "http://127.0.0.1:$HTTP_PORT" --no-autoupdate > "$LOG_DIR/app-tunnel.log" 2>&1 &
APP_TUNNEL_PID=$!

echo "Abriendo túnel para el WebSocket ..."
cloudflared tunnel --url "http://127.0.0.1:$WS_PORT" --no-autoupdate > "$LOG_DIR/ws-tunnel.log" 2>&1 &
WS_TUNNEL_PID=$!

APP_URL="$(wait_for_tunnel_url "$LOG_DIR/app-tunnel.log" "$APP_TUNNEL_PID")"
WS_URL="$(wait_for_tunnel_url "$LOG_DIR/ws-tunnel.log" "$WS_TUNNEL_PID")"
WS_HOST="${WS_URL#https://}"

if [[ -z "$APP_URL" || -z "$WS_HOST" ]]; then
    echo "Error: no se pudieron leer las URLs de los túneles." >&2
    exit 1
fi

echo "Actualizando .env ..."
update_env "$APP_URL" "$WS_HOST"

echo "Limpiando caché de configuración ..."
php artisan config:clear --no-interaction > /dev/null

echo "Preparando base de datos y sala por defecto ..."

if ! php artisan migrate --force --no-interaction > "$LOG_DIR/migrate.log" 2>&1; then
    echo "Error: falló php artisan migrate. Últimas líneas del log:" >&2
    tail -n 30 "$LOG_DIR/migrate.log" >&2
    exit 1
fi

if ! php artisan db:seed --class=DefaultTriviaSeeder --force --no-interaction; then
    echo "Error: no se pudo preparar la sala por defecto. Revisa los mensajes anteriores." >&2
    exit 1
fi

echo "Iniciando planificador de auto-avance ..."
php artisan schedule:work > "$LOG_DIR/scheduler.log" 2>&1 &
SCHEDULER_PID=$!

sleep 1

if ! kill -0 "$SCHEDULER_PID" 2>/dev/null; then
    echo "Error: el planificador no arrancó. Revisa $LOG_DIR/scheduler.log." >&2
    tail -n 20 "$LOG_DIR/scheduler.log" >&2
    exit 1
fi

if (( BUILD_ASSETS )); then
    echo "Reconstruyendo assets (Vite) ..."

    if ! npm run build > "$LOG_DIR/build.log" 2>&1; then
        echo "Error: falló npm run build. Últimas líneas del log:" >&2
        tail -n 30 "$LOG_DIR/build.log" >&2
        exit 1
    fi
fi

verify_public_url() {
    local url="$1"
    local attempt
    local status=""

    for attempt in 1 2 3 4; do
        status="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$url/trivia" || true)"

        if [[ "$status" == "200" ]]; then
            echo "Verificación: $url/trivia responde 200."
            return 0
        fi

        sleep 3
    done

    echo "Aviso: $url/trivia todavía no responde desde este equipo (último estado: ${status:-sin respuesta})." >&2
    echo "Los túneles rápidos pueden tardar en propagar DNS; prueba de nuevo en un minuto." >&2
}

echo ""
echo "================================================================"
echo " App:       $APP_URL"
echo " WebSocket: $WS_URL"
echo " Sala:      $DEFAULT_ROOM_CODE (PIN: $DEFAULT_ROOM_PIN)"
echo " Unirse:    $APP_URL/trivia/join?code=$DEFAULT_ROOM_CODE"
echo " Auto-avance: activo"
echo " Logs:      $LOG_DIR"
echo "================================================================"
echo ""

verify_public_url "$APP_URL"

echo "Presiona Ctrl+C para detener todo."
echo ""

wait -n "$HTTP_PID" "$WS_PID" "$SCHEDULER_PID" "$APP_TUNNEL_PID" "$WS_TUNNEL_PID"

echo "Uno de los procesos terminó; cerrando los demás." >&2

#!/usr/bin/env bash
# ==============================================================================
# Camargo PMS — Wrapper de Automatización iCalendar para Linux (CRON)
# Fase: AIRBNB-ICAL-1D2
# ==============================================================================
# Propósito:
#   Ejecutar periódicamente la sincronización de conexiones iCal debidas mediante
#   PHP CLI, garantizando rutas absolutas, prevención de solapamiento del scheduler
#   con flock y preservación del código de salida.
#
# Autoridad:
#   Cron del SO         = Disparador periódico (cada 5 minutos)
#   flock               = Prevención de solapamiento global del scheduler
#   --solo-debidas      = Autoridad temporal (evalúa frecuencia_minutos)
#   GET_LOCK MySQL      = Autoridad de concurrencia por conexión
#   MySQL / Dominio     = Autoridad soberana de estado e inventario
# ==============================================================================

set -euo pipefail

# 1. Resolución de Rutas Absolutas (con soporte para espacios en rutas)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROYECTO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Variables parametrizables (sobreescribibles mediante variables de entorno)
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
RUNNER_SCRIPT="${PROYECTO_DIR}/bin/sincronizar-ical.php"
LOCK_FILE="${LOCK_FILE:-/tmp/camargo_ical_scheduler.lock}"
LOG_DIR="${PROYECTO_DIR}/storage/logs"
LOG_FILE="${LOG_FILE:-${LOG_DIR}/ical_scheduler.log}"

# 2. Validación de Entorno Previa a Ejecución
if [[ ! -x "${PHP_BIN}" ]]; then
    # Fallback si /usr/bin/php no existe pero 'php' está en el PATH
    if command -v php >/dev/null 2>&1; then
        PHP_BIN="$(command -v php)"
    else
        echo "[ERROR] [$(date '+%Y-%m-%d %H:%M:%S')] Intérprete PHP no encontrado en '${PHP_BIN}' ni en PATH." >&2
        exit 1
    fi
fi

if [[ ! -f "${RUNNER_SCRIPT}" ]]; then
    echo "[ERROR] [$(date '+%Y-%m-%d %H:%M:%S')] Script de ejecución no encontrado en '${RUNNER_SCRIPT}'." >&2
    exit 1
fi

mkdir -p "${LOG_DIR}"

# 3. Prevención de Solapamiento Global mediante flock (sin reemplazar GET_LOCK MySQL)
# Abre el descriptor de archivo 200 apuntando al LOCK_FILE
exec 200>"${LOCK_FILE}"

if ! flock -n 200; then
    # Si otra instancia del scheduler sigue en ejecución, salir limpiamente sin acumular procesos
    echo "[INFO] [$(date '+%Y-%m-%d %H:%M:%S')] Tarea scheduler previa aún en ejecución (lock activo en ${LOCK_FILE}). Omitiendo ciclo." >> "${LOG_FILE}"
    exit 0
fi

# 4. Invocación Segura mediante PHP CLI (sin credenciales por argumentos ni entorno HTTP)
# Captura de código de salida para propagación exacta
EXIT_CODE=0
"${PHP_BIN}" "${RUNNER_SCRIPT}" --solo-debidas --quiet >> "${LOG_FILE}" 2>&1 || EXIT_CODE=$?

# Liberación explícita del lock descriptor
flock -u 200 2>/dev/null || true

exit "${EXIT_CODE}"

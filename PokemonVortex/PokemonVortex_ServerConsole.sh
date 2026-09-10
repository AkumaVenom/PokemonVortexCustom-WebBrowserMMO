#!/usr/bin/env sh
# Local console supervisor. Commands remain separate arguments; no shell eval.
set -eu
PV_CONSOLE_DIR=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
PV_CONSOLE_PHP=${PV_PHP_EXE:-php}
if ! command -v "$PV_CONSOLE_PHP" >/dev/null 2>&1; then
    printf '%s\n' 'PHP CLI was not found. Install PHP CLI or set PV_PHP_EXE to its path.' >&2
    exit 1
fi
export PV_CONSOLE_SUPERVISED=1
while :; do
    set +e
    "$PV_CONSOLE_PHP" -d display_errors=0 -d display_startup_errors=0 "$PV_CONSOLE_DIR/server_console.php" "$@"
    PV_CONSOLE_STATUS=$?
    set -e
    if [ "$PV_CONSOLE_STATUS" -ne 75 ]; then exit "$PV_CONSOLE_STATUS"; fi
done

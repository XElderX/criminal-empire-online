#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"
PHP_BIN="$(command -v php)"
CRONTAB_BIN="$(command -v crontab || true)"

if ! command -v gnome-terminal >/dev/null 2>&1; then
  echo "gnome-terminal is required but was not found."
  exit 1
fi

if [[ -z "${PHP_BIN}" ]]; then
  echo "php was not found in PATH."
  exit 1
fi

install_cron() {
  if [[ -z "${CRONTAB_BIN}" ]]; then
    echo "crontab was not found; skipping cron setup."
    return
  fi

  local temp_file
  temp_file="$(mktemp)"

  if crontab -l >/dev/null 2>&1; then
    crontab -l | sed '/^# BEGIN Criminal Empire Online$/,/^# END Criminal Empire Online$/d' > "$temp_file"
  else
    : > "$temp_file"
  fi

  cat <<EOF >> "$temp_file"

# BEGIN Criminal Empire Online
0 * * * * cd '$BACKEND_DIR' && '$PHP_BIN' commands/world.php process-hour >> /tmp/criminal-world-hour.log 2>&1
5 0 * * * cd '$BACKEND_DIR' && '$PHP_BIN' commands/world.php process-day >> /tmp/criminal-world-day.log 2>&1
10 0 * * 1 cd '$BACKEND_DIR' && '$PHP_BIN' commands/world.php process-week >> /tmp/criminal-world-week.log 2>&1
45 * * * * cd '$BACKEND_DIR' && '$PHP_BIN' commands/dirty-jobs.php expire >> /tmp/criminal-dirty-expire.log 2>&1
0 2 * * * cd '$BACKEND_DIR' && '$PHP_BIN' commands/dirty-jobs.php refresh >> /tmp/criminal-dirty-refresh.log 2>&1
# END Criminal Empire Online
EOF

  crontab "$temp_file"
  rm -f "$temp_file"
}

install_cron

gnome-terminal --title="Criminal Empire Backend" -- bash -lc "cd '$BACKEND_DIR' && php -S 127.0.0.1:8085 -t public public/index.php; exec bash" &
gnome-terminal --title="Criminal Empire Frontend" -- bash -lc "cd '$FRONTEND_DIR' && npm run dev -- --host 127.0.0.1 --port 5175 --strictPort; exec bash" &

wait

#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"

if ! command -v gnome-terminal >/dev/null 2>&1; then
  echo "gnome-terminal is required but was not found."
  exit 1
fi

gnome-terminal --title="Criminal Empire Backend" -- bash -lc "cd '$BACKEND_DIR' && php -S 127.0.0.1:8085 -t public public/index.php; exec bash" &
gnome-terminal --title="Criminal Empire Frontend" -- bash -lc "cd '$FRONTEND_DIR' && npm run dev -- --host 127.0.0.1 --port 5175 --strictPort; exec bash" &

wait

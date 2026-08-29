#!/usr/bin/env bash
# ┌─────────────────────────────────────────────────────────────────┐
# │  AI Token Optimizer Proxy — Start Script                        │
# │  Listens on port 3100 and intercepts all Claude/Gemini API calls│
# └─────────────────────────────────────────────────────────────────┘
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PID_FILE="$PROJECT_DIR/data/proxy.pid"
LOG_FILE="$PROJECT_DIR/data/proxy.log"
PORT=3100

mkdir -p "$PROJECT_DIR/data"

# Kill existing proxy instance
if [ -f "$PID_FILE" ]; then
    OLD_PID=$(cat "$PID_FILE")
    if kill -0 "$OLD_PID" 2>/dev/null; then
        kill "$OLD_PID"
        echo "⛔ Stopped previous proxy (PID $OLD_PID)"
    fi
    rm -f "$PID_FILE"
fi

# Also kill by pattern
pkill -f "php.*proxy\.php" 2>/dev/null || true

sleep 0.5

# Start proxy
nohup php -S "0.0.0.0:$PORT" "$SCRIPT_DIR/proxy.php" > "$LOG_FILE" 2>&1 &
PROXY_PID=$!
echo "$PROXY_PID" > "$PID_FILE"

sleep 0.8

# Verify it started
if kill -0 "$PROXY_PID" 2>/dev/null; then
    echo "✅ AI Token Optimizer Proxy running on port $PORT (PID: $PROXY_PID)"
    echo "   Log: $LOG_FILE"
    echo ""
    echo "   To activate, add these to your shell (~/.bashrc or ~/.profile):"
    echo "   export ANTHROPIC_BASE_URL=http://localhost:$PORT"
    echo "   export ANTHROPIC_API_BASE=http://localhost:$PORT"
    echo "   export GOOGLE_GENERATIVE_AI_API_BASE=http://localhost:$PORT"
    echo ""
    echo "   Then restart your terminal or run: source ~/.bashrc"
else
    echo "❌ Proxy failed to start. Check: $LOG_FILE"
    exit 1
fi

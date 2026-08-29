#!/usr/bin/env bash
# AI Token Optimizer — Start Dashboard Server
# Portable: works on any Linux/macOS with PHP 7.4+

PORT=${1:-8080}
HOST="0.0.0.0"
DIR="$(cd "$(dirname "$0")" && pwd)"

# --- PHP check with distro-agnostic install hint ---
if ! command -v php &>/dev/null; then
    echo "❌ PHP not found. Install it with:"
    echo "   Ubuntu/Debian : sudo apt install php-cli"
    echo "   Fedora/RHEL   : sudo dnf install php-cli"
    echo "   Arch          : sudo pacman -S php"
    echo "   macOS         : brew install php"
    exit 1
fi

# --- Port conflict detection ---
if command -v ss &>/dev/null; then
    if ss -tlnp 2>/dev/null | grep -q ":${PORT} "; then
        echo "⚠️  Port ${PORT} is already in use."
        echo "   Try: ./start_server.sh 8081"
        exit 1
    fi
elif command -v lsof &>/dev/null; then
    if lsof -i ":${PORT}" &>/dev/null; then
        echo "⚠️  Port ${PORT} is already in use."
        echo "   Try: ./start_server.sh 8081"
        exit 1
    fi
fi

# --- Ensure data directory is writable ---
if [ ! -d "${DIR}/data" ]; then
    mkdir -p "${DIR}/data"
fi
if [ ! -w "${DIR}/data" ]; then
    echo "⚠️  data/ directory is not writable. Fixing permissions..."
    chmod 755 "${DIR}/data"
fi

PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
HOSTNAME_DISPLAY="$(hostname 2>/dev/null || echo 'localhost')"

echo "============================================================"
echo "🚀 AI Token Optimizer — Dashboard Server"
echo "   Universal Token Optimization for AI Dev Agents"
echo "============================================================"
echo "📍 URL    : http://localhost:${PORT}"
echo "📁 Root   : ${DIR}"
echo "🖥️  Machine : ${HOSTNAME_DISPLAY} | PHP ${PHP_VERSION}"
echo "🔄 Stop   : Ctrl+C"
echo "============================================================"
echo ""

php -S "${HOST}:${PORT}" -t "${DIR}"

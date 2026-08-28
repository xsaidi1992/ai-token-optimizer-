#!/usr/bin/env bash
# AI Token Optimizer — Start Dashboard Server

PORT=${1:-8080}
HOST="0.0.0.0"
DIR="$(cd "$(dirname "$0")" && pwd)"

if ! command -v php &>/dev/null; then
    echo "❌ PHP is required. Install: sudo apt install php-cli"
    exit 1
fi

echo "============================================================"
echo "🚀 AI Token Optimizer — Dashboard Server"
echo "   Universal Token Optimization for AI Dev Agents"
echo "============================================================"
echo "📍 URL:  http://localhost:${PORT}"
echo "📁 Root: ${DIR}"
echo "🖥️  Host: $(hostname) | PHP $(php -v | head -1 | awk '{print $2}')"
echo "============================================================"

php -S ${HOST}:${PORT} -t "$DIR"

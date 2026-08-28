#!/usr/bin/env bash
set -euo pipefail

echo "============================================================"
echo "🚀 AI Token Optimizer — Installation"
echo "   Universal Token Optimization Suite for AI Dev Agents"
echo "============================================================"
echo ""

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BIN_DIR="${HOME}/bin"

# Check dependencies
echo "📋 Checking dependencies..."

if ! command -v php &>/dev/null; then
    echo "❌ PHP is required. Install: sudo apt install php-cli"
    exit 1
fi
echo "  ✅ PHP $(php -v | head -1 | awk '{print $2}')"

if command -v rg &>/dev/null; then
    echo "  ✅ ripgrep $(rg --version | head -1 | awk '{print $2}')"
else
    echo "  ⚠️  ripgrep not found. Recommended: sudo apt install ripgrep"
fi

# Install CLI tools
echo ""
echo "📦 Installing CLI tools to ${BIN_DIR}/..."
mkdir -p "$BIN_DIR"

for tool in ai-token-init ai-context ai-noise-audit; do
    src="${SCRIPT_DIR}/tools/${tool}"
    dst="${BIN_DIR}/${tool}"
    if [ -f "$src" ]; then
        cp "$src" "$dst"
        chmod +x "$dst"
        echo "  ✅ ${tool} → ${dst}"
    fi
done

# Ensure ~/bin is in PATH
if ! echo "$PATH" | tr ':' '\n' | grep -qx "$BIN_DIR"; then
    echo ""
    echo "⚠️  Add ~/bin to your PATH:"
    echo "    echo 'export PATH=\"\$HOME/bin:\$PATH\"' >> ~/.bashrc"
    echo "    source ~/.bashrc"
fi

# Deploy optimization rules to current workspace
echo ""
echo "⚙️  Deploying ignore patterns to current workspace..."
bash "${SCRIPT_DIR}/tools/ai-token-init" "${SCRIPT_DIR}"

echo ""
echo "============================================================"
echo "✅ Installation complete!"
echo ""
echo "🌐 Start the dashboard:"
echo "    cd ${SCRIPT_DIR}"
echo "    ./start_server.sh"
echo ""
echo "📍 Then open: http://localhost:8080"
echo "============================================================"

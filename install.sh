#!/usr/bin/env bash
set -euo pipefail

echo "============================================================"
echo "🚀 AI Token Optimizer — Installation"
echo "   Universal Token Optimization Suite for AI Dev Agents"
echo "============================================================"
echo ""

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BIN_DIR="${HOME}/bin"

# ── PHP ──────────────────────────────────────────────────────────
if ! command -v php &>/dev/null; then
    echo "❌ PHP is required but not found. Install it:"
    echo "   Ubuntu/Debian : sudo apt install php-cli php-sqlite3"
    echo "   Fedora/RHEL   : sudo dnf install php-cli php-sqlite3"
    echo "   Arch          : sudo pacman -S php"
    echo "   macOS         : brew install php"
    exit 1
fi
echo "  ✅ PHP $(php -r 'echo PHP_VERSION;')"

# ── SQLite FTS5 (optional but recommended) ────────────────────────
if php -m 2>/dev/null | grep -qi sqlite; then
    echo "  ✅ SQLite3 FTS5 Memory Engine enabled"
else
    echo "  ⚠️  SQLite3 extension not found — attempting install..."
    if command -v apt-get &>/dev/null; then
        sudo apt-get install -y -qq php-sqlite3 sqlite3 2>/dev/null || true
    elif command -v dnf &>/dev/null; then
        sudo dnf install -y php-sqlite3 sqlite 2>/dev/null || true
    elif command -v pacman &>/dev/null; then
        sudo pacman -S --noconfirm php-sqlite sqlite 2>/dev/null || true
    elif command -v brew &>/dev/null; then
        brew install php 2>/dev/null || true   # macOS: sqlite bundled with php
    fi
    # Re-check
    if php -m 2>/dev/null | grep -qi sqlite; then
        echo "  ✅ SQLite3 installed successfully"
    else
        echo "  ⚠️  SQLite3 unavailable. Using JSON memory fallback (fully functional)."
    fi
fi

# ── ripgrep (optional, used by ai-context tool) ───────────────────
if command -v rg &>/dev/null; then
    echo "  ✅ ripgrep $(rg --version | head -1 | awk '{print $2}')"
else
    echo "  ⚠️  ripgrep not found (optional). Install for faster workspace audits:"
    echo "      Ubuntu/Debian: sudo apt install ripgrep"
    echo "      Fedora       : sudo dnf install ripgrep"
    echo "      Arch         : sudo pacman -S ripgrep"
    echo "      macOS        : brew install ripgrep"
fi

# ── Ensure data/ directory exists and is writable ─────────────────
mkdir -p "${SCRIPT_DIR}/data"
chmod 755 "${SCRIPT_DIR}/data"

# ── CLI tools → ~/bin ─────────────────────────────────────────────
echo ""
echo "📦 Installing CLI tools to ${BIN_DIR}/..."
mkdir -p "${BIN_DIR}"

for tool in ai-token-init ai-context ai-noise-audit; do
    src="${SCRIPT_DIR}/tools/${tool}"
    dst="${BIN_DIR}/${tool}"
    if [ -f "${src}" ]; then
        cp "${src}" "${dst}"
        chmod +x "${dst}"
        echo "  ✅ ${tool} → ${dst}"
    else
        echo "  ⚠️  tools/${tool} not found — skipping"
    fi
done

# ── PATH hint if ~/bin not in PATH ───────────────────────────────
if ! echo "${PATH}" | tr ':' '\n' | grep -qx "${BIN_DIR}"; then
    echo ""
    echo "⚠️  ~/bin is not in your PATH. Add it with:"
    echo "    echo 'export PATH=\"\$HOME/bin:\$PATH\"' >> ~/.bashrc && source ~/.bashrc"
    echo "   (Replace .bashrc with .zshrc, .profile, or .bash_profile as needed)"
fi

# ── Deploy ignore files & init FTS5 memory ───────────────────────
echo ""
echo "⚙️  Deploying ignore patterns & initializing SQLite FTS5 memory..."
bash "${SCRIPT_DIR}/tools/ai-token-init" "${SCRIPT_DIR}"
php -r "require_once '${SCRIPT_DIR}/memory_indexer.php'; new MemoryIndexer();" 2>/dev/null || true
echo "  ✅ Episodic Memory initialized"

# ── Proxy env vars → shell rc files ──────────────────────────────
echo ""
echo "🔌 Configuring Token Optimizer Proxy (port 3100)..."
chmod +x "${SCRIPT_DIR}/proxy/start_proxy.sh"

PROXY_MARKER="# AI Token Optimizer Proxy"
PROXY_VARS="$PROXY_MARKER"$'\n'"export ANTHROPIC_BASE_URL=http://localhost:3100"$'\n'"export ANTHROPIC_API_BASE=http://localhost:3100"$'\n'"export GOOGLE_GENERATIVE_AI_API_BASE=http://localhost:3100"

for RC_FILE in "${HOME}/.bashrc" "${HOME}/.zshrc" "${HOME}/.profile"; do
    if [ -f "$RC_FILE" ] && ! grep -q 'ANTHROPIC_BASE_URL' "$RC_FILE" 2>/dev/null; then
        echo "" >> "$RC_FILE"
        echo "$PROXY_VARS" >> "$RC_FILE"
        echo "  ✅ Proxy env vars added to $(basename $RC_FILE)"
    fi
done

# Apply in current shell immediately
export ANTHROPIC_BASE_URL=http://localhost:3100
export ANTHROPIC_API_BASE=http://localhost:3100
export GOOGLE_GENERATIVE_AI_API_BASE=http://localhost:3100

# Start proxy now
bash "${SCRIPT_DIR}/proxy/start_proxy.sh" || true

echo ""
echo "============================================================"
echo "✅ Installation complete!"
echo ""
echo "🔌 Proxy  : http://localhost:3100  (intercepts all API calls)"
echo "🌐 Dashboard:"
echo "    cd ${SCRIPT_DIR} && ./start_server.sh"
echo ""
echo "📍 Open   : http://localhost:8080"
echo ""
echo "⚠️  IMPORTANT: run 'source ~/.bashrc' then restart Antigravity"
echo "   so it picks up ANTHROPIC_BASE_URL=http://localhost:3100"
echo "============================================================"

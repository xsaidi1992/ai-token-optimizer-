#!/usr/bin/env bash
# ┌────────────────────────────────────────────────────────────────────────────┐
# │  AI Token Optimizer Proxy — Session Activator                              │
# │  Source this file to redirect ALL IDE/SDK calls through the local proxy.  │
# │                                                                            │
# │  Usage:                                                                    │
# │    source proxy/activate_proxy.sh          # current session               │
# │    # OR add the exports below to ~/.bashrc # permanent                     │
# └────────────────────────────────────────────────────────────────────────────┘

PROXY="http://localhost:3100"

# ── Anthropic (Claude Code CLI, Antigravity, Cursor Claude mode) ─────────────
export ANTHROPIC_BASE_URL="$PROXY"
export ANTHROPIC_API_BASE="$PROXY"

# ── OpenAI-compatible IDEs ────────────────────────────────────────────────────
# Cursor, VS Code Copilot, Aider, Cline, Codex CLI, Windsurf, Zed, JetBrains AI
export OPENAI_BASE_URL="$PROXY"
export OPENAI_API_BASE="$PROXY"
export OPENAI_API_HOST="$PROXY"

# ── Google Gemini (Gemini CLI, Antigravity Gemini mode) ──────────────────────
export GOOGLE_GENERATIVE_AI_API_BASE="$PROXY"
export GEMINI_API_BASE_URL="$PROXY"

# ── Aider specific ───────────────────────────────────────────────────────────
export AIDER_OPENAI_API_BASE="$PROXY"

# ── Cline / Roo-Cline ────────────────────────────────────────────────────────
export CLINE_API_BASE_URL="$PROXY"

# ── Windsurf / Codeium ───────────────────────────────────────────────────────
export CODEIUM_API_BASE_URL="$PROXY"

# ── GitHub Copilot CLI (best-effort — Copilot uses its own auth layer) ───────
export GITHUB_COPILOT_PROXY="$PROXY"

echo "✅ AI Token Optimizer Proxy activé sur $PROXY"
echo "   Toutes les requêtes Anthropic / OpenAI / Gemini passent par le proxy."
echo "   Vérification : curl $PROXY/health"

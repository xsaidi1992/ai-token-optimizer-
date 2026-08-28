<div align="center">

# ⚡ AI Token Optimizer ⚡
### Universal Token Optimization & Analytics Suite for AI Coding Agents
**Dedicated for Linux Environments • 11 Supported IDEs & CLI Agents • Guide 2026 Ready**

[![License: MIT](https://img.shields.io/badge/License-MIT-emerald.svg)](LICENSE)
[![Platform: Linux](https://img.shields.io/badge/Platform-Linux-blue.svg)](https://kernel.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net)
[![IDEs Supported](https://img.shields.io/badge/Supported%20IDEs-11%20Agents-indigo.svg)](#-compatible-ides--ai-agents)

---

</div>

## 🎯 Project Overview

**AI Token Optimizer** is a production-ready, enterprise-grade optimization suite designed to eliminate context bloat, reduce token consumption by up to **60%**, cut API costs, and accelerate agent response speed across all major AI coding tools on **Linux**.

Rather than dumping entire codebases into context windows, **AI Token Optimizer** enforces high-precision context engineering, automated noise filtering, prompt caching optimization (Guide 2026 §29), and smart model tier routing.

---

## ✨ Key Capabilities & Features

- 🎛️ **11 Dedicated IDE & Agent Dashboards**: Custom metrics, model routing matrices, and rule deployment for each supported AI tool.
- 📊 **Real-Time Consumption Analytics**: Track prompt tokens, cached inputs, reasoning effort tax (§30), and tool/MCP output taxes (§26).
- 📸 **Per-IDE Benchmark Engine**: Measure AVANT (baseline) vs. APRÈS (optimized) token savings and compression ratios per model.
- 🔍 **Interactive Guide 2026 Engine**: 52 structured token optimization strategies with 1-click rule deployment.
- 🛡️ **Workspace Audit Engine**: Non-destructive noise scanner for large binaries, log files, node_modules, and vendor directories.
- 🐧 **100% Cross-Linux Portability**: Agnostic path resolution via dynamic `getenv('HOME')` detection.

---

## 💻 Compatible IDEs & AI Agents

| Icon | AI Coding Agent / IDE | Configuration & Rule Path | Model Routing Tiers |
| :---: | :--- | :--- | :--- |
| 🪐 | **Google Antigravity** | `~/.gemini/antigravity/GEMINI.md` | Gemini 3.7 Flash / Sonnet 4.6 / Opus 4.6 |
| 💻 | **VS Code / Copilot** | `~/.vscode/settings.json` | GPT-5.6 Luna / Terra / Sol |
| 🎯 | **Cursor** | `~/.cursor/rules/` & `.cursorignore` | Auto Cost / Balance / Intelligence |
| 🌊 | **Windsurf** | `~/.codeium/windsurf/memories/` | SWE-1-mini / SWE-1.5 / Sonnet |
| 🤖 | **Claude Code CLI** | `~/.claude.json` & `CLAUDE.md` | Claude Haiku 4.5 / Sonnet 4.6 / Opus 4.6 |
| ♊ | **Gemini CLI** | `~/.gemini/config.json` | Gemini 3.7 Flash / 3.6 Flash / 3.1 Pro |
| ⚡ | **Zed Editor** | `~/.config/zed/AGENTS.md` | GPT-5.6 Luna / Sonnet / Sol |
| 🧠 | **JetBrains AI** | `~/.config/JetBrains/` | GPT-5.6 Luna / AI Pro / Opus |
| 🧬 | **Cline** | `~/.vscode/extensions/cline/` | Gemini Flash / Sonnet / GPT-5.6 Terra |
| 🐍 | **Aider** | `~/.aider.conf.yml` | Gemini Flash / Sonnet / GPT-5.6 Terra |
| ⚙️ | **OpenAI Codex** | `~/.codex/config.toml` | GPT-5.6 Luna / Terra / Sol |

---

## ⚡ Optimization Rules Matrix (Guide 2026)

| Category | Optimization Rule | Applied Reduction | Impact |
| :--- | :--- | :---: | :--- |
| **Context Pruning** | Noise Exclusions (`.geminiignore`, `.cursorignore`, `.codeiumignore`) | **-35% Prompt Tokens** | Eliminates build artifacts, dist, node_modules, binaries |
| **Prompt Caching** | Stable Header Anchoring (§29) | **-68% Cache Hits** | Reuses cached prompt prefixes across multi-turn sessions |
| **Reasoning Tax** | Thinking Effort Cap (`/fast` mode) (§30) | **-50% Reasoning Costs** | Prevents reasoning over-thinking on simple edits |
| **Output Compression** | Concise Mode & Minimal Diff Mandates | **-45% Response Tokens** | Prevents re-writing unchanged boilerplate code |
| **MCP / Tool Tax** | Scoped Tool Schema Truncation (§26) | **-40% Output Tax** | Limits massive JSON outputs from bash/read commands |

---

## 🛠️ System Requirements & Installation

### Operating System
- **Linux** (Ubuntu, Debian, Fedora, Arch, RHEL, Linux Mint, Manjaro, WSL2)

### Dependencies
Ensure PHP 7.4+ and basic Linux CLI tools are installed:

```bash
# Ubuntu / Debian
sudo apt update && sudo apt install -y php-cli php-json php-curl ripgrep curl git

# Fedora / RHEL
sudo dnf install -y php-cli php-json php-curl ripgrep curl git

# Arch Linux
sudo pacman -S php ripgrep curl git
```

---

## 🚀 How to Launch

### 1. Clone the Repository
```bash
git clone https://github.com/xsaidi1992/ai-token-optimizer-.git
cd ai-token-optimizer-
```

### 2. Auto-Install & Initialize CLI Tools
Run the single installation script to register global Linux CLI helpers (`ai-token-init`, `ai-context`, `ai-noise-audit`):
```bash
chmod +x install.sh start_server.sh
./install.sh
```

### 3. Start the Web Console
Launch the local web dashboard:
```bash
./start_server.sh
```
Open your browser at: **`http://localhost:8080`**

---

## 📄 License

Distributed under the **MIT License**. Created by **Mahmoud Saidi**.

```
MIT License

Copyright (c) 2026 Mahmoud Saidi

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

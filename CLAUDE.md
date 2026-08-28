# Claude Code Optimization (Guide 2026 §9)
- Keep responses short and directly executable.
- Run narrow test targets first: pytest -q -x --tb=short
- Report changed files + test results in max 8 lines.
- Use /clear for new tasks, /compact for long sessions.
- Sonnet for daily work, Opus only for planning difficult tasks.
- Limit --max-turns in scripts to prevent runaway loops.
- Do not inject large files with @; let Claude search/read targeted.
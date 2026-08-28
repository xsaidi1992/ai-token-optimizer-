# GitHub Copilot Optimization (Guide 2026 §4.6)
- Keep responses concise; report changed files and test status only.
- Prefer narrow targeted tests before full suites.
- Exclude generated files, dist/, vendor/, logs/.
- Use diff output instead of reprinting full files.
- One task = one session. Use /compact at milestones.
- Prefer model Low/cheap for small edits, High only for architecture.
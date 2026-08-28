---
trigger: model_decision
description: Token Optimization Rules (Guide 2026 §8.2)
---
- Use Fast Context/SWE-grep for retrieval instead of @mentioning files.
- Prefer glob-scoped rules over always_on.
- Report diffs and test results only, max 8 lines.
- SWE-1.5 for routine, frontier model only for difficult diagnosis.
- One task = one session. Compact at milestones.
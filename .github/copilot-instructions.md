# GitHub Copilot CLI & Agent Curated Instructions (Guide 2026 & Agent Architecture)
- LAZY_TOOLS: Defer loading tool JSON schemas until explicitly required by task.
- MEMORY_PRUNING: Keep context concise (<500 tokens). Use FTS5 state for session search.
- PROCEDURAL_SKILLS: Convert recurring workflows into scoped skill files in .agents/skills/.
- CONCISE_OUTPUT: Limit responses to diffs and test status (<= 8 lines).
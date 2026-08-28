# CLAUDE.md

This project uses **[`AGENTS.md`](AGENTS.md)** as the canonical guide for AI agents and developers.
**Read [`AGENTS.md`](AGENTS.md) before editing anything** — it gathers the plugin's construction
pattern, the central data contract (the workflow tree), the per-layer conventions (PHP/Vue), i18n,
build, testing, and the Git workflow.

**Everything in the repository is written in English** — identifiers, inline comments, docblocks,
commit messages, and the narrative documentation (`*.md`, `readme.txt`), across PHP,
JavaScript/TypeScript, and Vue. There is no Portuguese exception; docs still in Portuguese are legacy
debt to migrate as you touch them. See [`AGENTS.md`](AGENTS.md) §0 for the full rule.

Supporting documentation (linked from `AGENTS.md`):

- [`CONTRIBUTING.md`](CONTRIBUTING.md) — conventions and delivery checklist.
- [`DEVELOPERS.md`](DEVELOPERS.md) — PHP extension API (actions, triggers, integrations, conditions,
  placeholders, REST, channels).
- [`app/README.md`](app/README.md) — Vue 3 + Vite frontend.
- [`languages/README.md`](languages/README.md) — i18n pipeline.
- [`README.md`](README.md) — overview, architecture, and build.

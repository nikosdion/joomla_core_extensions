# AGENTS.md

## Project Overview

Generates and publishes `extensions.json`/`extensions.md` and `tables.json`/`tables.md`: an
automatically-generated catalogue of every core Joomla! extension and database table shipped since
Joomla 1.7, each tagged with the min/max Joomla version family it appeared in. The purpose is to
let the author reliably detect obsolete core extensions/tables left behind on real sites by messy
upgrade histories (Joomla core removals that don't clean up after themselves, restores of old
backups over newer sites, etc.) — see the "Why does it even exist?" section of `README.md`.

`src/` (namespace `Dionysopoulos\JoomlaCoreExtensions\`) contains `JoomlaVersions.php`,
`Getter.php`, `Extensions.php`, `Tables.php`, and `CallbackController.php`. `create.php` at the
repo root is the generation entry point, driven by Joomla release data. Output values are inclusive
`major.minor` strings ("min"/"max"), documented in detail in `README.md` — read it before changing
the generated file formats, since downstream consumers key on `type`+`element`+`folder`+`client_id`
for extensions and `table` for tables, not on array order.

## Build

```bash
composer install
php create.php
```

## Git: commit and tag outside the sandbox

Commits and tags are always signed, with a key held in 1Password. The 1Password signing agent is reached
over a local socket that agent sandboxes do not expose, so a sandboxed `git commit` or `git tag` **always**
fails (e.g. `error: 1Password: Could not connect to socket. Is the agent running?`).

Run every `git commit` and `git tag` **outside the sandbox from the first attempt** — in Claude Code with
`dangerouslyDisableSandbox: true`, in other harnesses with their equivalent unsandboxed / escalated
execution. Do not try the sandboxed form first, do not diagnose the failure, and never work around it
with `--no-gpg-sign`, `-c commit.gpgsign=false` or unsigned tags.

## Project memory

Project memory lives in `.claude/memory/`, committed with the code, so that it is shared across machines
and across agentic harnesses (Claude Code, Codex, Qwen Code, Kimi Code, Junie, …).

There are no memory files yet. When there is something worth remembering, create `.claude/memory/`,
the topic file, and a table here mapping each file to a concrete trigger ("Before you… | Read").

### Recording new memories

This is the **default and only** place for project memory. Do not write memories for this project to a
harness's private memory store (such as Claude Code's auto-memory under `~/.claude/projects/`); write
them here instead:

- Add to the existing topic file when one fits; otherwise create a new kebab-case `.md` file named after
  the topic, and add a row for it to the table above with a concrete trigger.
- Plain Markdown, no frontmatter. State the rule, then **Why:** (the reason or incident behind it) and
  **How to apply:**. Link related files with relative Markdown links.
- Don't record what the code, Git history or an existing `AGENTS.md` already says — update that
  `AGENTS.md` instead when the rule belongs there. Remove or correct entries that turn out wrong.
- These files are committed: no secrets, credentials, customer data or personal details.

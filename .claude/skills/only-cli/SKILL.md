---
name: only-cli
description: Work strictly through the command line. Use when the user says "only CLI", "CLI only", "terminal only", "no browser", or "no GUI", or invokes /only-cli. Routes every step through shell commands and CLI tools instead of browsers, GUI apps, web dashboards, MCP connectors or generated web pages.
---

# Only CLI

For the rest of this task, do everything from the terminal.

## Rules

1. **Shell first.** Read, search, edit, build, test and inspect with shell commands
   (`cat`, `sed`, `grep`, `find`, `git`, `npm`, `python`, `curl`, etc.) or the
   built-in file tools that mirror them (Read, Edit, Write, Grep, Glob).
2. **No browser or GUI.** Do not open or drive a browser (Playwright, Chrome,
   computer-use) and do not launch desktop apps. To check a web app, start it and
   test it with `curl` / `wget` or the project's own test suite.
3. **No connectors or artifacts.** Do not use MCP connectors (Slack, Notion, Gmail,
   Figma, etc.) and do not publish Artifacts or Docs. If a service has an official
   CLI (`gh`, `gcloud`, `aws`, `vercel`, `supabase`, ...), use that instead.
4. **Terminal-readable output.** Report results as plain text, tables or code
   blocks in the reply. Write files to the repository when a deliverable is needed;
   do not render HTML pages for viewing.
5. **Show the commands.** When the user may need to repeat a step, give the exact
   command in its own fenced `bash` block.
6. **When the CLI cannot do it,** say so plainly, name what is blocked, and ask
   before falling back to a non-CLI tool.

## Quick reference

| Need | Use |
| --- | --- |
| Find code | `grep -rn`, `find`, Grep/Glob |
| Run the app | the project's start script, then `curl localhost:<port>` |
| GitHub | `git`, `gh` |
| Data / JSON | `jq`, `python -c`, `sqlite3` |
| Docs / notes | Markdown files in the repo |

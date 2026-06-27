# Performance & Security Pro Skills — Design

**Status:** Approved design → ready for implementation plan
**Target version:** v3.0.0 line (premium-only content; `@since 3.0.0` where code changes apply)
**Scope:** Two new standalone Pro Agent Skills + the packaging/admin wiring to deliver them.

## Goal

Ship two new premium Agent Skills that teach an AI client how to use the v3.0.0 diagnostic tools well:
1. **`emcp-performance`** — drive the `analyze-performance` tool: run it, interpret the scored report, and route fixes through the other EMCP tools, then re-scan.
2. **`emcp-security`** — drive the `scan-security` tool **combined with the Filesystem tools**: scan → confirm findings by reading the real files → remediate (gated) → re-scan.

These are Pro content (bundled only in the premium build, license-gated download), siblings to the existing `emcp-skills` (Elementor page-building) skill.

## Constraints & non-goals

- **Skill content only + minimal packaging wiring.** No new MCP tools, no changes to the scanner/analyzer PHP.
- **Standalone skills**, each with its own `name`/`description` frontmatter so it triggers on its own concern — NOT folded into the Elementor `emcp-skills` skill.
- **Premium-only**, same gate as the existing skill (`emcp_tools_fs()->can_use_premium_code()` + folder present in the premium build).
- Skills must be **accurate to the shipped tools** (tool names, params, the disabled-by-default gating of filesystem write tools and the `confirm:true` requirements).
- No new admin tab; reuse the existing **Skills** tab and the single download action.

## Deliverables

### 1. `skills/emcp-performance/SKILL.md`

Frontmatter `name: emcp-performance`, a `description` that triggers on performance audits / "site is slow" / when the `analyze-performance` tool is available (even if the user doesn't name it).

Body (organized as a workflow):
- **When this applies** — performance diagnosis/optimization on a WordPress site reachable via the EMCP MCP tools.
- **The loop:** scan → interpret → fix → re-scan.
- **Run** `analyze-performance` — params: `url` (same-site only), `post_id`, `include_page_fetch`, `deep_assets`. Default targets the frontpage. Note it's read-only + self-contained.
- **Interpret the report** — `summary.score` (0-100) + `grade` (A–F) + `counts`; the five `sections` (server / database / config / page / assets); `page_fetch` meta; `top_recommendations` (already ranked worst-first). Explain severities (critical/warning/info/pass) and that the page audit is HTML/header analysis, not real Core Web Vitals.
- **Act on findings, routing through other EMCP tools where possible:**
  - Autoloaded-options bloat / large tables / revisions → `list-tables`/`describe-table`/`query` (read-only DB tools) to pinpoint the offenders, then concrete guidance (the scanner only reports; cleanup is the admin's call).
  - No persistent object cache / no page cache → `search-plugins` + `install-plugin` (e.g. a Redis drop-in or a caching plugin), then `activate-plugin`.
  - Outdated core/plugins/themes → `update-plugin` / `update-theme`.
  - WP_DEBUG on in production, missing constants → `get-settings`/`update-settings` or wp-config guidance.
  - Server-level items (PHP version, OPcache, memory) → host guidance (not fixable via MCP).
- **Re-scan** to confirm the score moved; iterate.
- A compact **tool reference** table.

### 2. `skills/emcp-security/SKILL.md`

Frontmatter `name: emcp-security`, a `description` that triggers on security audits / malware checks / "is my site hacked" / hardening reviews / when `scan-security` is available.

Body — the **investigate → confirm → remediate → re-scan** loop pairing `scan-security` with the Filesystem tools:
- **When this applies** — security/malware assessment on a site reachable via the EMCP MCP tools.
- **Step 1 — Scan.** Run `scan-security` (start shallow; use `deep:true` for the whole tree, or `checks:[...]` for a subset of malware/integrity/hardening/software; `max_files`/`max_seconds` to bound). Read the four `sections` + `scan_meta` (`truncated`/`truncated_reason`, `integrity_api`, `headers_fetch`, `files_scanned`).
- **Step 2 — Confirm with filesystem READS (the combination).** Heuristics yield false positives, so never act on a raw finding:
  - For each **malware** finding (`value.location` = `path:line`, plus a short snippet), `read-file` the flagged file and read the surrounding context to judge true- vs false-positive.
  - `search-files` to hunt related artifacts — the same signature elsewhere, sibling droppers, recently-touched PHP under uploads.
  - `list-directory` to inspect suspicious directories surfaced by the scan.
  - For **integrity** "modified/missing core file" findings, `read-file` to see what changed (and confirm it isn't a legit localized core build).
- **Step 3 — Remediate (GATED, careful).** Only after confirmation:
  - Confirmed injected code → `edit-file` to strip the malicious block; confirmed dropper/webshell → `delete-file` (needs `confirm:true`).
  - These filesystem **write tools are disabled-by-default and RCE-grade**: the admin must enable them on EMCP Tools → Tools; every write is auto-backed-up + audit-logged; `wp-config.php`/`.htaccess` are refused; honor `DISALLOW_FILE_EDIT`. If they're not available in the session, tell the admin to enable them.
  - **Hardening** findings → guidance, or `edit-file` on wp-config (e.g. add `DISALLOW_FILE_EDIT`, fix `WP_DEBUG_DISPLAY`) when writes are enabled; renaming the `admin` user / disabling XML-RPC via the appropriate path.
  - **Software** findings → `update-plugin`/`update-theme`; closed/abandoned plugins → replace and `delete-plugin`.
- **Step 4 — Re-scan** to verify the finding is gone and the score improved.
- **Safety section** (prominent): heuristics ≠ proof; always `read-file`-confirm before deleting; the scanner self-excludes the EMCP plugin + its sandbox and skips benign empty PHP guards, so a finding under those is unexpected; backups + audit log exist but treat every write as serious; if the site is actively compromised, recommend offline backup + host involvement, not just file edits.
- A compact **tool reference** table (scan-security + read-file/list-directory/search-files + write-file/edit-file/delete-file with their gates).

### 3. Packaging — `includes/admin/class-pro-skills.php`

- Change the bundle source from a single skill folder to the **whole `skills/` tree** so all skill folders are included.
- Concretely: `SOURCE_RELATIVE = 'skills'`; in `handle_download()`, zip each entry relative to the `skills/` dir with **no extra top-level prefix** (each skill folder — `emcp-skills/`, `emcp-performance/`, `emcp-security/` — becomes a top-level dir in the zip). Keep `DOWNLOAD_FILENAME = 'emcp-skills.zip'`.
- `skills_dir_exists()` / `user_has_access()` keep working (the `skills/` dir exists on premium builds). Keep the license gate, nonce, cap check, and ZipArchive guard unchanged.

### 4. Admin docs — `includes/admin/views/page-skills.php`

- Update the copy so it says the download now contains **three** skills and you install each folder the same way (the install paths are unchanged — `.claude/skills/<skill>/`, etc.; users copy all three folders).
- The `verticals/` existence check (`$emcp_tools_verticals_dir = EMCP_TOOLS_DIR . 'skills/emcp-skills/verticals'`) is unaffected — leave it; it still detects the premium build.
- Keep per-client (Claude Code/Desktop, Cursor, Windsurf, Antigravity, universal) sections; adjust wording from "the emcp-skills folder" to "each skill folder (emcp-skills, emcp-performance, emcp-security)".

## Testing / verification

- **Skill content:** prose review for accuracy against the actual tool names/params/gates (no automated test).
- **Packaging:** if a PHPUnit test exists for `EMCP_Tools_Pro_Skills`, update it; otherwise verify by reading the changed `handle_download()` logic and (where feasible) a manual `php -l` lint + a local download smoke check that the produced zip contains the three top-level skill folders. Full PHPUnit suite must remain green.
- Confirm the two `SKILL.md` files have valid YAML frontmatter (name + description) and internal links resolve.

## Out of scope (future)

Per-skill separate download buttons; a skills "marketplace" UI; website docs pages for the new skills; auto-install into the user's skills directory; localization of the skill bodies (skills are English content like the existing one).

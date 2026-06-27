# Performance & Security Pro Skills Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Note on task types:** Tasks 1–2 are *content authoring* (Agent Skill markdown). Their steps give a precise section outline plus the exact tool names/params/gates the prose MUST be accurate to — the authoring is writing well-structured prose that satisfies the outline, not filling a code template. Tasks 3–4 are code/markup edits with exact diffs. Task 5 is verification.

**Goal:** Ship two standalone premium Agent Skills — `emcp-performance` (drive `analyze-performance`) and `emcp-security` (drive `scan-security` combined with the Filesystem tools) — and update the Pro-skills download + admin docs to bundle all skill folders.

**Architecture:** Two new sibling skill folders under `skills/` (each a self-contained `SKILL.md` with `name`/`description` frontmatter, mirroring the existing `skills/emcp-skills/SKILL.md`). The Pro-skills download handler is widened to zip the whole `skills/` tree (each skill folder becomes a top-level dir in `emcp-skills.zip`); the Skills admin tab copy is updated to say the bundle now contains three skills.

**Tech Stack:** Markdown (Agent Skills, YAML frontmatter), PHP 8.1+ (`EMCP_Tools_Pro_Skills` download handler), WordPress admin view markup.

**Reference spec:** `docs/superpowers/specs/2026-06-27-perf-security-pro-skills-design.md`

## Conventions every task follows
- Reference the live tool names exactly: `emcp-tools/analyze-performance`, `emcp-tools/scan-security`, `emcp-tools/read-file`, `emcp-tools/list-directory`, `emcp-tools/search-files`, `emcp-tools/write-file`, `emcp-tools/edit-file`, `emcp-tools/delete-file`, plus the DB/plugins tools (`list-tables`, `query`, `search-plugins`, `install-plugin`, `activate-plugin`, `update-plugin`, `update-theme`, `delete-plugin`, `get-settings`, `update-settings`). In skill prose, when an MCP client surfaces them they appear as `mcp__*__emcp-tools-<tool>`; write the prose like the existing SKILL.md (use the bare tool name in workflow text).
- Match the existing skill's voice: direct, workflow-ordered, tables for references, no fluff.
- Stage ONLY the files named in each task's commit. The working tree has unrelated pre-existing modified files (`bin/*`, `includes/admin/class-pro-*.php`, etc.) — never stage them.
- PHP lint: `"/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe" -l <file>`. Full test suite: `"/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe" vendor/bin/phpunit`.

## File Structure

| File | Responsibility |
|---|---|
| `skills/emcp-performance/SKILL.md` (create) | Performance Analyzer skill — scan → interpret → fix → re-scan |
| `skills/emcp-security/SKILL.md` (create) | Security & Malware Scanner skill — scan → confirm (filesystem reads) → remediate (gated writes) → re-scan |
| `includes/admin/class-pro-skills.php` (modify) | Widen the download to zip the whole `skills/` tree |
| `includes/admin/views/page-skills.php` (modify) | Admin copy: bundle now contains three skills |

---

## Task 1: Author `skills/emcp-performance/SKILL.md`

**Files:**
- Create: `skills/emcp-performance/SKILL.md`

- [ ] **Step 1: Write the frontmatter.** YAML block at the very top:

```yaml
---
name: emcp-performance
description: >-
  Use this skill whenever the user wants to diagnose or improve a WordPress
  site's performance through the EMCP Tools MCP — recognizable by an
  `emcp-tools-analyze-performance` tool being available, or by the user asking
  why a site is slow / to do a speed or performance audit / to optimize load
  time. Covers running the performance analyzer, reading its scored report,
  prioritizing fixes, applying them through the other EMCP tools, and
  re-scanning to confirm. Trigger even when the user doesn't say
  "analyze-performance" — if the tool is available and the task is about
  WordPress performance, this skill applies.
---
```

- [ ] **Step 2: Write the body** so it covers, in this order, accurately to the tool:

  1. **One-line purpose + the loop.** "Run → interpret → fix → re-scan." Note the tool is read-only and self-contained (no external API; safe to run anytime).
  2. **§1 Run the scan.** `analyze-performance` params: `url` (a page on THIS site only — external hosts rejected), `post_id` (resolved to its permalink), `include_page_fetch` (default true; set false for server/DB-only), `deep_assets` (default false; samples same-host asset sizes). Default target = frontpage. Show one example call with no args and one with `url`.
  3. **§2 Read the report.** Document the shape: `summary` = `{score 0-100, grade A–F, counts{critical,warning,pass,info}}`; `sections` = `server` / `database` / `config` / `page` / `assets` (each a list of findings with `id,label,status,value,message,recommendation`); `page_fetch` = `{ok,status_code,response_ms,total_bytes,error}`; `top_recommendations` (already ranked critical→warning). State clearly that the page audit is **server-side HTML/header analysis, not real Core Web Vitals / Lighthouse** (PHP can't run a browser). Grade bands: A≥90, B≥80, C≥70, D≥60, else F.
  4. **§3 Work findings worst-first**, with a table mapping common findings → concrete fix, routing through EMCP tools where possible:
     - `autoload_size` (critical/warning) → use `query`/`list-tables`/`describe-table` to list the largest autoloaded options, then guidance to disable autoload on stale ones (the tool only reports; the admin decides).
     - `database_size` / `post_revisions` → `query` to quantify; guidance (`WP_POST_REVISIONS`, cleanup).
     - `object_cache` warning → `search-plugins` "redis cache" → `install-plugin` (e.g. `redis-cache`) → `activate-plugin`; note the drop-in still needs a Redis/Memcached server (host-level).
     - page-cache hint warning → `search-plugins`/`install-plugin` a caching plugin.
     - `plugin_count` warning / inactive plugins → review; `delete-plugin` for unused (note it's disabled-by-default).
     - outdated core/plugins/themes (if surfaced) → `update-plugin`/`update-theme`.
     - `wp_debug` on in production → `get-settings`/`update-settings` won't toggle WP_DEBUG (it's a constant); give wp-config guidance.
     - `compression` / `cache_headers` / `render_blocking` / `image_lazy_loading` / `third_party` (page+assets) → server/theme guidance; these are reported, fixed outside MCP.
     - server items (`php_version`, `opcache`, `memory_limit`, `image_lib`) → host-level guidance (not MCP-fixable).
  5. **§4 Re-scan & iterate.** After a fix, re-run `analyze-performance` on the same target and confirm the relevant finding cleared and the score moved. Loop until the high-severity items are gone or are host-level.
  6. **§5 Tool reference** table: `analyze-performance` (+ its params) and the support tools it routes to (`query`, `list-tables`, `search-plugins`, `install-plugin`, `activate-plugin`, `update-plugin`, `update-theme`, `get-settings`/`update-settings`).
  7. A short **premium note** at the end: "This skill ships in the EMCP Tools premium build."

  Keep it tight (roughly 120–200 lines), tables over prose where it fits, mirroring the existing `skills/emcp-skills/SKILL.md` style.

- [ ] **Step 3: Verify** the file: confirm the YAML frontmatter parses (opening/closing `---`, `name:` + `description:` present) and that every tool name written matches the canonical list in the Conventions section. Read it once end-to-end for accuracy against the analyze-performance behavior described in `CLAUDE.md`.

- [ ] **Step 4: Commit**

```bash
git add skills/emcp-performance/SKILL.md
git commit -m "feat(skills): add emcp-performance Pro Agent Skill"
```

---

## Task 2: Author `skills/emcp-security/SKILL.md`

**Files:**
- Create: `skills/emcp-security/SKILL.md`

- [ ] **Step 1: Write the frontmatter.** YAML block:

```yaml
---
name: emcp-security
description: >-
  Use this skill whenever the user wants to assess or harden a WordPress
  site's security through the EMCP Tools MCP — recognizable by an
  `emcp-tools-scan-security` tool being available, or by the user asking for a
  security audit, a malware scan, "is my site hacked?", hardening advice, or
  help cleaning an infection. Covers running the security scanner, confirming
  each finding by reading the real files with the Filesystem tools, remediating
  confirmed issues (with the gated write tools), and re-scanning. Trigger even
  when the user doesn't say "scan-security" — if the tool is available and the
  task is WordPress security/malware, this skill applies.
---
```

- [ ] **Step 2: Write the body** covering, in order, accurately to the tools:

  1. **Purpose + the loop:** "Scan → confirm (read the real files) → remediate (gated) → re-scan." State up front that the scanner is **heuristic** — findings are leads to confirm, not verdicts — which is exactly why it's paired with the Filesystem tools.
  2. **§1 Scan.** `scan-security` params: `checks` (subset of `malware`/`integrity`/`hardening`/`software`; omit = all four), `deep` (false = uploads + active plugins/themes; true = whole tree), `max_files` (default 2000, ceiling 20000), `max_seconds` (default 20, ceiling 120). Output: `summary{score,grade,counts}`, `sections` = `malware`/`integrity`/`hardening`/`software`, `scan_meta` (`files_scanned`, `files_skipped_size`, `truncated`+`truncated_reason`, `deep`, `checks_run`, `integrity_api{ok,error}`, `headers_fetch{ok,error}`, `elapsed_ms`), `top_recommendations`. Recommend starting shallow; go `deep:true` or narrow with `checks` when investigating. Note malware findings carry `value.location` = `path:line` + a short `value.snippet` (never full file contents — that's why you read the file next).
  3. **§2 Confirm with Filesystem READS — the combination (the heart of this skill).** For every finding before acting:
     - **malware** finding → `read-file` the path from `value.location` and read around the line to judge true- vs false-positive (e.g. a real `eval(base64_decode(...))` backdoor vs. a library that legitimately packs code).
     - `search-files` to widen — hunt the same signature in other files, find sibling droppers, list recently-modified PHP under `uploads/`.
     - `list-directory` to inspect a suspicious directory the scan surfaced (unexpected PHP in uploads, odd folders).
     - **integrity** "modified/missing core file" → `read-file` to see what changed (confirm it's not a legit localized build), then plan a core re-install.
     - Give a concrete worked example: a `malware_eval_obfuscation` critical → `read-file` → confirm the obfuscated block → `search-files` for the same first 20 chars of the payload across the install to find every infected file.
  4. **§3 Remediate — GATED, careful.** Only after confirmation:
     - Injected code in an otherwise-legit file → `edit-file` (find-and-replace the malicious block).
     - A confirmed dropper/webshell file → `delete-file` (requires `confirm:true`).
     - **Loud safety box:** the Filesystem write tools (`write-file`/`edit-file`/`delete-file`) are **disabled-by-default and effectively RCE** — the admin must enable them on EMCP Tools → Tools; every write is auto-backed-up + audit-logged; `wp-config.php`/`.htaccess` are refused; `DISALLOW_FILE_EDIT` is honored. If the write tools aren't in the session, tell the admin to enable them rather than guessing another path.
     - **hardening** findings → `edit-file` on wp-config when writes are enabled (e.g. add `define('DISALLOW_FILE_EDIT', true);`, fix `WP_DEBUG_DISPLAY`), or give guidance (rename the `admin` user, disable XML-RPC, delete `readme.html`, add security headers, force HTTPS).
     - **software** findings → `update-plugin`/`update-theme`; a plugin closed/removed from wordpress.org → replace it, then `delete-plugin`.
  5. **§4 Re-scan** the same `checks` to confirm the finding is gone and the score improved.
  6. **§5 Safety & judgment** (prominent): heuristics ≠ proof; always `read-file`-confirm before `delete-file`; the scanner **self-excludes the EMCP plugin's own dir + its `uploads/emcp-widgets` snippet sandbox** and **skips benign empty/comment-only PHP guards**, so a finding under those paths would be unexpected; backups + audit log exist but treat every write as serious and reversible-only-via-backup; if the site is actively compromised, recommend a full offline backup + host/professional involvement, password/salt rotation, and not relying on file edits alone.
  7. **§6 Tool reference** table: `scan-security` (+ params) | read: `read-file`/`list-directory`/`search-files` | gated write: `write-file`/`edit-file`/`delete-file` (note disabled-by-default + `confirm:true` for delete) | software/hardening support: `update-plugin`/`update-theme`/`delete-plugin`/`get-settings`.
  8. **Premium note** at the end.

  Roughly 160–240 lines; tables + the worked confirm-example; mirror the existing skill's voice.

- [ ] **Step 3: Verify** the YAML frontmatter parses and every tool name matches the canonical list; read end-to-end for accuracy against the scan-security + filesystem behavior in `CLAUDE.md` (disabled-by-default writes, `confirm:true`, ABSPATH confinement, self-exclusion, trivial-PHP skip).

- [ ] **Step 4: Commit**

```bash
git add skills/emcp-security/SKILL.md
git commit -m "feat(skills): add emcp-security Pro Agent Skill (scan-security + filesystem tools)"
```

---

## Task 3: Widen the Pro-skills download to bundle all skill folders

**Files:**
- Modify: `includes/admin/class-pro-skills.php`

The handler currently zips only `skills/emcp-skills` under a forced `emcp-skills/` top-level prefix. Change it to zip the whole `skills/` tree so each skill folder is a top-level dir in the zip.

- [ ] **Step 1: Change the source constant.** Replace:

```php
	const SOURCE_RELATIVE = 'skills/emcp-skills';
```

with:

```php
	const SOURCE_RELATIVE = 'skills';
```

- [ ] **Step 2: Drop the forced top-level prefix in `handle_download()`.** Replace this block:

```php
		// Top-level folder name inside the zip is `emcp-skills/` so users get
		// a sensible directory when they extract it on their machine.
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		$base_in_zip = 'emcp-skills/';
		foreach ( $iterator as $file_info ) {
			$abs_path  = $file_info->getPathname();
			$rel_path  = ltrim( substr( $abs_path, strlen( $source ) ), DIRECTORY_SEPARATOR . '/' );
			$zip_path  = $base_in_zip . str_replace( DIRECTORY_SEPARATOR, '/', $rel_path );
			if ( $file_info->isDir() ) {
				$zip->addEmptyDir( $zip_path );
			} else {
				$zip->addFile( $abs_path, $zip_path );
			}
		}
```

with (each skill folder under `skills/` becomes its own top-level dir in the zip — `emcp-skills/`, `emcp-performance/`, `emcp-security/`):

```php
		// Zip the whole skills/ tree so every bundled skill folder
		// (emcp-skills/, emcp-performance/, emcp-security/) is a top-level
		// directory when the user extracts the archive.
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $file_info ) {
			$abs_path = $file_info->getPathname();
			$rel_path = ltrim( substr( $abs_path, strlen( $source ) ), DIRECTORY_SEPARATOR . '/' );
			if ( '' === $rel_path ) {
				continue;
			}
			$zip_path = str_replace( DIRECTORY_SEPARATOR, '/', $rel_path );
			if ( $file_info->isDir() ) {
				$zip->addEmptyDir( $zip_path );
			} else {
				$zip->addFile( $abs_path, $zip_path );
			}
		}
```

- [ ] **Step 3: Update the class docblock** (top of file) so it no longer says it bundles only `skills/emcp-skills`. Replace the first docblock sentence:

```php
 * Premium Skills download — bundles plugin's skills/emcp-skills folder on the
 * fly and streams it as emcp-skills.zip for licensed Pro users.
```

with:

```php
 * Premium Skills download — bundles every skill folder under the plugin's
 * skills/ tree (emcp-skills, emcp-performance, emcp-security) on the fly and
 * streams them as emcp-skills.zip for licensed Pro users.
```

- [ ] **Step 4: Lint**

Run: `"/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe" -l includes/admin/class-pro-skills.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Smoke-test the zip contents** with a tiny script (confirms each skill folder is top-level; uses the live skill folders on disk):

Run:
```bash
"/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe" -r '
$src = "skills";
$zip = new ZipArchive(); $tmp = tempnam(sys_get_temp_dir(),"z"); $zip->open($tmp, ZipArchive::OVERWRITE|ZipArchive::CREATE);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach($it as $f){ $rel = ltrim(substr($f->getPathname(), strlen($src)), "/\\"); if($rel==="") continue; $zp = str_replace("\\","/",$rel); $f->isDir()?$zip->addEmptyDir($zp):$zip->addFile($f->getPathname(),$zp); }
$zip->close();
$z = new ZipArchive(); $z->open($tmp); $tops=[]; for($i=0;$i<$z->numFiles;$i++){ $n=$z->getNameIndex($i); $tops[strtok($n,"/")]=true; }
echo "top-level: ".implode(", ", array_keys($tops))."\n";
$has = isset($tops["emcp-skills"])&&isset($tops["emcp-performance"])&&isset($tops["emcp-security"]);
echo $has?"OK all three present\n":"MISSING a skill folder\n"; unlink($tmp);
'
```
Expected: `top-level: emcp-skills, emcp-performance, emcp-security` and `OK all three present`. (Run from the plugin root; depends on Tasks 1–2 having created the two new folders.)

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-pro-skills.php
git commit -m "feat(skills): bundle all skill folders in the Pro download zip"
```

---

## Task 4: Update the Skills admin tab copy

**Files:**
- Modify: `includes/admin/views/page-skills.php`

The page describes a single Elementor skill and "a folder named emcp-skills/". Update the framing to: the bundle now contains three skills, each installs the same way.

- [ ] **Step 1: Update the intro description.** Replace the intro `<p class="description">` text:

```php
				<?php esc_html_e( 'A pre-written Agent Skill that teaches Claude (and any compatible AI client) exactly how to build, edit, and style Elementor pages through the MCP tools — now with industry skill packs that tailor the build to the site\'s trade. Install once per machine — every future session that loads this skill knows your workflow.', 'emcp-tools' ); ?>
```

with:

```php
				<?php esc_html_e( 'Pre-written Agent Skills that teach Claude (and any compatible AI client) exactly how to use the EMCP Tools MCP. The bundle includes three skills: Elementor page building (with industry skill packs), a Performance Analyzer skill, and a Security & Malware Scanner skill (which pairs the security scanner with the filesystem tools to confirm and clean findings). Install once per machine — every future session that loads them knows your workflow.', 'emcp-tools' ); ?>
```

- [ ] **Step 2: Update the quick-install note** so "The skill folder goes into…" becomes plural. Replace:

```php
				<?php esc_html_e( 'Click the download button above, then follow the guide for your AI client below. The skill folder goes into your client\'s skills/rules directory — paths listed per platform.', 'emcp-tools' ); ?>
```

with:

```php
				<?php esc_html_e( 'Click the download button above, then follow the guide for your AI client below. The zip contains three skill folders (emcp-skills, emcp-performance, emcp-security) — copy each into your client\'s skills/rules directory the same way; paths listed per platform.', 'emcp-tools' ); ?>
```

- [ ] **Step 3: Update the Claude Code "extract" line.** Replace:

```php
							<?php esc_html_e( 'Extract emcp-skills.zip — you\'ll get a folder named "emcp-skills/".', 'emcp-tools' ); ?>
```

with:

```php
							<?php esc_html_e( 'Extract emcp-skills.zip — you\'ll get three folders: "emcp-skills/", "emcp-performance/", and "emcp-security/".', 'emcp-tools' ); ?>
```

- [ ] **Step 4: Generalize the "Move the folder" line** to cover all three. Replace:

```php
							<?php esc_html_e( 'Move the folder to one of these locations:', 'emcp-tools' ); ?>
```

with:

```php
							<?php esc_html_e( 'Move each folder into one of these locations (same parent dir for all three):', 'emcp-tools' ); ?>
```

- [ ] **Step 5: Add a one-line "all three install identically" note** in the other client guides. For the Cursor, Windsurf, and Antigravity guides, find each `Copy emcp-skills/ to your project at:` / `Copy the folder to your workspace at:` / `Point it at the extracted emcp-skills folder` line and append the clarifier `(repeat for emcp-performance/ and emcp-security/)` to the visible string. Concretely, replace:

```php
						<li><?php esc_html_e( 'Copy emcp-skills/ to your project at:', 'emcp-tools' ); ?> <code>&lt;project-root&gt;/.cursor/rules/emcp-skills/</code></li>
```

with:

```php
						<li><?php esc_html_e( 'Copy each skill folder (emcp-skills, emcp-performance, emcp-security) to your project under:', 'emcp-tools' ); ?> <code>&lt;project-root&gt;/.cursor/rules/</code></li>
```

and replace:

```php
						<li><?php esc_html_e( 'Copy the folder to your workspace at:', 'emcp-tools' ); ?> <code>&lt;workspace&gt;/.windsurf/rules/emcp-skills/</code></li>
```

with:

```php
						<li><?php esc_html_e( 'Copy each skill folder (emcp-skills, emcp-performance, emcp-security) to your workspace under:', 'emcp-tools' ); ?> <code>&lt;workspace&gt;/.windsurf/rules/</code></li>
```

and replace:

```php
						<li><?php esc_html_e( 'In Antigravity, open Knowledge Manager and create a new knowledge source. Point it at the extracted emcp-skills folder, or paste the SKILL.md contents directly.', 'emcp-tools' ); ?></li>
```

with:

```php
						<li><?php esc_html_e( 'In Antigravity, open Knowledge Manager and create a knowledge source for each extracted skill folder (emcp-skills, emcp-performance, emcp-security), or paste each SKILL.md\'s contents directly.', 'emcp-tools' ); ?></li>
```

(Leave the Claude Desktop global-path lines and the per-platform `~/.claude/skills/emcp-skills/` examples as-is — they correctly show one example folder; the Step 2 note already tells users to repeat for all three. If you want, append " (and emcp-performance/, emcp-security/)" to those `code` examples, but it is not required.)

- [ ] **Step 6: Lint**

Run: `"/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe" -l includes/admin/views/page-skills.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/views/page-skills.php
git commit -m "docs(skills): admin Skills tab copy for the three-skill bundle"
```

---

## Task 5: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full PHPUnit suite** to confirm the PHP changes broke nothing:

Run: `"/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe" vendor/bin/phpunit`
Expected: 0 failures, 0 errors (1 pre-existing skip is fine).

- [ ] **Step 2: Confirm both skill folders + frontmatter exist.**

Run:
```bash
for f in skills/emcp-performance/SKILL.md skills/emcp-security/SKILL.md; do
  echo "== $f =="; head -3 "$f"
done
```
Expected: each shows `---` then `name: emcp-performance` / `name: emcp-security`.

- [ ] **Step 3: Re-run the Task 3 zip smoke test** (now that both folders exist) and confirm `OK all three present`.

- [ ] **Step 4: Grep the two new SKILL.md files for tool-name accuracy** — confirm no stale/legacy tool names crept in:

Run:
```bash
grep -nE "analyze-performance|scan-security|read-file|list-directory|search-files|edit-file|delete-file|write-file" skills/emcp-performance/SKILL.md skills/emcp-security/SKILL.md | head
```
Expected: performance file references `analyze-performance` (+ support tools); security file references `scan-security` + the filesystem tools. No `add-widget`/legacy names.

---

## Self-Review

**1. Spec coverage:**
- `emcp-performance/SKILL.md` → Task 1. ✓
- `emcp-security/SKILL.md` (scan-security + filesystem confirm/remediate loop) → Task 2. ✓
- Packaging: zip whole `skills/` tree, each folder top-level, keep gate/nonce/cap/ZipArchive guard → Task 3. ✓
- Admin docs: three-skill bundle copy → Task 4. ✓
- Verification (suite green, frontmatter valid, zip contents, tool-name accuracy) → Task 5. ✓
- Spec "out of scope" (separate download buttons, website docs, auto-install, localization) → correctly NOT in any task. ✓

**2. Placeholder scan:** No TBD/TODO. Tasks 1–2 are authoring tasks with explicit content outlines + exact tool facts (not code placeholders); Tasks 3–4 carry exact old→new code/markup; Task 5 has concrete commands. ✓

**3. Consistency:** `SOURCE_RELATIVE = 'skills'` in Task 3 matches the zip smoke test's `$src = "skills"` in Task 3/Task 5. Skill folder names (`emcp-performance`, `emcp-security`) are identical across Tasks 1, 2, 3, 4, 5. Tool names match the canonical Conventions list throughout. The `DOWNLOAD_FILENAME` stays `emcp-skills.zip` (unchanged) — referenced consistently in Task 4 copy. ✓

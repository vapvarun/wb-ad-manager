#!/usr/bin/env node
/**
 * Release builder with completeness verification.
 *
 * What it does:
 *   1. Reads the plugin slug + version from the main PHP header.
 *   2. git archive HEAD -> temp tree.
 *   3. Strips paths listed in .distignore.
 *   4. Verifies every CSS file under assets/css/ is in the zip tree.
 *   5. Verifies every JS file under assets/js/ is in the zip tree.
 *   6. Verifies every PHP file under includes/ is in the zip tree.
 *   7. Parses require / require_once / include / include_once across EVERY
 *      PHP file under includes/ + the main plugin file, resolves __DIR__
 *      relative paths, skips WP core paths, and asserts every remaining
 *      target exists in the zip. Catches "forgot to ship this class" and
 *      bundled-dep bugs (e.g. vendor/wbcom-credits-sdk stripped by
 *      .distignore).
 *   8. Writes dist/{releaseName}-{version}.zip and prints a summary.
 *
 * Exit codes:
 *   0 = zip built, all checks passed
 *   1 = missing file (CSS/JS/PHP) or broken require target
 *   2 = configuration problem (missing .distignore, bad version, etc.)
 *
 * Pure Node 20+ (no third-party deps), run from the plugin root:
 *   node scripts/build-release.mjs
 *   npm run release
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdirSync, readdirSync, statSync, rmSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
process.chdir(ROOT);

const BOLD = (s) => `\x1b[1m${s}\x1b[0m`;
const GREEN = (s) => `\x1b[32m${s}\x1b[0m`;
const RED = (s) => `\x1b[31m${s}\x1b[0m`;
const DIM = (s) => `\x1b[2m${s}\x1b[0m`;

function die(code, msg) {
	console.error(RED('x ' + msg));
	process.exit(code);
}

function run(cmd, args, opts = {}) {
	// execFileSync avoids shell interpolation entirely (no command-injection surface).
	return execFileSync(cmd, args, { encoding: 'utf8', ...opts });
}

function walk(dir, exts = null) {
	if (!existsSync(dir)) return [];
	const out = [];
	for (const entry of readdirSync(dir)) {
		const full = join(dir, entry);
		const s = statSync(full);
		if (s.isDirectory()) {
			out.push(...walk(full, exts));
		} else if (!exts || exts.some((e) => entry.endsWith(e))) {
			out.push(full);
		}
	}
	return out;
}

function readDistignore() {
	if (!existsSync('.distignore')) {
		die(2, '.distignore not found. Create one listing paths/files to exclude from the release zip.');
	}
	return readFileSync('.distignore', 'utf8')
		.split('\n')
		.map((l) => l.trim())
		.filter((l) => l && !l.startsWith('#'));
}

function parseMainPlugin() {
	const rootPhps = readdirSync('.').filter((f) => f.endsWith('.php'));
	for (const file of rootPhps) {
		const src = readFileSync(file, 'utf8');
		if (/^\s*\*\s*Plugin Name:/m.test(src)) {
			const vMatch = src.match(/^\s*\*\s*Version:\s*(.+)$/m);
			const tdMatch = src.match(/^\s*\*\s*Text Domain:\s*(.+)$/m);
			const slug = tdMatch ? tdMatch[1].trim() : file.replace(/\.php$/, '');
			const version = vMatch ? vMatch[1].trim() : null;
			if (!version) die(2, `Version header missing from ${file}`);
			// If package.json has a different "name", use it for the zip filename
			// (marketing name vs WordPress.org slug — e.g. wb-ad-manager vs
			// wb-ads-rotator-with-split-test). The in-zip folder keeps the PHP slug.
			let releaseName = slug;
			if (existsSync('package.json')) {
				try {
					const pkg = JSON.parse(readFileSync('package.json', 'utf8'));
					if (pkg.name && typeof pkg.name === 'string') releaseName = pkg.name;
				} catch {}
			}
			return { mainFile: file, slug, releaseName, version };
		}
	}
	die(2, 'No main plugin file found at repo root (looked for Plugin Name header).');
}

function parseRequires(phpPath) {
	if (!existsSync(phpPath)) return [];
	const src = readFileSync(phpPath, 'utf8');
	const out = new Set();
	// Track the source file's directory (relative to ROOT) so __DIR__
	// requires resolve to the right plugin-relative path.
	const srcDirRel = relative(ROOT, dirname(phpPath)).replace(/\\/g, '/');
	const re = /\b(?:require|require_once|include|include_once)\s*\(?\s*([^;]+?)\s*\)?\s*;/g;
	let m;
	while ((m = re.exec(src)) !== null) {
		const raw = m[1].trim();
		const pathMatch = raw.match(/['"]([^'"]+\.php)['"]/);
		if (!pathMatch) continue;
		let rel = pathMatch[1];
		if (rel.startsWith('/')) rel = rel.slice(1);
		rel = rel.replace(/^\.\//, '');

		// Skip WordPress core paths (always present at runtime).
		if (/^wp-(admin|includes|content)\//.test(rel)) continue;

		const usesDir = /__DIR__/.test(raw);
		const usesFile = /__FILE__|plugin_dir_path\s*\(/.test(raw);
		// Check __DIR__ FIRST. __DIR__ contains "_DIR" as substring so it
		// would false-match any naive plugin-constant check below.
		const usesConstant = !usesDir && /\b(WBAM_\w+|[A-Z]+_(?:PATH|DIR|PLUGIN_DIR))\b/.test(raw);

		// __DIR__ relative — prepend the source file's directory.
		if (usesDir && srcDirRel) {
			out.add(`${srcDirRel}/${rel}`);
			continue;
		}
		// Plugin-root-relative if the expression used a plugin constant
		// or plugin_dir_path(__FILE__). Keep rel as-is.
		if (usesConstant || usesFile) {
			out.add(rel);
			continue;
		}
		// Fall back to plugin-root-relative.
		out.add(rel);
	}
	return [...out];
}

function isExcluded(relPath, patterns) {
	// Patterns are ALWAYS plugin-root-relative. A bare "vendor" means
	// only "/vendor" and "/vendor/..." — NOT "assets/vendor" anywhere.
	// This matches what the user's mental model is for .distignore in
	// a WordPress plugin context (we want explicit control, not
	// gitignore's walk-everywhere semantics).
	//
	// Semantics supported:
	//   vendor        -> top-level dir/file only
	//   /vendor       -> same as above (leading slash allowed)
	//   vendor/bin    -> top-level vendor/bin subtree
	//   *.log         -> any file ending in .log, anywhere
	//   .DS_Store     -> any file named .DS_Store, anywhere
	const name = relPath.split('/').pop();
	for (const raw of patterns) {
		let pat = raw;
		if (pat.startsWith('/')) pat = pat.slice(1);
		if (pat.endsWith('/*')) pat = pat.slice(0, -2);
		// Wildcard extension patterns ("*.log") match any file by suffix.
		if (pat.startsWith('*')) {
			if (name.endsWith(pat.slice(1))) return true;
			continue;
		}
		// Bare dot-prefixed names or filenames with no slash match
		// anywhere (they're "file-name" patterns, not path patterns):
		// .DS_Store, Thumbs.db, .editorconfig, etc.
		if (!pat.includes('/')) {
			// If the pattern is a file-name with an extension (has a dot
			// after the first char), match basename anywhere.
			// Otherwise it's a bare dir/file name — match plugin root only.
			const looksLikeFilename = /\.[a-z0-9]+$/i.test(pat);
			if (looksLikeFilename) {
				if (name === pat) return true;
				continue;
			}
			// Bare name: plugin-root-relative only.
			if (relPath === pat || relPath.startsWith(pat + '/')) return true;
			continue;
		}
		// Path pattern (contains slash) — exact match or prefix.
		if (relPath === pat || relPath.startsWith(pat + '/')) return true;
	}
	return false;
}

function main() {
	const { mainFile, slug, releaseName, version } = parseMainPlugin();
	console.log(BOLD(`\nBuilding release: ${releaseName} ${version}`));

	const patterns = readDistignore();
	const DIST = resolve(ROOT, 'dist');
	const WORK = resolve(DIST, `_build-${slug}`);
	mkdirSync(DIST, { recursive: true });

	// 1. Archive HEAD.
	rmSync(WORK, { recursive: true, force: true });
	mkdirSync(WORK, { recursive: true });
	const rawZip = resolve(WORK, 'raw.zip');
	run('git', ['archive', '--format=zip', `--prefix=${slug}/`, 'HEAD', '-o', rawZip]);
	console.log(DIM(`  git archive -> ${relative(ROOT, rawZip)}`));

	// 2. Extract + strip distignore paths.
	const extractRoot = resolve(WORK, 'extract');
	mkdirSync(extractRoot, { recursive: true });
	run('unzip', ['-q', rawZip, '-d', extractRoot]);
	const pluginDir = resolve(extractRoot, slug);
	let stripped = 0;

	function walkAll(dir) {
		const out = [];
		function visit(current) {
			if (!existsSync(current)) return;
			for (const entry of readdirSync(current)) {
				const full = join(current, entry);
				const s = statSync(full);
				const rel = relative(pluginDir, full);
				if (s.isDirectory()) {
					out.push({ path: full, rel, isDir: true });
					visit(full);
				} else {
					out.push({ path: full, rel, isDir: false });
				}
			}
		}
		visit(dir);
		return out;
	}

	const entries = walkAll(pluginDir);
	const deletedDirs = [];
	for (const e of entries) {
		if (!e.isDir) continue;
		if (deletedDirs.some((d) => e.rel === d || e.rel.startsWith(d + '/'))) continue;
		if (isExcluded(e.rel, patterns)) {
			rmSync(e.path, { recursive: true, force: true });
			deletedDirs.push(e.rel);
			stripped++;
		}
	}
	for (const e of entries) {
		if (e.isDir) continue;
		if (deletedDirs.some((d) => e.rel.startsWith(d + '/'))) continue;
		if (!existsSync(e.path)) continue;
		if (isExcluded(e.rel, patterns)) {
			rmSync(e.path, { force: true });
			stripped++;
		}
	}
	console.log(DIM(`  distignore  -> stripped ${stripped} entries (dirs + files)`));

	// 3. Completeness checks.
	const errors = [];

	function requireInZip(srcRel, kind) {
		const inZip = resolve(pluginDir, srcRel);
		if (!existsSync(inZip)) errors.push({ kind, path: srcRel });
	}

	// 3a. CSS + JS across ALL of assets/ (not just assets/css and
	// assets/js). Plugins commonly stash vendored libs at
	// assets/vendor/ (lucide, chart.js, etc.) and those must ship too.
	for (const f of walk('assets', ['.css', '.js'])) {
		const rel = relative(ROOT, f);
		if (isExcluded(rel, patterns)) continue;
		const kind = f.endsWith('.css') ? 'CSS' : 'JS';
		requireInZip(rel, kind);
	}
	// 3c. Every PHP file under includes/.
	for (const phpFile of walk('includes', ['.php'])) {
		const rel = relative(ROOT, phpFile);
		if (isExcluded(rel, patterns)) continue;
		requireInZip(rel, 'PHP');
	}
	// 3d. require / require_once / include targets across the whole tree.
	// Scans the main plugin file plus EVERY PHP file under includes/ so the
	// check catches deep require paths like vendor/wbcom-credits-sdk loaded
	// from includes/Core/class-credits-bridge.php. Without this Pro 1.5.0
	// shipped with a fatal on activation because vendor/wbcom-credits-sdk
	// was stripped by .distignore and only that one deep file referenced it.
	const requireSources = [mainFile, ...walk('includes', ['.php'])];
	const requiredPaths = new Set();
	for (const src of requireSources) {
		for (const r of parseRequires(src)) requiredPaths.add(r);
	}
	for (const r of requiredPaths) {
		if (!r.endsWith('.php')) continue;
		if (isExcluded(r, patterns)) continue;
		requireInZip(r, 'require target');
	}

	if (errors.length > 0) {
		console.log('');
		for (const e of errors) console.error(RED(`  x ${e.kind} missing from zip: ${e.path}`));
		console.log('');
		die(1, `${errors.length} file(s) missing from release zip. Fix .distignore or ensure files are committed.`);
	}

	// 4. Build final zip. Using cwd instead of "cd X && zip" keeps us shell-free.
	const outZip = resolve(DIST, `${releaseName}-${version}.zip`);
	rmSync(outZip, { force: true });
	run('zip', ['-rq', outZip, slug], { cwd: extractRoot });

	// 5. Summary.
	const zipStats = statSync(outZip);
	const listing = run('unzip', ['-l', outZip]);
	const lastLine = listing.trim().split('\n').pop().trim();
	const entryCount = lastLine.split(/\s+/)[1] || '?';
	console.log('');
	console.log(GREEN('OK Release zip built'));
	console.log(`  Path:  ${relative(ROOT, outZip)}`);
	console.log(`  Size:  ${(zipStats.size / 1024).toFixed(0)} KB`);
	console.log(`  Files: ${entryCount}`);
	console.log(GREEN('OK Completeness checks passed (CSS, JS, PHP, require targets)'));

	// 6. Cleanup work dir.
	rmSync(WORK, { recursive: true, force: true });
}

main();

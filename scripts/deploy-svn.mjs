#!/usr/bin/env node

/**
 * Publish a Joinotify release to the WordPress.org SVN repository.
 *
 * Git stays the development history; SVN is only the publishing channel. This
 * script drives the whole cycle:
 *   1. Build the package (release/joinotify/) unless told to reuse it.
 *   2. Check out plugins.svn.wordpress.org/<slug> into .wporg-svn/ (sparse: the
 *      full tags/ history is hundreds of megabytes and is never needed locally).
 *   3. Mirror the staged package into trunk/, adding and removing files so SVN
 *      matches the build exactly.
 *   4. Mirror .wordpress-org/ into assets/ (banner, icon, screenshots).
 *   5. Copy trunk to tags/<version>.
 *   6. Commit trunk and the tag in a single revision — but only with --commit.
 *
 * Nothing is published without --commit. A bare run stages everything and prints
 * the pending changes, so the release can be inspected before it goes public.
 *
 * Usage:
 *   node scripts/deploy-svn.mjs [flags]
 *
 * Flags:
 *   --commit             Actually commit to WordPress.org. Omitted, the run is a
 *                        local dry run and publishes nothing.
 *   --skip-build         Reuse release/joinotify/ instead of rebuilding.
 *   --trunk-only         Update trunk without creating a tag. For readme-only
 *                        fixes (tested-up-to, description, FAQ) that should not
 *                        ship a new version.
 *   --assets-only        Only sync .wordpress-org/ -> assets/. Directory page
 *                        artwork, no code.
 *   --force-tag          Replace tags/<version> if it already exists. Published
 *                        tags are what users install; only for a tag that was
 *                        just created and never announced.
 *   --username=<name>    WordPress.org username (default: $WPORG_USERNAME).
 *   --password=<pass>    WordPress.org password (default: $WPORG_PASSWORD). Only
 *                        for unattended runs; left unset, svn prompts once and
 *                        caches the credential itself.
 *   --slug=<slug>        Plugin slug on WordPress.org (default: $WPORG_SLUG, or
 *                        joinotify).
 *   --message=<text>     Commit message (default: "Release <version>").
 *
 * Credentials and machine paths are read from a Git-ignored `.env` at the
 * repository root — copy `.env.example` and fill it in.
 */

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

import { loadEnv } from './env.mjs';
import { resolveVersion } from './version.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

// Credentials come from .env before the flags are resolved, so $WPORG_* can act
// as the default for --username / --password / --slug.
loadEnv(root);

/* ------------------------------------------------------------------ flags */

const argv = process.argv.slice(2);
const hasFlag = (name) => argv.includes(name);
const getOpt = (name, fallback) => {
	const prefix = `${name}=`;
	const match = argv.find((arg) => arg.startsWith(prefix));
	return match ? match.slice(prefix.length) : fallback;
};

const opts = {
	commit: hasFlag('--commit'),
	skipBuild: hasFlag('--skip-build'),
	trunkOnly: hasFlag('--trunk-only'),
	assetsOnly: hasFlag('--assets-only'),
	forceTag: hasFlag('--force-tag'),
	username: getOpt('--username', process.env.WPORG_USERNAME || ''),
	password: getOpt('--password', process.env.WPORG_PASSWORD || ''),
	slug: getOpt('--slug', process.env.WPORG_SLUG || 'joinotify'),
	message: getOpt('--message', ''),
};

const slug = opts.slug;
const repoUrl = `https://plugins.svn.wordpress.org/${slug}/`;
const workingCopy = path.join(root, '.wporg-svn');
const stagingDir = path.join(root, 'release', slug);
const assetsSource = path.join(root, '.wordpress-org');

/* ---------------------------------------------------------------- helpers */

const log = (msg) => console.log(`\x1b[36m▶\x1b[0m ${msg}`);
const ok = (msg) => console.log(`\x1b[32m✓\x1b[0m ${msg}`);
const warn = (msg) => console.log(`\x1b[33m!\x1b[0m ${msg}`);

/**
 * Run a command, streaming its output. Throws on a non-zero exit.
 */
function run(command, args, cwd = root) {
	log(mask(`${command} ${args.join(' ')}`));

	// shell:true lets Windows resolve svn.exe / npm.cmd from PATH.
	const result = spawnSync(command, args, { cwd, stdio: 'inherit', shell: true });

	if (result.status !== 0) {
		throw new Error(mask(`Command failed (exit ${result.status}): ${command} ${args.join(' ')}`));
	}
}

/**
 * Run a command and return its stdout. Throws on a non-zero exit.
 */
function capture(command, args, cwd = root) {
	const result = spawnSync(command, args, { cwd, encoding: 'utf8', shell: true });

	if (result.status !== 0) {
		throw new Error(mask(`Command failed (exit ${result.status}): ${command} ${args.join(' ')}\n${result.stderr || ''}`));
	}

	return result.stdout || '';
}

/**
 * SVN auth flags.
 *
 * With only a username, svn reuses a cached credential for that user and prompts
 * for the password on the first commit, caching it itself — the friendly path for
 * a human deploy.
 *
 * A password (from $WPORG_PASSWORD or --password) is for unattended runs, where
 * there is nobody to answer the prompt. It comes with --non-interactive so a
 * wrong credential fails instead of hanging, and --no-auth-cache so the secret is
 * not copied into svn's on-disk auth store — the .env is already the one place
 * that holds it.
 */
function authArgs() {
	const args = [];

	if (opts.username) {
		args.push('--username', quote(opts.username));
	}

	if (opts.password) {
		args.push('--password', quoteSecret(opts.password), '--non-interactive', '--no-auth-cache');
	}

	return args;
}

/**
 * Hide the password in anything printed. run() echoes every command it runs, and
 * a deploy log is routinely pasted into an issue or a CI transcript.
 */
function mask(text) {
	if (!opts.password) {
		return text;
	}

	return [quoteSecret(opts.password), opts.password].reduce(
		(out, secret) => out.split(secret).join('********'),
		text,
	);
}

/**
 * List every file under a directory, as paths relative to it.
 *
 * @param {string} dir Directory to walk.
 * @param {(name: string) => boolean} [skip] Return true to skip an entry.
 */
export async function listFiles(dir, skip = () => false) {
	const found = [];

	async function walk(current, prefix) {
		const entries = await fs.readdir(current, { withFileTypes: true });

		for (const entry of entries) {
			if (skip(entry.name)) {
				continue;
			}

			const relative = prefix ? `${prefix}/${entry.name}` : entry.name;

			if (entry.isDirectory()) {
				await walk(path.join(current, entry.name), relative);
			} else {
				found.push(relative);
			}
		}
	}

	await walk(dir, '');

	return found;
}

/**
 * Make destDir hold exactly what sourceDir holds, leaving SVN's own metadata
 * alone. Files gone from the build are deleted rather than left behind — a
 * stale file in trunk ships to every user and is invisible in a diff of what
 * changed.
 */
export async function mirror(sourceDir, destDir, skipSource = () => false) {
	const isSvnMeta = (name) => name === '.svn';

	const sourceFiles = await listFiles(sourceDir, skipSource);
	const destFiles = existsSync(destDir) ? await listFiles(destDir, isSvnMeta) : [];

	for (const relative of sourceFiles) {
		const target = path.join(destDir, relative);
		await fs.mkdir(path.dirname(target), { recursive: true });
		await fs.copyFile(path.join(sourceDir, relative), target);
	}

	const keep = new Set(sourceFiles);
	const removed = destFiles.filter((relative) => !keep.has(relative));

	for (const relative of removed) {
		await fs.rm(path.join(destDir, relative), { force: true });
	}

	await pruneEmptyDirs(destDir, isSvnMeta);

	return { copied: sourceFiles.length, removed: removed.length };
}

/**
 * Drop directories left empty after a mirror, so `svn status` doesn't report
 * unversioned husks.
 */
async function pruneEmptyDirs(dir, skip) {
	const entries = await fs.readdir(dir, { withFileTypes: true });

	for (const entry of entries) {
		if (!entry.isDirectory() || skip(entry.name)) {
			continue;
		}

		const child = path.join(dir, entry.name);
		await pruneEmptyDirs(child, skip);

		if ((await fs.readdir(child)).length === 0) {
			await fs.rmdir(child);
		}
	}
}

/**
 * Tell SVN about files the mirror created or deleted.
 *
 * `svn status` reports "?" for unversioned paths and "!" for versioned paths
 * whose file is gone. Without this step a commit would carry neither.
 */
function syncSvnState(cwd) {
	// Columns 0-6 are status flags; the path starts at column 8 and may itself
	// contain spaces, so slice rather than split.
	const lines = capture('svn', ['status'], cwd).split(/\r?\n/).filter(Boolean);

	const added = lines.filter((line) => line.startsWith('?')).map((line) => line.slice(8).trim());
	const missing = lines.filter((line) => line.startsWith('!')).map((line) => line.slice(8).trim());

	for (const target of added) {
		run('svn', ['add', '--parents', '--force', quote(target)], cwd);
	}

	for (const target of missing) {
		run('svn', ['rm', '--force', quote(target)], cwd);
	}

	return { added: added.length, missing: missing.length };
}

/**
 * Quote an argument for the shell, since run() uses shell:true. Paths here carry
 * spaces routinely ("Local Sites"), and a commit message can carry quotes.
 */
function quote(value) {
	return `"${String(value).replace(/"/g, '\\"')}"`;
}

/**
 * Quote a secret for the shell. Same job as quote(), but a password can carry
 * characters the shell would otherwise expand, and — unlike a path — it is never
 * a Windows path, so POSIX single-quoting is safe here.
 */
function quoteSecret(value) {
	const text = String(value);

	if (process.platform === 'win32') {
		return `"${text.replace(/"/g, '\\"')}"`;
	}

	return `'${text.replace(/'/g, "'\\''")}'`;
}

/* ----------------------------------------------------------------- stages */

function requireSvn() {
	const result = spawnSync('svn', ['--version', '--quiet'], { encoding: 'utf8', shell: true });

	if (result.status !== 0) {
		throw new Error(
			'Subversion is not installed or not on PATH.\n' +
			'    Windows: winget install TortoiseSVN.TortoiseSVN (enable the command line client during setup)\n' +
			'    macOS:   brew install svn\n' +
			'    Debian:  sudo apt install subversion',
		);
	}

	ok(`Subversion ${result.stdout.trim()} available.`);
}

/**
 * Check out the repository sparsely, or refresh an existing checkout.
 *
 * tags/ is fetched at "immediates" depth: the tag names are needed to detect a
 * duplicate, but their contents are every release ever published and would cost
 * hundreds of megabytes to no purpose.
 */
function prepareWorkingCopy() {
	if (existsSync(path.join(workingCopy, '.svn'))) {
		log('Refreshing existing SVN working copy...');
		run('svn', ['update', ...authArgs()], workingCopy);
	} else {
		log(`Checking out ${repoUrl} (this takes a minute)...`);
		run('svn', ['checkout', repoUrl, quote(workingCopy), '--depth', 'immediates', ...authArgs()]);
		run('svn', ['update', '--set-depth', 'infinity', 'trunk', ...authArgs()], workingCopy);

		// assets/ exists on every approved plugin, but not on a repository that
		// has never been written to. syncAssets() creates and adds it in that case.
		if (existsSync(path.join(workingCopy, 'assets'))) {
			run('svn', ['update', '--set-depth', 'infinity', 'assets', ...authArgs()], workingCopy);
		}
	}

	ok(`Working copy ready at ${path.relative(root, workingCopy)}.`);
}

async function syncTrunk() {
	if (!existsSync(stagingDir)) {
		throw new Error(`No staged package at ${path.relative(root, stagingDir)}. Drop --skip-build, or run "npm run build".`);
	}

	const trunk = path.join(workingCopy, 'trunk');
	log('Mirroring the build into trunk/...');

	const { copied, removed } = await mirror(stagingDir, trunk);
	const { added, missing } = syncSvnState(trunk);

	ok(`trunk/: ${copied} files staged, ${removed} deleted (svn add ${added}, svn rm ${missing}).`);
}

async function syncAssets() {
	if (!existsSync(assetsSource)) {
		warn(`No ${path.relative(root, assetsSource)}/ directory — skipping directory artwork.`);
		return;
	}

	const assets = path.join(workingCopy, 'assets');
	log('Mirroring .wordpress-org/ into assets/...');

	await fs.mkdir(assets, { recursive: true });

	// README.md documents the artwork spec for whoever replaces these files; it
	// is not artwork itself and has no business on the directory page.
	const { copied, removed } = await mirror(assetsSource, assets, (name) => name === 'README.md');
	const { added, missing } = syncSvnState(assets);

	ok(`assets/: ${copied} files staged, ${removed} deleted (svn add ${added}, svn rm ${missing}).`);
}

/**
 * Create tags/<version> as a local copy of trunk, so the tag and the trunk
 * changes land in the same revision.
 */
function createTag(version) {
	const tagPath = path.join(workingCopy, 'tags', version);

	if (existsSync(tagPath)) {
		if (!opts.forceTag) {
			throw new Error(
				`tags/${version} already exists. Bump the version, or pass --force-tag to replace it ` +
				'(only safe for a tag that was never announced).',
			);
		}

		warn(`Replacing existing tags/${version}.`);
		run('svn', ['rm', '--force', quote(path.join('tags', version))], workingCopy);
	}

	run('svn', ['copy', 'trunk', quote(path.join('tags', version))], workingCopy);
	ok(`Tagged as tags/${version}.`);
}

/**
 * Re-read Stable tag from the file that is actually about to be committed.
 *
 * resolveVersion() checked the repository copy; this checks trunk after the
 * mirror, which is what WordPress.org will read to decide which tag to serve.
 */
async function verifyStableTag(version) {
	const readmePath = path.join(workingCopy, 'trunk', 'readme.txt');
	const contents = await fs.readFile(readmePath, 'utf8');
	const stable = contents.match(/^Stable tag:\s*(.+)$/m)?.[1].trim();

	if (stable !== version) {
		throw new Error(
			`trunk/readme.txt declares "Stable tag: ${stable}" but this release is ${version}. ` +
			'WordPress.org serves whatever Stable tag names, so committing this would publish the wrong version.',
		);
	}

	ok(`trunk/readme.txt Stable tag matches ${version}.`);
}

function showPending() {
	console.log('\n\x1b[1mPending SVN changes\x1b[0m\n');

	const lines = capture('svn', ['status'], workingCopy).split(/\r?\n/).filter(Boolean);

	if (lines.length === 0) {
		warn('Nothing changed — the repository already matches this build.');
		return false;
	}

	// The first release adds every file in the package, so a raw dump would be
	// over a thousand lines and nobody would read it. Summarise by status letter
	// and show a sample; `svn status` in .wporg-svn/ gives the full list.
	const labels = { A: 'added', D: 'deleted', M: 'modified', R: 'replaced', C: 'conflicted' };
	const counts = {};

	for (const line of lines) {
		const letter = line[0];
		counts[letter] = (counts[letter] || 0) + 1;
	}

	const summary = Object.entries(counts)
		.map(([letter, count]) => `${count} ${labels[letter] || letter}`)
		.join(', ');

	const sample = 30;
	console.log(lines.slice(0, sample).join('\n'));

	if (lines.length > sample) {
		console.log(`  ... and ${lines.length - sample} more`);
	}

	console.log(`\n${summary}\n`);

	if (counts.C) {
		throw new Error('SVN reports conflicts. Resolve them in .wporg-svn/ before deploying.');
	}

	return true;
}

function commit(message) {
	run('svn', ['commit', '-m', quote(message), ...authArgs()], workingCopy);
	ok(`Committed: ${message}`);
}

/* -------------------------------------------------------------------- main */

async function main() {
	const version = await resolveVersion(root, slug, warn);

	console.log(`\n\x1b[1mDeploying ${slug} v${version} to WordPress.org\x1b[0m\n`);

	requireSvn();
	prepareWorkingCopy();

	if (opts.assetsOnly) {
		await syncAssets();
	} else {
		if (!opts.skipBuild) {
			run('npm', ['run', 'build'], root);
		}

		await syncTrunk();
		await syncAssets();
		await verifyStableTag(version);

		if (!opts.trunkOnly) {
			createTag(version);
		}
	}

	const hasChanges = showPending();

	if (!hasChanges) {
		return;
	}

	if (!opts.commit) {
		console.log(
			'\x1b[33mDry run — nothing was published.\x1b[0m\n' +
			`Review the changes above, then re-run with --commit to publish.\n` +
			`  node scripts/deploy-svn.mjs --skip-build --commit\n`,
		);
		return;
	}

	const message =
		opts.message ||
		(opts.assetsOnly
			? 'Update directory assets'
			: opts.trunkOnly
				? `Update trunk for ${version}`
				: `Release ${version}`);

	commit(message);

	console.log(
		`\n\x1b[32m\x1b[1mPublished.\x1b[0m\n` +
		`https://wordpress.org/plugins/${slug}/ updates within about 15 minutes.\n`,
	);
}

// Only deploy when invoked as a script. Importing the module (to test mirror()
// and listFiles() without an SVN client present) must not publish anything.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
	main().catch((err) => {
		console.error(`\n\x1b[31m✗ Deploy failed:\x1b[0m ${err.message}\n`);
		process.exit(1);
	});
}

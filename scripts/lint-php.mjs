/**
 * lint-php.mjs — parse every source PHP file with a PHP 7.4 binary.
 *
 * The plugin declares `Requires PHP: 7.4`, but everyone develops on PHP 8.x, so
 * nothing stops a `match` expression or a nullsafe operator from slipping in and
 * fataling on the oldest supported host. `php -l` under a real 7.4 binary is the
 * only honest check: it catches every syntax-level regression at once.
 *
 * It does not catch PHP 8 *functions* (`str_contains`, `get_debug_type`, ...),
 * which parse fine everywhere — see AGENTS.md §4 for the list to avoid.
 *
 * Usage:
 *   npm run lint:php
 *   PHP74_BIN="C:\\path\\to\\php.exe" npm run lint:php
 *
 * Resolution order for the binary: --php=<path>, $PHP74_BIN, then a few common
 * Local by Flywheel / XAMPP locations, then `php7.4` on PATH.
 */

import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, statSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// Directories that hold code we ship or run, minus everything vendored,
// generated or kept around as editor history.
const scanDirs = ['admin/src', 'templates', 'tests', 'examples'];
const scanFiles = ['joinotify.php'];
const skipDirs = new Set(['node_modules', 'vendor', 'dist', '.git', '.history', 'release']);

function findPhp() {
	const fromFlag = process.argv.find((arg) => arg.startsWith('--php='));

	const candidates = [
		fromFlag ? fromFlag.slice('--php='.length) : null,
		process.env.PHP74_BIN,
		path.join(
			os.homedir(),
			'AppData/Roaming/Local/lightning-services/php-7.4.30+6/bin/win64/php.exe',
		),
		'C:/xampp74/php/php.exe',
		'/usr/bin/php7.4',
	].filter(Boolean);

	for (const candidate of candidates) {
		if (existsSync(candidate)) {
			return candidate;
		}
	}

	// Last resort: whatever `php7.4` resolves to on PATH.
	return 'php7.4';
}

function collect(dir, out) {
	for (const entry of readdirSync(dir, { withFileTypes: true })) {
		if (entry.isDirectory()) {
			if (!skipDirs.has(entry.name)) {
				collect(path.join(dir, entry.name), out);
			}

			continue;
		}

		if (entry.name.endsWith('.php')) {
			out.push(path.join(dir, entry.name));
		}
	}

	return out;
}

const php = findPhp();
const version = spawnSync(php, ['-n', '-r', 'echo PHP_VERSION;'], { encoding: 'utf8' });

if (version.status !== 0) {
	console.error(
		`Could not run a PHP 7.4 binary ("${php}").\n` +
			'Point at one with PHP74_BIN=... or --php=<path>.',
	);
	process.exit(1);
}

if (!version.stdout.startsWith('7.4')) {
	console.error(
		`"${php}" is PHP ${version.stdout.trim()}, not 7.4. Linting against a newer ` +
			'runtime would accept syntax the oldest supported host rejects.\n' +
			'Point at a 7.4 binary with PHP74_BIN=... or --php=<path>.',
	);
	process.exit(1);
}

const files = [];

for (const rel of scanDirs) {
	const dir = path.join(root, rel);

	if (existsSync(dir) && statSync(dir).isDirectory()) {
		collect(dir, files);
	}
}

for (const rel of scanFiles) {
	const file = path.join(root, rel);

	if (existsSync(file)) {
		files.push(file);
	}
}

let failed = 0;

for (const file of files) {
	// -n ignores php.ini so the check does not depend on the local extension set.
	const result = spawnSync(php, ['-n', '-l', file], { encoding: 'utf8' });

	if (result.status !== 0) {
		failed += 1;
		console.error(`${path.relative(root, file)}\n${(result.stdout + result.stderr).trim()}\n`);
	}
}

if (failed > 0) {
	console.error(`PHP 7.4 lint failed: ${failed} of ${files.length} file(s) do not parse.`);
	process.exit(1);
}

console.log(`PHP ${version.stdout.trim()} lint passed: ${files.length} file(s).`);

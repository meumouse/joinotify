# AGENTS.md — Joinotify guide for AI agents and developers

This is the **canonical entry point** for working on the Joinotify codebase. It gathers the plugin's
construction pattern, the per-layer conventions, the central data contract, and the process rules.
Read this **before editing anything**.

> **Free software, distributed on WordPress.org.** Joinotify is licensed under the **GPLv2 or
> later** (see [`LICENSE`](LICENSE)) and every feature is unlocked — there is no premium tier,
> trial or license check. Because the plugin is published in the WordPress.org directory, changes
> must keep it inside the [plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/):
> no code executed from a remote server, no update mechanism of its own, no external request
> before the user consents, and no minified asset shipped without its source. Every external
> service the plugin talks to must stay declared in [`readme.txt`](readme.txt).

---

## 0. Golden rules (read first)

1. **Read before you write.** The central contracts (workflow tree, REST `joinotify/v1`, hooks
   `Joinotify/...`) are interdependent. Locate the matching side (frontend ↔ backend) and the
   `Workflow_Migrator` before changing a node's shape.
2. **Extend, don't edit the core.** Actions, triggers, integrations, conditions, placeholders,
   settings, REST routes, and channels are all extensible **with PHP only**, via filters/facade —
   see [`DEVELOPERS.md`](DEVELOPERS.md). Editing the core is the last resort.
3. **Match the surrounding code.** Style, naming, and comment density follow the neighboring
   file/module — don't impose personal preference.
4. **Don't invent APIs.** Use `joinotify_*()` helpers and classes that actually exist; confirm with
   a repository search before referencing a symbol.
5. **Don't commit, push, or run destructive builds** without an explicit request from the user.
6. **All source code is written in English.** Identifiers (classes, methods, variables, functions,
   files), inline comments, docblocks, and commit messages are **always in English** — this applies
   to PHP, JavaScript/TypeScript, and Vue alike. Only the narrative documentation (`*.md`) is
   written in Portuguese; match the language of the file you are editing. User-facing strings are
   authored in English and translated through the i18n pipeline (see §7).
7. **Touch both sides of the contract.** Changed a workflow node's shape? Update the serializer/parser
   (Vue) **and** the processor (PHP) — and consider the migrator. Otherwise saved workflows break.

---

## 1. What the plugin is

A WordPress plugin that builds **WhatsApp message automation workflows** in a visual drag-and-drop
builder. It connects site triggers (WooCommerce, forms, user actions) to actions (send WhatsApp,
conditions, delays, AI, etc.). Current version in [`joinotify.php`](joinotify.php) (`Version:`),
WordPress **7.0+**, PHP **8.1+**, Node **18+**.

---

## 2. Documentation map — don't duplicate, link

| Document | When to consult |
|----------|-----------------|
| **`AGENTS.md`** (this) | Project standards, central contract, conventions, process. |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Detailed conventions and delivery checklist. |
| [`README.md`](README.md) | Overview, architecture, installation, build/packaging. |
| [`DEVELOPERS.md`](DEVELOPERS.md) | **PHP extension API** (actions, triggers, integrations, conditions, placeholders, REST, notification/OTP channels). |
| [`app/README.md`](app/README.md) | Vue 3 + Vite frontend: entries, REST bootstrap, builder store, Tailwind, i18n. |
| [`languages/README.md`](languages/README.md) | i18n pipeline (`.pot`, AI/Google translation, `.mo`/`.l10n.php`/`.json` compilation). |
| [`docs/integrations.md`](docs/integrations.md) | Available integrations and their triggers. |
| [`examples/joinotify-extension-example.php`](examples/joinotify-extension-example.php) | Runnable third-party extension example. |
| [`CHANGELOG.md`](CHANGELOG.md) | Version history. |

---

## 3. Architecture and construction pattern

Since **2.0.0** there is a **strict separation** between backend (PHP: REST + data schemas only) and
frontend (Vue: consumes everything via REST). **There is no server-side HTML injection and no jQuery.**

```
joinotify/
├── joinotify.php          # Bootstrap: loads Composer autoloader + instantiates Core\Init
├── admin/                 # PHP BACKEND (PSR-4, namespace MeuMouse\Joinotify\ → admin/src/)
│   ├── src/
│   │   ├── AI/            #   AI-driven workflow generation (via the WordPress AI Client)
│   │   ├── Admin/         #   Server-side screens/settings/builder, Workflow_Migrator, Queue, History
│   │   ├── Api/           #   Send Controller, Extensions (extension facade)
│   │   ├── Assets/        #   Asset registration (reads the Vite manifest)
│   │   ├── Builder/       #   Action catalog, Placeholders, Triggers, Attachments
│   │   ├── Core/          #   Init, Workflow_Processor (engine), Helpers, Upgrader, Cron...
│   │   ├── Cron/          #   Scheduled tasks (WP-Cron)
│   │   ├── Integrations/  #   WooCommerce, WPForms, Elementor, Flexify Checkout, Telegram, Resend...
│   │   ├── Notifications/ #   Notification channel layer (Channel_Interface)
│   │   ├── Otp_Login/     #   Passwordless login (OTP channels)
│   │   ├── Rest/          #   REST routes (namespace joinotify/v1)
│   │   ├── Validations/   #   Workflow conditions and validations
│   │   └── Views/         #   Residual PHP views
│   └── vendor/            #   Composer dependencies (build-generated — NOT versioned)
├── app/                   # Vue 3 + Vite FRONTEND (see app/README.md)
│   ├── src/               #   entries, pages, components, builder, stores, serializers, parsers...
│   └── dist/              #   Production build (generated — NOT versioned)
├── languages/             # Node i18n pipeline (see languages/README.md)
├── templates/             # PHP templates (e.g. OTP login) — markup scanned by Tailwind
├── assets/                # Static assets (brand, icons)
├── dist/                  # Distributed workflow templates (NOT shipped in the ZIP)
├── docs/ · examples/ · tests/
├── scripts/build.mjs      # Build/packaging pipeline (orchestrates everything)
├── scripts/deploy-svn.mjs # Publishes a release to the WordPress.org SVN repository
├── .wordpress-org/        # Directory page artwork (banner, icon, screenshots) — not shipped
├── readme.txt             # WordPress.org plugin page (headers, external services)
├── LICENSE                # GNU GPL v2 or later
└── *.md                   # AGENTS, CONTRIBUTING, README, DEVELOPERS, CHANGELOG
```

- **Backend (PHP):** API only (REST under `joinotify/v1`) and schema provider. PSR-4, root namespace
  `MeuMouse\Joinotify\` → `admin/src/` (see [`admin/composer.json`](admin/composer.json)). Requires
  `giggsey/libphonenumber-for-php`.
- **Frontend (Vue):** each admin screen is an **independent Vue application** (multi-page) that
  consumes everything via REST. Details in [`app/README.md`](app/README.md).
- **Workflow engine:** [`Core/Workflow_Processor.php`](admin/src/Core/Workflow_Processor.php).

### The central contract: the workflow — understanding this solves 80% of tasks

- A workflow is a **tree of nodes** (trigger → actions/conditions) stored in the
  `joinotify_workflow_content` post meta of the `joinotify-workflow` CPT.
- The **frontend** models it as a nested `WorkflowNode` tree
  (`{ id, type, data, children, branches? }`) in the Pinia store
  [`useWorkflowBuilderStore.ts`](app/src/stores/useWorkflowBuilderStore.ts); serializes via
  [`workflowSerializer.ts`](app/src/serializers/workflowSerializer.ts) and re-hydrates via
  [`workflowParser.ts`](app/src/parsers/workflowParser.ts).
- The **backend** consumes exactly this format and executes it in
  [`Core/Workflow_Processor.php`](admin/src/Core/Workflow_Processor.php).
- **`connection_from` is the source of truth for the wiring.** `children`/`branches` can drift and
  must be reconstructed from `connection_from` when they conflict.

> **Shared-contract rule:** frontend and backend share the same `workflow_content`. When you change
> a node's shape, update **both sides** (serializer/parser in Vue **and** the processor in PHP) and
> consider the [`Workflow_Migrator`](admin/src/Admin/Builder/Workflow_Migrator.php) for legacy content.

---

## 4. Conventions — Backend (PHP)

Follow the style already present in `admin/src/`.

- **PSR-4 + WordPress Coding Standards.** One class per file; the file name matches the class name
  (`Workflow_Processor.php` → `class Workflow_Processor`).
- **TAB indentation** (not spaces).
- **Security guard** at the top of every PHP file: `defined('ABSPATH') || exit;`.
- **Long array syntax** (`array( ... )`) in the core (the short examples in `DEVELOPERS.md` use `[]`,
  but the core code uses `array()`).
- **Spaces inside parentheses**: `current_user_can( 'manage_options' )`.
- **Naming:** classes in `Studly_Snake_Case` (`Phone_Manager`); methods/variables/functions in
  `snake_case`. All identifiers in **English**.
- **Mandatory docblocks** on classes and public methods (written in English):

  ```php
  /**
   * Short one-line summary.
   *
   * @since 1.0.0
   * @version 2.2.0
   * @param string $sender Sender phone in digits.
   * @return bool
   */
  ```

  - `@since` = version the symbol **first appeared** (never change it afterwards).
  - `@version` = version of the **last change** (bump it when you touch the symbol). Use the current
    plugin version (see `joinotify.php` → `Version:`).
- **Plugin hooks** follow the `Joinotify/Area/Name` pattern (e.g. `Joinotify/Builder/Actions`).
- **Action handlers return `bool`.** Returning a non-bool may halt the funnel.
- **i18n:** every user-facing string goes through `__()`, `esc_html__()`, etc., with the `'joinotify'`
  text domain.
- **Runtime helpers:** prefer the global `joinotify_*()` helpers (e.g. `joinotify_replace_placeholders()`,
  `joinotify_send_whatsapp_message_text()`) over coupling directly to namespaced classes. Full table
  in [`DEVELOPERS.md` → Runtime helpers](DEVELOPERS.md#runtime-helpers).

---

## 5. Conventions — Frontend (Vue 3 + TS)

Full architecture in [`app/README.md`](app/README.md). Summary:

- **Vue 3 with `<script setup>` + Composition API.** **Strict** TypeScript.
- **State in Pinia** (`stores/`). The builder store is the source of truth for the workflow tree.
- **Tailwind CSS 3** for styling; `important: true` is enabled to beat the WordPress admin CSS. Avoid
  loose CSS when a utility class does the job. Tailwind `content` also scans
  `../templates/otp-login/**/*.php`.
- **Alias `@/*` → `src/*`** (in [`app/tsconfig.json`](app/tsconfig.json)). Prefer it over long
  relative paths.
- **Multi-page:** each screen is an independent Vue app in `src/entries/<page>.js` that calls
  `mountPage('<handle>', PageComponent)`. When creating a new page, register the entry in
  `vite.config.js` **and** the matching script handle in the backend.
- **Communication via REST only** under `joinotify/v1`, using the client in
  [`utils/api.js`](app/src/utils/api.js) with the WordPress nonce. Bootstrap is an **async GET**
  (see `utils/bootstrap.js`); no heavy payload embedded in the HTML.
- **i18n:** use `wp.i18n.__(text, 'joinotify')` via [`utils/i18n.ts`](app/src/utils/i18n.ts).
  Translatable strings must use the `@wordpress/i18n` functions to be picked up by the `.pot` extractor.

> **Builder golden rule:** adding an **action** or a **trigger** normally **does not require frontend
> changes** — register it in the backend via the filters in [`DEVELOPERS.md`](DEVELOPERS.md) and the
> action library renders the `settings_schema` on its own (zero JS per action). Only touch the Vue
> side when you need a truly custom settings component.

**Cache-busting:** `app.js`/CSS are emitted **without a hash**; the backend versions them by
`filemtime` (`?ver=...`). If a frontend change "doesn't show up" after the build, suspect the asset
cache first (force refresh / rebuild).

---

## 6. Extension model (the heart of the plugin)

Almost all new domain functionality should be **registered**, not hardcoded in the core. Prefer the
recommended facade (`joinotify_register_*()`) or the raw filters underneath. Full reference and
examples in [`DEVELOPERS.md`](DEVELOPERS.md):

- **Actions** — `joinotify_register_action()` (+ category, handler, canvas description). The
  `settings_schema` renders the settings UI with no JavaScript.
- **Triggers/Integrations** — `joinotify_register_integration()` + `joinotify_register_trigger()`;
  dispatch at runtime with `joinotify_dispatch_trigger()`.
- **Conditions** — conditions/operators/value resolvers per trigger.
- **Placeholders** — static `{{ token }}` and dynamic (bracket-syntax) per integration.
- **Settings** — declarative tabs/sections/fields; custom controls via the Vue component registry.
- **REST** — `joinotify_register_rest_route()` under `joinotify/v1`.
- **Channels** — notification (`Notifications\Channel_Interface`) and OTP
  (`Otp_Login\Channel_Interface`): 1 class + 1 filter.

> **Register early** (`plugins_loaded`/`init`) so the filters are wired before the builder bootstrap
> and the REST catalog are built.

---

## 7. Internationalization (i18n)

- Full pipeline in [`languages/README.md`](languages/README.md). Text domain: `joinotify`.
- Maintained languages: **pt_BR, en_US, es_ES**.
- **Translation-only changes don't require a frontend rebuild** — JS translations are injected at
  runtime via `wp.i18n.setLocaleData` per script handle.
- When **adding/changing strings**: run `npm run pot` in `languages/`, translate
  (`npm run translate` / `:ai`) and compile (`compile:mo`, `compile:php`, `compile:json`).
- Each script handle's `.json` carries **only the strings its own bundle uses**. The handle list is
  derived from `app/src/entries/*` and the `mountPage('<handle>', …)` call in each entry, so a new
  page needs no separate list — but an entry without a resolvable handle fails the build.

---

## 8. Build and packaging

Orchestrated by [`scripts/build.mjs`](scripts/build.mjs), from the root:

| Command | Description |
|---------|-------------|
| `npm run build` | Full build + ZIP → `release/joinotify-<version>.zip`. |
| `npm run build:fast` | Reuses artifacts (skips app, composer, and translations). |
| `npm run build:translate` | Re-translates `.po` via AI before compiling (requires `OPENAI_API_KEY`). |
| `npm run build:app` | Frontend only (`app/dist`). |

Full build order: **frontend → composer `--no-dev` → translations → staging → ZIP.** Flags:
`--skip-app`, `--skip-composer`, `--skip-translations`, `--translate`, `--engine=<name>`,
`--no-install`, `--no-zip`, `--ship-locales`. Initial setup: `cd app && npm install`;
`cd languages && npm install`; `cd admin && composer install`; `npm install` at the root.

The build refuses to run when `joinotify.php` (header and `$plugin_version`), the `Stable tag` in
`readme.txt` and `package.json` disagree — the gate lives in
[`scripts/version.mjs`](scripts/version.mjs) and the SVN deploy shares it.

**Only `joinotify.pot` ships.** WordPress.org generates and delivers every locale from
translate.wordpress.org, and the plugin review team asks that packages not duplicate that channel,
so the compiled catalogues (`.po`/`.mo`/`.l10n.php`/`.json`) stay out of the ZIP. Until the strings
are imported and approved there, non-English installs fall back to English. `--ship-locales` builds
a package that carries them, which is what installs outside the directory need — they get no
language packs.

### Publishing to WordPress.org

Git is the development history; SVN is only the publishing channel, driven by
[`scripts/deploy-svn.mjs`](scripts/deploy-svn.mjs). **Nothing is published without `--commit`** — a
bare run mirrors the build into `trunk/`, creates the tag locally and prints the pending diff.

| Command | Description |
|---------|-------------|
| `npm run deploy` | Dry run: build, mirror into `trunk/`, tag, show the diff. Publishes nothing. |
| `npm run deploy:commit` | Commits `trunk/` and `tags/<version>` in one revision. |
| `npm run deploy:assets` | Directory artwork only (`.wordpress-org/` → SVN `assets/`). |
| `npm run deploy:trunk` | Updates `trunk/` without tagging, for readme-only fixes. |

The working copy lives in `.wporg-svn/` (Git-ignored), with `tags/` at shallow depth. Needs an `svn`
client on PATH and `WPORG_USERNAME` (or `--username=<name>`).

> **SVN `assets/` ≠ the plugin's `assets/`.** The SVN one sits beside `trunk/`, outside the
> installed package, and holds the banner, icon and screenshots — see
> [`.wordpress-org/README.md`](.wordpress-org/README.md). The plugin's own `assets/brand/` still
> ships inside the ZIP.

---

## 9. Testing and verification

The tests in [`tests/`](tests/) are **standalone harnesses** (no PhpUnit, no WordPress bootstrap),
meant to run with the local PHP:

```bash
# Adjust the php.exe path for your Local/XAMPP environment.
& "C:\path\to\php.exe" tests/workflow-migrator-test.php
```

> **PHP CLI lacks sodium.** The PHP on PATH may not have the sodium extension. Run the harnesses with
> Local's PHP binary and the required `-d extension=...` flags, not the generic PHP on PATH.

Relevant harnesses include: `workflow-migrator-test.php`, `upgrader-test.php`,
`schedule-cron-args-test.php`, `attachments-test.php`, `loop-test.php`, `refund-trigger-test.php`,
and the `licensing-*-test.php` suite. Fixtures in `tests/fixtures/`.

**Before finishing a change:**

1. Run the relevant harness(es) if you touched migration, upgrade, cron, attachments, loop, or licensing.
2. Frontend: `npm run build:app` (or `npm run dev`) and confirm it compiles with no TypeScript errors.
3. Changed the release ZIP: `npm run build` and confirm the package is generated.
4. Added/changed strings: regenerate the `.pot`.

Report results **honestly**: if a test fails, say so and show the output.

---

## 10. Git

- **Branches:** work on a feature/fix branch off `main`. **Do not** commit directly to `main`.
- **Conventional Commits** in English, imperative mood:

  ```
  feat(queue): add processing-queue subpage for scheduled segments
  fix(cron): store positional args for WP-Cron scheduled segments
  docs: add language pipeline documentation
  ```

  Types: `feat`, `fix`, `refactor`, `style`, `docs`, `chore`, `i18n`. Common scopes: `builder`,
  `ai`, `cron`, `queue`, `history`, `settings`, `core`, `i18n`.
- **Don't commit generated artifacts or secrets** (already in [`.gitignore`](.gitignore)): `app/dist/`,
  `admin/vendor`, `node_modules/`, `release/`, `.wporg-svn/`, `.env`, `composer.lock`, `.claude`,
  `.history`.
- **Commit/push only when explicitly requested** by the user.

---

## 11. Delivery checklist

- [ ] Code matches the neighboring module's style (TAB in PHP; Vue/TS conventions in the app).
- [ ] All source code (identifiers, comments, docblocks) is in **English**.
- [ ] PHP docblocks updated (`@since`/`@version`) on new/changed symbols.
- [ ] Workflow-contract change reflected on **both sides** (serializer/parser + PHP processor) and,
      if needed, the migrator.
- [ ] New strings go through `__()`/`wp.i18n.__` and the `.pot` was regenerated.
- [ ] Extension done via filters/facade — **no core edits** when avoidable.
- [ ] No generated artifacts (`dist/`, `vendor/`, `node_modules/`) or secrets in the commit.
- [ ] Relevant tests run; build green when applicable.
- [ ] Commit follows Conventional Commits.
- [ ] `CHANGELOG.md` updated for user-facing changes.

---

## 12. Environment

- Development via **WordPress Local (Windows)**. `php.exe` paths vary per installation — **don't
  assume a fixed path**.
- Primary shell is PowerShell; Bash (POSIX) is also available.

---

© 2026 MeuMouse.com — Soluções Digitais LTDA. All rights reserved.

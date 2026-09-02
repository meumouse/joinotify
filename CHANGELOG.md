# Changelog

All notable changes to Joinotify are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Two notes on the history below. Releases before 2.0.0 did not strictly follow SemVer — features shipped as patch releases more than once (1.2.2 added two locales, 1.3.3 added triggers and placeholders, 1.4.5 and 1.4.7 added features) — so the numbering of that period cannot be read as a compatibility promise. And the entries before 2.0.0 were carried over from the original history, keeping the level of detail they had at the time.

## [Unreleased]

## [2.3.4] - 2026-09-01

Three hooks that never fired. All three come from the same place: `Core\Init` builds the plugin's classes from callbacks on `init`, `admin_init` and `wp_loaded`, so whatever a constructor registers is registered while that hook is already running. `WP_Hook::apply_filters()` iterates a snapshot of the callbacks at the priority it is executing, and anything hooked to a priority that has already gone past — the current one included — is dropped without a warning.

### Fixed

- **Any log written before `wp_loaded` crashed the site with a fatal `ValueError: Path cannot be empty`**
    - `Core\Logger` resolved its file path (`uploads/joinotify/logs.txt`) only inside the constructor, and the class is bootstrapped on `wp_loaded`
    - Every static `Logger::register_log()` call that runs earlier — `Notification_Queue::maybe_process_due_items()` on `init`, cron, REST and CLI — therefore reached `file_put_contents()` with a `null` path, which is a fatal error on PHP 8
    - The path is now resolved lazily on first use, so the static API is safe from any hook. When the uploads folder is unavailable the flat-file write is skipped instead of fatalling; the entry is still recorded in the structured `Debug_Log` table
- **The `joinotify-workflow` post type was never registered**
    - `Core\Workflow_Post_Type` is bootstrapped by `Core\Init` on `init` at priority 10, and its constructor hooked `init` at that same priority
    - `WP_Hook::apply_filters()` iterates a snapshot of the callbacks at the priority it is running, so a callback added to that very priority is dropped for the rest of the request. `register_post_type()` therefore never ran, and neither did the one-time `flush_rewrite_rules()` behind it
    - Workflows kept saving and querying — WordPress tolerates unregistered types in `wp_insert_post()` and `WP_Query` — which is why this went unnoticed. What did not apply were the declared capabilities: with no post type object, `map_meta_cap()` falls back to `edit_others_posts`, so any Editor could edit and delete workflows that were meant to require `manage_options`
    - The post type is now registered inline when the class is built inside `init`, and still hooked when it is built earlier
- **The WooCommerce HPOS compatibility declaration never reached WooCommerce**
    - WooCommerce fires `before_woocommerce_init` from `WooCommerce::init()`, hooked to `init` at priority 0; `Core\Compatibility` registered its callback from a constructor running at `init` priority 10, always after the action had fired
    - `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )` was consequently never called, and the plugin was listed as incompatible under WooCommerce → Settings → Advanced → Features
    - The declaration is now registered by `Core\Compatibility::init()` at plugin load time, well before `init`

## [2.3.3] - 2026-08-28

Release tooling only. Nothing inside the shipped package changed since 2.3.2 — the plugin code, the frontend build and the translation catalogues are the same files.

### Added

- **Local credentials and machine paths now come from a Git-ignored `.env`** at the repository root, read by `scripts/env.mjs`
    - `scripts/deploy-svn.mjs` takes `WPORG_USERNAME`, `WPORG_PASSWORD` and `WPORG_SLUG` from it, so publishing no longer needs the login passed as a flag that ends up in the shell history
    - `scripts/build.mjs` and `scripts/lint-php.mjs` read `PHP81_BIN` and the translation keys from the same file
    - `.env.example` is the tracked documentation of every variable the tooling reads. A variable already present in the environment always wins, so CI secrets are never overridden by a stray local file
    - The parser is deliberately dependency-free, keeping the root package at its single devDependency
- The WordPress.org directory artwork lives in `.wordpress-org/` — banner in both sizes, icon and screenshots — which the deploy mirrors into the SVN `assets/` directory

### Fixed

- **The SVN deploy could corrupt its own working copy while creating a tag**
    - The checkout is sparse, and `tags/` came down at depth `empty`, so the tag names never reached the working copy
    - A tag copied under a depth-empty parent falls outside the next update's target set: `svn update` dropped it from disk while leaving the scheduled add in the entries database, and the following `svn copy` failed against that orphan with `E155033`
    - The same gap made the duplicate-tag guard in `createTag()` unreachable, which in turn left `--force-tag` with nothing to act on
    - `prepareWorkingCopy()` now promotes `tags/` to depth `immediates` on every run, existing checkouts included. Only the names are fetched; the contents — every release ever published — stay on the server

## [2.3.2] - 2026-08-23

### Added

- **The readme.txt gains the "Source code and build" section** required by the WordPress.org directory
    - It explains that the PHP is not compiled and that the complete Vue frontend source ships in the package, under `app/src`, with all the build configuration needed to reproduce `app/dist`
    - It lists the third-party libraries bundled into the build, each with its source code address and license
    - It points to the public development repository
- New `npm run lint:php`, which parses every source file with a real PHP 8.1 binary so nothing newer creeps back in unnoticed
- New `Transport::is_ready()` method, which reports whether the site already has a key to send with

### Changed

- **PHP 8.1 stays the minimum**, and the phone number library moves to `giggsey/libphonenumber-for-php-lite` 9.0.37
    - 8.13.55, the version shipped until now, is the last release of that library to support PHP 7.4 — it came out in February 2025 and receives no further updates. Staying on it would freeze the country numbering rules the plugin validates against
    - There is no alternative: every maintained phone library in the PHP ecosystem wraps this same package, because the value is in Google's metadata rather than the code
    - The "lite" build is the same library without the offline geocoding, carrier and timezone datasets, which the plugin never reads. It requires PHP 8, which the 8.1 floor now allows again, and brings `admin/vendor` down from 22 MB to 3 MB
    - With no datasets to drop, the build no longer strips anything from `admin/vendor`
- **The transport is no longer configurable** and `Transport` always resolves to the official API
    - It remains the single outbound point for messages, so swapping transports in the future is still a one-file change
    - The `whatsapp` channel identifier stays registered, pointing at the official API channel: messages already queued before the update still find a valid channel
- The build plugin that strips the emoji picker's CDN URL now targets the library constant, not the address
    - The address no longer appears in any plugin file, which was triggering a false positive in the directory review
    - It also stops depending on the host: if the library switches CDN in a future version, the URL is still removed from the package

### Removed

- **The legacy transport (Evolution / slots-manager) is gone for good**
    - **Heads-up:** sites still sending through that path stop sending until the Joinotify account is connected under Settings → General → WhatsApp Cloud API
    - The plugin shipped an embedded slots-manager API key, encrypted with the decryption key sitting on the next line. It was the same credential for every installation and anyone could extract it; it was removed along with the homegrown encryption that hid it
    - Also gone: the Proxy API and its routes, the "Message transport" selector, phone registration by QR Code/OTP, the connection state lookup and the group listing — features that only ever existed on the relay
    - The scheduled routine that checked phone connection every 6 hours was removed and is automatically unscheduled on update
    - Numbers are still connected in the Joinotify panel and imported with the "Sync numbers" button
    - `slots-manager.joinotify.com` was dropped from the external services list in readme.txt, since the plugin no longer contacts it
- The `Joinotify/Transport/Active` filter, along with the transport choice

## [2.3.1] - 2026-08-21

### Added

- **The builder warns when a step cannot start a conversation**
    - On the official API, text, media, interactive messages and AI-generated content only reach the contact within 24 hours of their last reply
    - A flow that starts the conversation — abandoned cart, "order shipped", post-sale — has to begin with an approved template; otherwise Meta rejects the send and the failure only showed up in the log
    - The warning appears while editing the step and changes tone once the flow already has a template step. It does not appear on the legacy transport, because that rule does not exist there
    - Nothing changed about sending: this is edit-time guidance
    - Extensions can include their own actions in the warning through the `Joinotify/Transport/Free_Form_Actions` filter

### Changed

- **WordPress 7.0 is now the minimum required version**
    - From that release on, WordPress ships the built-in AI Client, which is what the plugin's AI integration uses now
- **AI no longer stores its own keys and now uses the WordPress AI Client**
    - The provider and the key are configured once under Settings → Connectors, in WordPress itself, and apply to every plugin on the site
    - The "OpenAI" and "Anthropic" integrations were removed from Settings → Integrations, along with the key and model fields
    - The plugin no longer talks directly to api.openai.com or api.anthropic.com; WordPress makes the call, to whichever provider you chose
    - Global instructions and the default temperature stay in the plugin, now under Settings → General → Artificial Intelligence
    - The setup wizard no longer asks for a key: the AI step now reports whether the site already has a provider and links to the Connectors screen
    - **Heads-up:** anyone already using AI needs to configure the provider under Settings → Connectors. The old keys remain in the database but are no longer used
- **The OTP login template is now picked from a list instead of typed**
    - The list only shows approved authentication templates on your account, with a button to sync when one has just been approved
    - Templates are still created in the Joinotify panel; the plugin only reads and caches them
    - The language now comes from the selected template instead of a second hand-filled field — sending a language other than the approved one is rejected by Meta. The field still exists as a fallback
- **Compiled translation files no longer ship in the package**
    - WordPress.org generates and delivers each language through translate.wordpress.org, and the review team asks that the package not duplicate that channel
    - Until the strings are approved there, non-English installations display the text in English
    - Anyone installing outside the directory can build a package with the languages using `npm run build -- --ship-locales`
    - The ZIP dropped from 3.35 MB to 2.15 MB
- Adjustments requested in the WordPress.org review
    - The builder styles moved out of the HTML into an enqueued stylesheet (`assets/css/builder-boot.css`), and the loader's utility rules are now scoped to it instead of leaking into the rest of the screen
    - The setup wizard notice no longer uses inline JavaScript: "Don't remind me again" became a nonce-signed link handled on the server
    - The admin menu position no longer competes with the WordPress items; it now sits below them, adjustable through the `Joinotify/Admin/Menu_Position` filter
    - The plugin no longer deactivates the old "Joinotify OTP Login" on its own: it shows a notice linking to the Plugins screen and leaves the decision to you
    - Removed IP-based country detection, which queried ipinfo.io without being declared in the readme. The function had no callers and was broken (the URL never interpolated the address)
    - The emoji picker no longer loads images from an external CDN: the URL is stripped from the package at build time (the picker already used native emoji, so nothing changes on screen)
    - `giggsey/libphonenumber-for-php-lite` updated from 8.13.55 to 9.0.37

### Removed

- **The builder's "PHP Snippet" action**
    - The action executed arbitrary PHP code through `eval()`, which the WordPress.org directory does not allow
    - AI snippet generation, which existed only for that action, was removed as well
    - **Heads-up:** saved automations using that step still open, but the snippet step is no longer executed. Anyone depending on it should move the logic into their own plugin, hooked into the `Joinotify/...` hooks
- The button that installed the cart recovery add-on from an external address
    - The button no longer worked (there was no code left responding to the click) and the remote address went against the directory guidelines
    - In its place, the builder now simply states which plugin needs to be installed and activated

### Fixed

- Accounts with more than 100 message templates lost the remainder in the selectors
    - The listing did not request a limit and received only the API's first page, with no sign that there was more
- Compliance fixes reported by Plugin Check: prepared and documented database queries, output escaping on the integrations icon, `translators:` comments and numbered placeholders in every translatable string, `date()` replaced by `wp_date()`/`gmdate()`, `mt_rand()` by `wp_rand()`, `strip_tags()` by `wp_strip_all_tags()`, and the debug calls (`error_log`/`print_r`) routed to the plugin's own log
- The translation file (`.pot`) generator read the editor's history folder and brought back strings already removed from the code; it now also carries the `translators:` comments into the file, which used to be lost

## [2.3.0] - 2026-08-17

### Added

- **WhatsApp connection through the official Joinotify API**, replacing the Proxy API format over the Evolution API
    - "Connect to Joinotify" button in the settings: the account is authorized in the panel and the API key is delivered to the site without any copy and paste (pasting the key manually is still available)
    - Numbers connected in the panel are imported into the site, with verified name, phone number identifier, Meta-assigned quality and 24-hour business-initiated conversation limit
    - Support for multiple numbers and multiple business accounts (WABA) on the same account: each action picks which number the message goes out from
    - Anyone still on the old format keeps working normally and starts seeing a deprecation notice
- **Meta-approved message templates**
    - Your account's templates are listed inside the builder, with a content preview, category, language and approval status
    - New "WhatsApp: Template message" action, with one Joinotify variable per template variable
    - Button to sync the templates when one is created straight in Business Manager
    - Warning when the chosen template is paused, disabled or rejected by Meta
    - Templates are the only way to reach someone outside the 24-hour window, which is the situation for most automatic flows
- New message types allowed by the official API: reply buttons, option list, link button, location, contact card, sticker and reaction
- **Real delivery confirmation**
    - The site now receives the account's events and records when the message was delivered, read or rejected, instead of only "accepted by the API"
    - Delivery failures now appear in the debug log with the reason reported by Meta
    - Messages received from the contact open the 24-hour window, allowing free-form replies
- **Usage data reporting**, which had existed since 2.4.0 with no destination, now actually works
    - It stays **off by default**: nothing leaves the site before you accept it in the wizard
    - The site is identified by a random value generated right here — never derived from the address — plus the API key you already use to send messages
    - The identifier appears under Settings → About, so you can quote it in a support ticket; without it, support has no way to find your site
    - Events are accumulated and sent in batches by a scheduled task, at most every few hours, never during a page load
    - A repeated event on the same day counts once: a store with five thousand daily orders generates about ten events, not five thousand
    - Only what is in the catalog goes out, and every property has a closed type — there is no free-text field, so message content does not fit the format
    - Error codes are normalized before leaving; the detail stays in an identifier for the code point that produced the failure
    - Turning it off clears whatever was still queued and tells the server to stop counting this installation (the `Joinotify/Telemetry/Send_Opt_Out` filter suppresses that last notice)
    - The wizard now also shows examples of the events and how the site is identified
- **A 6-step setup wizard**, shown right after activation
    - Default country, pre-selected from the WooCommerce store address, the site language or the timezone
    - Connection to Joinotify: the API key is validated on the spot and the account's numbers are imported
    - AI provider (optional), with the provider's key
    - Plugin documentation, at https://docs.joinotify.com
    - Authorization for anonymous usage data reporting
    - At the end, create the first automation or go to the settings
    - Older installations that never went through the wizard also see it, once, when opening a Joinotify screen
- **Anonymous usage data reporting, off by default**
    - The wizard shows exactly what would be sent before you decide
    - It never includes the site address, e-mail, phone numbers, contacts, message content, flows or credentials
    - It can be turned off at any time under Settings → About
- **A settings modal on the WhatsApp card**, under Settings → Integrations
    - The "Configure" button opens the modal with the Joinotify API key, the message transport, the phone number ID and the business account ID
    - The "Connect" button validates the key against the API on the spot and imports the account's numbers, filling in the phone number ID and the business account ID automatically
    - If the key is rejected, the previous key is restored — the site is never left with a key that just failed
    - The "Disconnect" button erases the key without removing the numbers already imported
    - With a key saved, the field displays its public prefix (the rest is masked) plus a "Remove key" button, with confirmation
    - The key entered in the setup wizard appears already saved in the field, and saving the settings does not erase it
    - The API key is not sent to the browser on any screen: the settings, the automation builder and the save response receive only the public prefix and the fact that a key is stored
    - The WhatsApp API key, the Telegram token and the Resend key are now excluded from the settings export
- New hooks for extensions
    - `Joinotify/Settings/Saved`, with the values before and after each save
    - `Joinotify/Sender_Selected`, when the site gains a sender number (it carries only how it was chosen, never the number)
    - `Joinotify/Notification_Queue/Item_Retried`, when a message fails and goes back into the queue
    - `Joinotify/Debug_Log/Recorded`, fired on errors even with logging turned off

### Changed

- **The plugin is now 100% free and open source software**, under the GNU GPL v2 or later license
    - Every feature is unlocked: there is no paid version, trial period or license check anymore
    - The "License" screen and the licensing system were removed; the Joinotify API key is now the only credential
    - On update, the license key, the status and the licensing server data are automatically deleted from the database
- **Updates are now delivered by WordPress itself**
    - The plugin's own update checker and the "Automatic updates" and "Update notices" options were removed
    - Sites installed outside the WordPress.org directory need to reinstall the plugin from the directory to receive updates again
- The OTP login access code is now delivered through an authentication template on the official API, with configurable name and language
- When the API asks to wait (rate limit), the retry respects exactly the time reported
- The official API does not offer sending to groups; in this mode the group actions are unavailable and explain why
- The setup wizard is now displayed full screen, over the WordPress dashboard
    - The admin bar and menu are covered while the wizard is open, so the flow is not interrupted halfway
    - The page behind no longer scrolls along, and the loading skeleton already appears full screen, without the jump that happened while mounting
    - New close button in the top right corner, available in every step
- The workflow template library is now served by the Joinotify API, at the same address as the account (`api.joinotify.com`), instead of the dedicated address it used before
    - When a template is used, the download is counted in the library, which now feeds each template's install count
    - When a template is revised, the site notices through the signature published in the catalog and fetches the new version by itself, with no cache clearing
    - Failures loading the library now appear in the debug log with the reason reported by the API
- The Proxy API (deprecated) now ships turned off on new installations
- The builder no longer offers actions from disabled integrations
    - Telegram, Resend, WhatsApp and the WooCommerce discount coupon only appear in the action library when the corresponding integration is active under Settings → Integrations
    - Before, those actions showed up even when off and the automation delivered nothing when executed
    - Already saved automations keep opening and displaying those steps normally, even if the integration is disabled afterwards
- Every WhatsApp action in the builder now displays the WhatsApp logo
    - Approved template, AI message, reply buttons, option list, link button, location, contact, sticker and reaction were using generic icons
- Compliance with the WordPress.org plugin directory guidelines: readme.txt declaring every external service used, GPL license and the Vue frontend source code distributed with the package

### Deprecated

- **"Enable Proxy API"**, under Settings → General, is now marked as deprecated and will be removed in an upcoming version
    - The routes keep working for now, but sending is exclusively through the Joinotify API (official WhatsApp API)
    - The proxy settings now display a deprecation notice
    - The proxy route responses now report the deprecation in the "Deprecation" and "X-Joinotify-Deprecation" headers, and every call is recorded in the debug log to help identify integrations still on the old format

### Removed

- The extension installer that downloaded packages from an external address

### Fixed

- The plugin removed none of its own scheduled tasks on deactivation
    - A site that deactivated the plugin kept the schedules in the database until someone cleaned them by hand
    - Deactivation now clears all of them. Nothing else is erased — deactivating is not uninstalling
- **Monetary values arrived broken in the message**
    - `{{ wc_currency_symbol }}` sent WooCommerce's internal code instead of the symbol: the Brazilian real showed up as `&#82;&#36;` instead of `R$`
    - Totals were sent with the order's raw number (`68.70`), without the decimal separator of the store's language and different from what the builder preview showed
    - `{{ joinotify_coupon_discount_formatted }}` sent the entire price HTML, with the `<span>` tags visible in the message
    - `{{ fcrc_cart_total }}` (Flexify Checkout abandoned cart) sent the value with no formatting at all
    - **Heads-up:** `{{ wc_order_total }}`, `{{ wc_total_discount }}`, `{{ wc_total_tax }}` and `{{ wc_total_refunded }}` now already include the currency symbol (`R$ 68,70`), as the builder preview always showed. Flows writing `{{ wc_currency_symbol }}{{ wc_order_total }}` need to drop the `{{ wc_currency_symbol }}`, otherwise the symbol appears twice

## [2.2.0] - 2026-08-03

### Added

- **"Loop" action in the flow builder**, which walks through a collection and runs the body actions once per item, allowing several messages to be sent in sequence
    - Available collections: the order's digital files, the order's purchased items and a list from a variable (split by line or delimiter)
    - Delivers one file per message: for example, one media message for each downloadable file linked to the order
    - New "Loop item file" attachment source, which sends the current iteration's file without consuming the customer's download limit
    - Support for a wait time (delay) between iterations
- New loop variables, available inside the loop body: `{{ loop_value }}`, `{{ loop_index }}`, `{{ loop_number }}`, `{{ loop_count }}`, `{{ loop_file_name }}`, `{{ loop_download_url }}`, `{{ loop_product_name }}`, `{{ loop_item_name }}` and `{{ loop_item_quantity }}`
    - Loop variables are processed in any field they are inserted into (caption, media URL, attachment URL and recipient)

### Removed

- Notify when WhatsApp disconnects

### Fixed

- The default country code (dial code) was not applied when the number did not include the code, making the send fail; the configured fallback country is now used correctly

## [2.1.0] - 2026-07-27

### Added

- **WooCommerce digital product delivery straight from the flow builder**, sending files and PDFs by e-mail or WhatsApp
- **Attachments in the e-mail (Resend) and WhatsApp media actions**, with a reusable field that accepts media library files, links or the order's own digital files
    - Large e-mail attachments are automatically replaced by the download link, guaranteeing the message goes out
    - Multiple files can be sent in the WhatsApp media action
- New trigger: "Digital product access granted" (WooCommerce), recommended for delivery flows, since the download links already exist at that point
- New trigger: "Digital file downloaded" (WooCommerce), fired when the customer downloads a file, both with an optional per-product filter
- New WooCommerce variables for digital products: downloadable product list, file names and links, links without the name, expiration date, remaining downloads, link to the downloads area in the customer's account and download link for a specific product

### Changed

- Variables now respect the chosen trigger at send time as well, preventing context-less variables from being filled in improperly
- Groundwork for the new license server, with automatic migration and no user intervention
    - The plugin switches to the new server as soon as the current one stops responding, keeping the same license key
    - Already activated sites are migrated in the background, without freezing the dashboard
    - No license is deactivated when the server is down or when there is a registration mismatch; in those cases the plugin keeps working and reports it on the license screen
    - Plugin updates are delivered by the new server after the migration

### Fixed

- Argument order in the WooCommerce partial refund trigger, which made the order variable receive the refund identifier
- An expired or server-rejected license kept the premium features unlocked for up to 24 hours
- Opening the license screen could deactivate the site's license when the expiration date had already passed
- Lifetime licenses marked as "Unlimited" or "Lifetime" were treated as expired
- The license activation response could be reused on deactivation, since both shared the same cache

## [2.0.0] - 2026-07-02

### Added

- **Artificial Intelligence in the flow builder**
    - Automatic creation of complete flows from a text description
    - Generation of dynamic WhatsApp messages with AI
    - Generation of smart variables with AI
    - Creation of PHP snippets with AI assistance
- **Custom text variables**, created by the user from the site's content types and fields (includes support for WooCommerce orders)
- Sent message history, with filters and a month-and-year date picker
- Passwordless login by code (OTP) sent via WhatsApp, with support for new channels in the future
- Export and import all plugin settings in a JSON file
- "Password reset request" trigger, with a reset link variable
- New languages added: French, Italian, German and European Portuguese

### Changed

- **New flow builder**: fully redesigned interface as a visual canvas, with drag and drop, step connections, zoom, fit to screen and undo/redo buttons
- **New message editor** with visual formatting (bold, italic, emoji) automatically converted to the WhatsApp standard
- Media preview right in the WhatsApp media message step
- Text variables highlighted and clickable inside the fields, with a warning when unavailable in the chosen trigger
- Actions flag when there are required settings pending
- A more complete condition catalog, with list-based selection and a product picker in the values
- Message scheduling with a specific date and time (wait time), with a queue and reprocessing of failed notifications
- "Integrations" tab renamed to "Apps"
- Manual update check in the "About" tab
- Automatic migration of flows from previous versions when updating the plugin
- Performance optimizations in plugin loading and in the admin screens
- Improved translation support (Portuguese, English and Spanish)

## 1.4.7 - 2026-04-12

### Added

- Queue for message processing

### Changed

- Frontend technology switched to Vue.js, Vite and Tailwind CSS

## [1.4.6] - 2026-02-10

### Changed

- Optimizations

### Fixed

- Fatal error due to the missing `ElementorPro\Modules\Forms\Classes\Action_Base` class

## [1.4.5] - 2026-01-24

### Added

- WooCommerce -> full address format (billing and shipping)

### Changed

- Optimizations

### Fixed

- Fatal error loading the "All flows" page: Call to undefined function `convert_to_screen()`

## [1.4.4] - 2025-12-12

### Security

- Security improvements in class instantiation

## [1.4.3] - 2025-12-08

### Fixed

- Check whether the order was paid
- Emoji encoding in messages
- Post count in the All flows table

## [1.4.2] - 2025-11-27

### Changed

- Optimizations

### Fixed

- String validation error with the Proxy API

## [1.4.1] - 2025-10-28

### Changed

- Change to the update check API

## [1.4.0] - 2025-08-29

### Added

- Caption for WhatsApp media messages
- Text variables `{{ post_title }}`, `{{ post_date }}`, `{{ post_content }}`, `{{ post_link }}`, `{{ post_tags }}`, `{{ post_categories }}` and `{{ post_featured_image }}`

### Changed

- Optimizations

## [1.3.7] - 2025-08-13

### Fixed

- Unable to edit flows with the Academy LMS plugin and similar

## [1.3.6] - 2025-07-11

### Fixed

- Priority and arguments of `add_action()` in the `Woo_Subscriptions` class passed outside the callback array
- Failure checking order payment status

## [1.3.5] - 2025-07-09

### Added

- Post status validation in the "Post status changed" trigger

### Fixed

- Fatal error when changing order status: Uncaught Error: Class name must be a valid object or a string in /woocommerce/src/Internal/DataStores/Orders/OrdersTableDataStore.php:1524

## [1.3.4] - 2025-06-16

### Added

- Show version update notices

### Changed

- Responsiveness improvements on desktop

### Fixed

- Cart recovery link is empty (Flexify Checkout - Abandoned cart recovery)

## [1.3.3] - 2025-06-10

### Added

- Receive notices when WhatsApp is disconnected
- Text variables `{{ fcrc_first_name }}`, `{{ fcrc_last_name }}`, `{{ fcrc_phone }}`, `{{ fcrc_email }}`, `{{ fcrc_cart_total }}` (Flexify Checkout - Abandoned cart recovery)
- Trigger: "Lead captured via modal" (Flexify Checkout - Abandoned cart recovery)
- Trigger: "Lead captured via checkout" (Flexify Checkout - Abandoned cart recovery)

### Removed

- On entering step 1 of the Flexify Checkout integration
- On entering step 2 of the Flexify Checkout integration
- On entering step 3 of the Flexify Checkout integration

## [1.3.2] - 2025-05-29

### Changed

- Fill in the sender when importing a flow

### Fixed

- Undefined `set_default_options()` method in the `Helpers` class at line 171

## [1.3.1] - 2025-05-26

### Fixed

- Wait time action

## [1.3.0] - 2025-05-08

### Changed

- Change to the WhatsApp message sending API
- Optimizations

### Fixed

- Bug fixes

### Security

- Security fix for whether the sender is registered on the site

## [1.2.5] - 2025-03-24

### Changed

- Optimizations

### Fixed

- Fix to the hook calls of the Woo Subscriptions integration

## [1.2.2] - 2025-03-17

### Added

- Adding actions between existing actions in the flow
- Text formatting with WhatsApp variables
- English translation (en-US)
- Spanish translation (es-ES)

### Changed

- Text variables `{{ wc_order_total }}`, `{{ wc_total_discount }}`, `{{ wc_total_tax }}`, `{{ wc_total_refunded }}` now return formatted values with the currency symbol
- Optimizations

### Removed

- "Order status" condition in the "New order" trigger

### Fixed

- Text variables in WooCommerce triggers in test mode were not being replaced correctly

## [1.2.0] - 2025-03-12

### Added

- "giggsey/libphonenumber-for-php" library for formatting and validating phone numbers in international format
- "Selectize" library for multi-selecting elements
- "Payment method", "Shipping method" and "Order paid" conditions
- Trigger: "Payment processed by PayPal"
- "Routines" class for running routines; and added a routine that checks phone connection and updates
- Text variables `{{ fc_inter_pix_copia_cola }}`, `{{ fc_inter_pix_expiration_time }}`, `{{ fc_inter_bank_slip_url }}` and `{{ fcrc_recovery_link }}`

### Changed

- Optimizations

### Removed

- Text variable `{{ post_id }}`

### Fixed

- Bug fixes

## [1.1.2] - 2025-02-24

### Fixed

- Bug fixes

## [1.1.1] - 2025-02-24

### Fixed

- Bug fixes

## [1.1.0] - 2025-02-24

### Added

- Enable debug mode
- Integration with Elementor forms
- Text variables `{{ wc_billing_first_name }}`, `{{ wc_billing_last_name }}`, `{{ wc_billing_email }}`, `{{ wc_billing_phone }}`, `{{ wc_shipping_phone }}`, `{{ wc_order_status }}`, `{{ wc_billing_full_address }}`, `{{ wc_shipping_full_address }}`, `{{ wc_order_total }}`, `{{ wc_total_discount }}`, `{{ wc_total_tax }}`, `{{ wc_total_refunded }}`, `{{ wc_coupon_codes }}`, `{{ wc_payment_method_title }}`, `{{ wc_shipping_address }}`, `{{ wc_checkout_field=[FIELD_ID] }}`
- Enable automatic updates
- "PHP Snippet" action in the flow builder
- "Discount coupon" action in the flow builder for the WooCommerce integration
- Get WhatsApp group information

### Changed

- Emoji library swapped (Picmo -> EmojioneArea)
- Optimizations

### Removed

- Text variables `{{ br }}` and `{{ phone }}`
- Automatic settings update

### Fixed

- Bug fixes

## [1.0.5] - 2024-12-05

### Fixed

- PHP 7.4 compatibility fix

## [1.0.4] - 2024-11-22

### Fixed

- Bug fixes

## [1.0.3] - 2024-11-22

### Fixed

- Bug fixes

## [1.0.2] - 2024-11-22

### Fixed

- Bug fixes

## 1.0.1 - 2024-11-21

### Fixed

- Bug fixes

## [1.0.0] - 2024-11-20

### Added

- Initial release

[Unreleased]: https://github.com/meumouse/joinotify/compare/v2.3.4...HEAD
[2.3.4]: https://github.com/meumouse/joinotify/compare/v2.3.3...v2.3.4
[2.3.3]: https://github.com/meumouse/joinotify/compare/v2.3.2...v2.3.3
[2.3.2]: https://github.com/meumouse/joinotify/compare/v2.3.1...v2.3.2
[2.3.1]: https://github.com/meumouse/joinotify/compare/v2.3.0...v2.3.1
[2.3.0]: https://github.com/meumouse/joinotify/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/meumouse/joinotify/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/meumouse/joinotify/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/meumouse/joinotify/compare/v1.4.6...v2.0.0
[1.4.6]: https://github.com/meumouse/joinotify/compare/v1.4.5...v1.4.6
[1.4.5]: https://github.com/meumouse/joinotify/compare/v1.4.4...v1.4.5
[1.4.4]: https://github.com/meumouse/joinotify/compare/v1.4.3...v1.4.4
[1.4.3]: https://github.com/meumouse/joinotify/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/meumouse/joinotify/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/meumouse/joinotify/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/meumouse/joinotify/compare/v1.3.7...v1.4.0
[1.3.7]: https://github.com/meumouse/joinotify/compare/v1.3.6...v1.3.7
[1.3.6]: https://github.com/meumouse/joinotify/compare/v1.3.5...v1.3.6
[1.3.5]: https://github.com/meumouse/joinotify/compare/v1.3.4...v1.3.5
[1.3.4]: https://github.com/meumouse/joinotify/compare/v1.3.3...v1.3.4
[1.3.3]: https://github.com/meumouse/joinotify/compare/v1.3.2...v1.3.3
[1.3.2]: https://github.com/meumouse/joinotify/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/meumouse/joinotify/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/meumouse/joinotify/compare/v1.2.5...v1.3.0
[1.2.5]: https://github.com/meumouse/joinotify/compare/v1.2.2...v1.2.5
[1.2.2]: https://github.com/meumouse/joinotify/compare/v1.2.0...v1.2.2
[1.2.0]: https://github.com/meumouse/joinotify/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/meumouse/joinotify/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/meumouse/joinotify/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/meumouse/joinotify/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/meumouse/joinotify/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/meumouse/joinotify/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/meumouse/joinotify/compare/1.0.2...v1.0.3
[1.0.2]: https://github.com/meumouse/joinotify/compare/1.0.0...1.0.2
[1.0.0]: https://github.com/meumouse/joinotify/releases/tag/1.0.0

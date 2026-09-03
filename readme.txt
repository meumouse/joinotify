=== Joinotify ===
Contributors: meumouse
Tags: whatsapp, automation, woocommerce, notifications, workflow
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1.0
Stable tag: 2.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build WhatsApp message automations with a visual drag-and-drop workflow builder, connected to WooCommerce, WPForms, Elementor and more.

== Description ==

Joinotify turns things that happen on your site into WhatsApp messages, without writing code. You draw the automation on a canvas: pick a trigger (an order is paid, a form is submitted, a user registers), add conditions, delays and loops, and attach the messages you want sent.

The plugin is free and has no locked features. Building, saving, testing and exporting workflows all work out of the box. Sending messages requires a Joinotify account, because messages are delivered through the official WhatsApp Cloud API — see **External services** below.

= Visual workflow builder =

* Drag-and-drop canvas with triggers, actions, conditions, delays and loops
* Branching logic with true/false paths
* Placeholders and custom variables to personalise every message
* Import and export workflows as JSON
* Ready-made workflow templates

= WhatsApp messaging =

* Text, media, audio, documents and stickers
* Interactive messages: reply buttons, option lists and link buttons
* Location messages, contact cards and reactions
* Meta-approved message templates, listed inside the builder with preview, language and approval status
* Real delivery confirmation: sent, delivered, read and failed, with the reason reported by Meta
* Multiple numbers and multiple WhatsApp Business Accounts on the same account

= Integrations =

* WooCommerce — order status, refunds, subscriptions, digital product delivery, abandoned carts
* WPForms, Elementor Forms and Flexify Checkout
* WordPress user events
* Telegram and Resend (e-mail) as additional delivery channels
* AI-generated message content through the WordPress AI Client, using the provider set up in Settings → Connectors (optional)

= Operations =

* Message history with per-message status
* Processing queue for scheduled and retried messages
* Debug log with configurable retention
* Passwordless login (OTP) over WhatsApp
* A PHP extension API: register your own actions, triggers, integrations, conditions, placeholders, REST routes and channels with filters only

== External services ==

This plugin connects to external services. Nothing is contacted until you supply the corresponding credential in the setup wizard or in the settings screen.

The AI actions are the exception: they do not call any provider directly. They go through the WordPress AI Client, so the request is made by WordPress to whichever provider you configured in Settings → Connectors, under that provider's own terms.

**Joinotify API — https://api.joinotify.com**
Delivers your WhatsApp messages through the official WhatsApp Cloud API, lists the numbers on your account, syncs Meta-approved message templates, serves the catalogue of ready-made workflow templates shown in the template picker, and receives delivery events. Used only once you paste the API key issued for your site. Each request sends your API key, the recipient phone number and the message content you configured. When you connect the site from the Joinotify panel, that one request also sends your site address, so the key can be named and revoked from there.
Terms: https://joinotify.com/terms-and-conditions — Privacy: https://joinotify.com/policy-privacy

**Joinotify telemetry — https://api.joinotify.com/telemetry**
Optional and **off by default**. Contacted only after you switch on "Share anonymous usage data", either in the setup wizard or in Settings → About. It receives a random identifier generated on your site, your Joinotify API key (the same one used to send messages), the plugin, WordPress and PHP versions, your locale and time zone, whether the site is multisite and whether WooCommerce, Elementor and HTTPS are in play, how many workflows exist, which integrations are switched on, and a list of named events: which feature ran, whether a message succeeded or failed, and normalized error codes. It never receives your site address, admin e-mail, phone numbers, contacts, message content or workflow content. Sent at most once every few hours, and once more when you switch the option off — that last request is what tells the service to stop counting this installation. To have the data already collected for your installation erased, use the removal request page below.
Terms: https://joinotify.com/terms-and-conditions — Privacy: https://joinotify.com/policy-privacy — Data removal: https://joinotify.com/remove-data

**Joinotify panel — https://app.joinotify.com**
Opened in your browser when you click to create or manage an API key. No data is sent from your site by the plugin itself.
Terms: https://joinotify.com/terms-and-conditions — Privacy: https://joinotify.com/policy-privacy

**Telegram Bot API — https://api.telegram.org**
Optional. Contacted only if you enable the Telegram integration and supply a bot token. It receives the chat id and message content of Telegram actions.
Terms: https://telegram.org/tos — Privacy: https://telegram.org/privacy

**Resend — https://api.resend.com**
Optional. Contacted only if you enable the Resend integration and supply an API key. It receives the recipient address, subject, body and attachments of e-mail actions.
Terms: https://resend.com/legal/terms-of-service — Privacy: https://resend.com/legal/privacy-policy

= Usage data =

Sharing anonymous usage data is **off by default** and entirely optional. If you turn it on, the setup wizard shows you the exact payload and sample events beforehand. It never includes your site address, admin e-mail, phone numbers, contacts, message content, workflow content or any credential.

Your site is identified by a random value generated on the site itself — never derived from your address — together with the API key you already use to send messages. Reports are batched and sent at most once every few hours, from a scheduled task, never during a page load. The identifier is shown in Settings → About so you can quote it in a support ticket, since the service has no way to look your site up by domain.

Switching the option off stops collection, deletes anything still waiting to be sent, and sends one last request asking the service to stop counting this installation. If you would rather it stayed completely silent, the `Joinotify/Telemetry/Send_Opt_Out` filter suppresses that final request.

Switching it off stops future reports but does not erase what was already received. To request the erasure of the data tied to your installation, use https://joinotify.com/remove-data and quote the identifier shown in Settings → About.

== Source code and build ==

Nothing here is obfuscated, and no compiled file ships without the source that produced it.

= The plugin's own code =

The PHP under `admin/src` and `templates/` is not compiled: it is the code that runs.

The admin screens are Vue 3 applications bundled with Vite. Their complete, unminified source ships inside the package under `app/src` (242 files, of which 167 are Vue components), together with `app/package.json`, `app/package-lock.json`, `app/vite.config.js`, `app/tailwind.config.js`, `app/postcss.config.js` and `app/tsconfig.json`. To reproduce `app/dist` from it:

`cd app && npm install && npm run build`

PHP dependencies are declared in `admin/composer.json` and installed with `cd admin && composer install --no-dev`.

The development repository, including the script that assembles the release package, is public at https://github.com/meumouse/joinotify

= Bundled third-party libraries =

`app/dist` also carries the libraries below. Each is installed from npm and used unmodified; the version in this release is pinned in `app/package.json` and `app/package-lock.json`, both shipped. Their source is at:

* Vue — https://github.com/vuejs/core (MIT)
* Pinia — https://github.com/vuejs/pinia (MIT)
* Vue Flow, with the background, controls and minimap plugins — https://github.com/bcakmakoglu/vue-flow (MIT)
* Headless UI — https://github.com/tailwindlabs/headlessui (MIT)
* VueUse — https://github.com/vueuse/vueuse (MIT)
* Boxicons for Vue — https://github.com/box-icons/boxicons-vue (MIT)
* intl-tel-input — https://github.com/jackocnr/intl-tel-input (MIT)
* vue3-emoji-picker — https://github.com/delowardev/vue3-emoji-picker (MIT)
* @wordpress/i18n — https://github.com/WordPress/gutenberg (GPL-2.0-or-later)

The country-name chunks under `app/dist/chunks/index-*.js` are intl-tel-input's translated country lists, split out by Vite so a screen only downloads the locale it needs.

`admin/vendor` holds the Composer dependencies, installed with `composer install --no-dev` from `admin/composer.json`:

* giggsey/libphonenumber-for-php-lite — https://github.com/giggsey/libphonenumber-for-php (Apache-2.0) — used to validate and format phone numbers. The "lite" build of the same library, without the offline geocoding, carrier and timezone datasets the plugin never reads
* symfony/polyfill-mbstring — https://github.com/symfony/polyfill-mbstring (MIT) — a dependency of the above

== Frequently Asked Questions ==

= Do I need a paid account to use the plugin? =

The plugin itself is free and every feature is unlocked. Building, testing and exporting workflows works with no account at all. Delivering messages requires a Joinotify account, because WhatsApp messages go through Meta's official Cloud API and that access is provisioned per account.

= Where do I get the API key? =

Create it for your site on the Joinotify panel at https://app.joinotify.com, then paste it in the setup wizard (step 2) or in Settings → General → WhatsApp Cloud API.

= Can I send a message to anyone at any time? =

No — that is a WhatsApp rule, not a plugin limitation. Outside a 24-hour window opened by the contact writing to you first, only Meta-approved message templates can be delivered. The builder lists your approved templates so you can pick one.

= Does it work without WooCommerce? =

Yes. WooCommerce is one integration among several; WPForms, Elementor, Flexify Checkout and core WordPress user events all work on their own.

= Where is the uncompiled JavaScript? =

It ships with the plugin, under `app/src`, together with the build configuration needed to reproduce `app/dist`. See **Source code and build** above, which also lists the source of every third-party library bundled into it.

= Is this plugin affiliated with WhatsApp or Meta? =

No. Joinotify is not affiliated with, endorsed by or sponsored by WhatsApp LLC or Meta Platforms, Inc. WhatsApp is a trademark of WhatsApp LLC.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/joinotify`, or install it from the Plugins screen.
2. Activate it. The setup wizard opens automatically.
3. Work through the wizard: default country, Joinotify API key, optional AI provider, documentation, usage-data choice.
4. Build your first automation from Joinotify → Add new workflow.

You can reopen the wizard at any time from `wp-admin/admin.php?page=joinotify-onboarding`.

== Screenshots ==

1. The visual workflow builder.
2. The workflow actions library.
3. Message history with delivery status.
4. Settings, with integrations and senders.

== Changelog ==

= 2.3.4 =
* Fixed: a fatal error could take the whole site down, login page included, whenever the plugin wrote a log entry early in the request. Sites on PHP 8 with debugging enabled were the ones affected.
* Fixed: the workflow post type was never actually registered with WordPress. Workflows themselves kept working, but the permissions declared for them did not apply, so an Editor could open and delete workflows that were meant to be restricted to administrators.
* Fixed: the plugin was reported as incompatible with WooCommerce's High-Performance Order Storage, because the compatibility declaration was sent after WooCommerce had stopped listening. HPOS can now be enabled with Joinotify active.

= 2.3.3 =
* No change to the plugin itself: the code, the interface and the translations are the same as 2.3.2. Only the release pipeline that publishes the package to WordPress.org was fixed.

= 2.3.2 =
* Changed: the phone number library moved to giggsey/libphonenumber-for-php-lite 9.0.37. The version shipped until now was the last one supporting PHP 7.4, released in February 2025 and no longer updated, which would have frozen the country numbering rules the plugin validates against. PHP 8.1 remains the minimum.
* Removed: the legacy Evolution relay, in full. Messages now go through the official WhatsApp Cloud API only.
* Security: the plugin shipped an embedded slots-manager API key, encrypted with its decryption key on the next line. It was the same credential in every installation and anyone could extract it. Both it and the hand-rolled encryption around it are gone.
* Removed with the relay: the Proxy API and its endpoints, the "Message transport" selector, phone registration by QR code and OTP, the per-number connection check and the WhatsApp group listing. None of them exist on the official API.
* Removed: the scheduled task that polled phone connections every six hours. Upgrading unschedules it.
* Changed: `Transport` always resolves to the official API and gained `Transport::is_ready()`, which reports whether the site has a key to send with. The `Joinotify/Transport/Active` filter went away with the transport choice.
* Changed: `slots-manager.joinotify.com` was dropped from the external services list, since the plugin no longer contacts it.
* Added: a "Source code and build" section in this readme, documenting where the uncompiled frontend source lives, how to rebuild it, and the source of every third-party library bundled into the package.
* Changed: the build step that strips the emoji picker's CDN URL now targets the library constant rather than the address, so no remote asset URL appears anywhere in the plugin.

= 2.3.1 =
* Changed: WordPress 7.0 is now required. The AI features run on the AI Client that ships with it.
* Changed: AI content is generated through the WordPress AI Client. Choose a provider and store its key once in Settings → Connectors and every AI action uses it — Joinotify no longer keeps a key of its own, nor calls a provider directly. The OpenAI and Anthropic integrations were removed, so a site using AI has to set the provider up again in Connectors.
* Removed: the "PHP Snippet" action, which executed arbitrary PHP. Saved workflows still open, but that step no longer runs.
* New: the builder warns when a step cannot open a conversation on the official API. Text, media, interactive messages and AI copy only reach a contact within 24 hours of their last reply, so a workflow that starts the conversation has to lead with an approved template.
* Improved: the OTP login template is now picked from the templates approved on your account instead of typed by name, and its language comes from the template itself.
* Fixed: an account with more than 100 message templates lost the rest from the template pickers.
* Changed: translations are delivered by translate.wordpress.org instead of being bundled with the plugin.
* Changed: the Joinotify menu moved below the WordPress items instead of sitting among them.
* Changed: the standalone "Joinotify OTP Login" plugin is no longer deactivated on its own. You are told it duplicates the built-in login and decide what to do.
* Removed: country detection by IP address, which contacted a third-party service that was not declared and was never actually used.
* Removed: the button offering to install the cart recovery add-on from an external download. It had done nothing since 2.0.0.
* Improved: the phone number library was updated, and the builder styles and the setup notice now go through the standard WordPress asset queue.

= 2.3.0 =
* New: anonymous usage reporting now actually delivers. Still off by default — nothing leaves the site until you agree in the setup wizard.
* New: the site is identified by a random value generated locally, never derived from your address. It is shown in Settings → About so you can quote it in a support ticket.
* New: reports are batched and sent by a scheduled task, never during a page load. Repeated events collapse to one per day.
* New: the consent screen also shows sample events and explains how the site is identified.
* New: switching usage reporting off deletes anything still queued and asks the service to stop counting the installation.
* New: actions for extensions — `Joinotify/Settings/Saved` (with the settings before and after each save), `Joinotify/Sender_Selected`, `Joinotify/Notification_Queue/Item_Retried` and `Joinotify/Debug_Log/Recorded`.
* New: six-step setup wizard shown after activation, covering the default country, the Joinotify API key, an optional AI provider, the documentation, the usage-data choice and the first automation.
* New: anonymous usage reporting, off by default, with the exact payload shown before you agree.
* New: WhatsApp connection through the official Joinotify API, replacing the Proxy API over Evolution.
* New: Meta-approved message templates listed inside the builder, with a dedicated template action.
* New: interactive messages — reply buttons, option lists, link buttons, location, contact cards, stickers and reactions.
* New: real delivery confirmation through account events (delivered, read, failed).
* The plugin is now free software under the GPLv2 and every feature is unlocked — the licensing system and its activation screen were removed.
* Changed: updates are now delivered by WordPress.org; the plugin no longer contacts an update server of its own.
* Changed: the Proxy API is off by default on new installs. It remains deprecated and will be removed.
* Removed: the extension installer that downloaded plugin packages from an external URL.
* Improved: OTP login codes are delivered through an authentication template on the official API.
* Improved: the workflow template library is now served by the Joinotify API at the same address as the account, counts an install when a template is used, and picks up revised templates on its own.
* Fixed: deactivating the plugin left all of its scheduled tasks behind. They are now cleared. Nothing else is deleted — deactivating is not uninstalling.
* Deprecated: the Proxy API setting under Settings → General.

= 2.2.0 =
* New: "Loop" action that walks a collection and runs its body once per item.
* New: loop variables available inside the loop body.
* Removed: WhatsApp disconnection notice.
* Fixed: the default country code was not applied to numbers typed without one.

= 2.1.0 =
* New: WooCommerce digital product delivery straight from the workflow builder.
* New: attachments on e-mail (Resend) and WhatsApp media actions.

== Upgrade Notice ==

= 2.3.4 =
Recommended for every installation. Fixes a fatal error that could make the site — and wp-login.php — unreachable, restores the permission rules on workflows, and makes the plugin compatible with WooCommerce High-Performance Order Storage again.

= 2.3.3 =
Same plugin as 2.3.2. Coming from 2.2.x: the legacy Evolution relay was removed, so a site still sending through it stops delivering until you connect your Joinotify account in Settings → General → WhatsApp Cloud API. It also removes an API key that shipped embedded in the plugin.

= 2.3.2 =
The legacy Evolution relay was removed. A site still sending through it stops delivering until you connect your Joinotify account in Settings → General → WhatsApp Cloud API. This release also removes an API key that shipped embedded in the plugin, so updating is recommended for every installation.

= 2.3.1 =
Requires WordPress 7.0. AI content now runs on the WordPress AI Client: set your provider up again in Settings → Connectors, because the OpenAI and Anthropic keys stored by the plugin are no longer used. The "PHP Snippet" action was removed — workflows that use it still open, but that step no longer runs.

= 2.3.0 =
Joinotify is now free and GPL-licensed, with no locked features. Updates move to WordPress.org, so the plugin's own update server is gone — install this version from WordPress.org to keep receiving updates. A setup wizard runs once to confirm your configuration.

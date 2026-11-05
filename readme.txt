=== Joinotify – Notifications & Marketing Automation ===
Contributors: meumouse
Tags: automation, whatsapp, marketing, notifications, notify
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1.0
Stable tag: 2.7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate WhatsApp notifications with a visual drag-and-drop workflow builder, connected to WooCommerce, WPForms, Elementor and more.

== Description ==

Joinotify — from *join* and *notify* — turns things that happen on your site into WhatsApp messages, without writing code. You draw the automation on a canvas: pick a trigger (an order is paid, a form is submitted, a user registers), add conditions, delays and loops, and attach the messages you want sent.

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

* WooCommerce — order status, confirmed payments, refunds, subscriptions, digital product delivery, abandoned carts, new customer accounts, low stock and back in stock alerts
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

**Joinotify Cloud sync — https://api.joinotify.com/site-events, /contacts/sync, /sites/me and /sites/me/erasures**
Optional and **off by default**. Contacted only after you switch on "Joinotify Cloud sync" in Settings → General or on the Sources tab of Joinotify → Audiences & Contacts; the sync's settings window lists what is sent before you turn it on. It receives the name, e-mail and phone number of your customers and of users who register; for each order, its number, status, totals, payment and shipping method and the customer's order count, total spent and first and last purchase dates; the order's items and full addresses only if you allow them in the same window (city and state are always sent); the form submissions and abandoned carts you choose to sync; the user or order meta you map to custom fields on the Sources tab of Audiences & Contacts, the tags you set there, and, for a form whose rule names a consent field, that the person ticked it, with the text you set, the form and when; the phone, e-mail, name, tags, custom field values and consent that the "Save contact" and "Tag contact" actions of your workflows send; and, for the site itself, its address, name, the Joinotify version and which WooCommerce, form and checkout plugins are active, with a made-up sample of each event it sends. If you also ask customers for marketing consent, it receives whether they agreed, the checkbox text and when, and an opt-out made in My account; the site's webhook endpoint is then also told when a customer opts in or out on Joinotify. When you run WordPress's "Erase Personal Data" tool for a customer, the site asks Joinotify to erase the contacts it sent for that person, identified by their user id or a hash of their e-mail. Events are queued on your site and sent in batches from a scheduled task, never during a page load. Switching it off stops it at once; data already received is kept in your Joinotify account, where you can erase contacts.
Terms: https://joinotify.com/terms-and-conditions — Privacy: https://joinotify.com/policy-privacy

**Joinotify contact base — https://api.joinotify.com/contacts, /audiences and /suppressions**
Contacted only when an administrator opens Joinotify → Audiences & Contacts on a site already connected to Joinotify. That screen lists and edits the contacts, custom fields, tags, audiences and suppression list of your Joinotify account, so each request sends your API key and what the administrator types or picks there: a search term or filter, the name, phone number, e-mail, custom field values, tags and consent evidence of a contact they add or edit, a phone number they add to the suppression list, and, when they import a CSV file, the columns they mapped of each row (the file is read in the browser and never stored on the site). Nothing is sent from the front end of the site. Opening the screen also asks Joinotify what the site's key may do, which sends only the key.
Terms: https://joinotify.com/terms-and-conditions — Privacy: https://joinotify.com/policy-privacy

**Webhook and API URLs you set in a workflow**
Optional. Contacted only when a workflow you built has a "Send webhook" or "HTTP request" action and reaches it, at the URL you typed in that action. It receives either the data of the trigger (the order with its customer history and links, the user, the cart or the form entry), the JSON, form fields or text you wrote in the action, or nothing (a GET request), with the query parameters, headers and credential you added. The "HTTP request" action keeps the answer for the next actions of that workflow run. Local and private network addresses are refused. The terms and privacy policy are those of the service you point it to.

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

= 2.7.2 =
* Fixed: WooCommerce workflows run outside the storefront, such as an order status change in the admin, no longer end in a fatal error.

= 2.7.1 =
* New: the JSON body of the "HTTP request" action can be built from a table of fields, each with its own type.
* New: a typed JSON body is edited in a code editor with syntax checking, a "Format" button and variable suggestions.
* Fixed: workflow titles no longer show HTML entities such as `&#8211;` in the admin pages.

= 2.7.0 =
* New: "HTTP request" workflow action, which calls any public API and keeps the response for the next actions.
* New: failed messages can be sent again from the message history, one at a time or up to 50 at once.
* New: "Customer account created", "Product low in stock", "Product out of stock", "Product back in stock" and "Back in stock notice sent" WooCommerce triggers.
* New: customer and product variables for the new triggers, such as `{{ wc_customer_first_name }}` and `{{ wc_product_name }}`.
* New: developers can pass data between actions with `writes_payload` on `joinotify_register_action()`.
* Changed: "New order (Processing)" is now "Payment confirmed", which runs once per order when the payment is confirmed.
* Changed: the `Checkout_Order_Processed` WooCommerce filter is replaced by `Joinotify/Process_Workflows/Woocommerce/Payment_Complete`.
* Changed: WooCommerce order variables are no longer offered on the account and stock triggers.
* Changed: the message history is always on, and its switch is gone from Settings → About.
* Fixed: saving a workflow no longer drops the backslashes typed in it.
* Fixed: local attachments are no longer recorded in the history with a broken media URL.

= 2.6.2 =
* Changed: the plugin is renamed "Joinotify – Notifications & Marketing Automation", following the WordPress.org trademark guidelines.

= 2.6.1 =
* New: variables take modifiers after a pipe, such as `remove`, `replace` and `remove_home_url`.
* New: `{{ wc_first_product_review_path }}` returns the review path of the first purchased product.

= 2.6.0 =
* New: the WhatsApp template action supports media and location headers, copy-code buttons, limited-time offers and carousels.
* New: "WhatsApp 24-hour window" condition to branch a workflow on whether the window is open.
* New: the Joinotify API key can be defined in `wp-config.php` with `JOINOTIFY_CLOUD_API_TOKEN`.
* New: developer helpers to send, list and render WhatsApp templates and read a contact's 24-hour window.
* Changed: the checkout block's consent checkbox is ticked for customers who already agreed to marketing messages.
* Fixed: the checkout block's marketing consent checkbox is offered to every customer of a headless store.
* Security: the Joinotify API key is stored encrypted in the database.

= 2.5.1 =
* Fixed: the plugin ships its bundled translations again.
* Fixed: counts such as "%d contacts" use the plural form in every translated language.
* Fixed: the labels of the workflow post type are translated.

= 2.5.0 =
* New: Joinotify → Audiences & Contacts screen to manage the contacts, fields, tags, audiences and suppression list of the Joinotify account.
* New: Joinotify Cloud sync, off by default, sends the site's customers and events to the Joinotify account.
* New: the sync delivers in background batches, retries failures and pauses on a revoked key or a copied site.
* New: "Send existing customers" sends the store's current customers to Joinotify once.
* New: optional marketing consent checkbox in the checkouts, sign-up forms and My account.
* New: per-source tags, meta-to-field mapping and per-form contact rules for WPForms and Elementor.
* New: "Save contact" and "Tag contact" workflow actions.
* New: "Send webhook" workflow action, which posts the trigger's data or a custom JSON to any public URL.
* New: `joinotify_track()` sends custom events to Joinotify flows.
* New: WordPress's personal data export and erase tools cover the sync.
* New: a review request asks administrators to rate Joinotify after ten minutes of use.
* New: settings declared with the `placeholder` component get the variable picker.
* Changed: admin and OTP login icons come from Boxicons.
* Changed: focused fields show a solid outline instead of a glow.
* Fixed: strings from the Vue admin screens reach translate.wordpress.org.
* Fixed: the "New order" trigger runs for orders placed with the checkout block.
* Fixed: the WPForms PayPal Standard trigger no longer raises PHP warnings.
* Fixed: "Connect to Joinotify" no longer ends in "invalid code".
* Fixed: workflow step descriptions can no longer run scripts in the builder.
* Fixed: actions registered with `joinotify_register_action()` show their description in the action library.

= 2.4.2 =
* New: JSON export on the workflows, history and queue screens.
* New: "Items per page" selector on the workflows, history and queue tables.
* New: `{{ wc_shipping_method_total }}` returns the shipping cost and method as plain text.
* Changed: refused sends show WhatsApp's own explanation in the builder test and the debug log.
* Changed: tables show 25 rows per page by default.
* Fixed: template variables are sent as one line of plain text, as WhatsApp requires.
* Fixed: templates built outside the builder are no longer refused with error 132018.
* Fixed: saving the test number no longer announces a refused send as a success.
* Fixed: actions in the history and queue tables no longer lose the active filters.
* Fixed: template sends record their rendered text in the history from the first send.
* Fixed: `{{ wc_shipping_address }}` is described as the shipping cost it returns; use `{{ wc_shipping_full_address }}` for the address.

= 2.4.1 =
* New: the message history records the rendered text and values of each template send.
* New: debug mode logs every delivered message, not only failures.
* New: `Joinotify/Api/Template_Dispatch_Log` filter to hide template values, while login codes are never stored.
* Changed: the debug log viewer indents structured details.
* Fixed: templates with named variables were refused by WhatsApp.
* Fixed: login codes and builder tests were listed under the "API" source.
* Fixed: the history type column showed the raw `template` slug.
* Fixed: the setup wizard's country list opened behind the wizard.
* Fixed: the "Joinotify account" field looked empty after a key was saved.
* Removed: the "Plugin updates" row in Settings → About.

= 2.4.0 =
* New: delivery retries are configurable under Settings → General, with 5 attempts by default.
* New: pending resends can be cancelled from the message history.
* New: Error column in the message history.
* Changed: the setup wizard uses the same style as the other admin screens.
* Fixed: messages stuck in "queued" now settle as sent or failed.
* Fixed: a test message refused by WhatsApp was announced as a success.
* Fixed: failed sends explain the reason on screen instead of only in the log.

= 2.3.4 =
* Fixed: an early log entry could crash the whole site with a fatal error.
* Fixed: the workflow post type is registered again, so its permissions apply.
* Fixed: the plugin is compatible with WooCommerce High-Performance Order Storage again.

= 2.3.3 =
* No change to the plugin; only the release pipeline was fixed.

= 2.3.2 =
* Changed: the phone number library moves to `giggsey/libphonenumber-for-php-lite`.
* Removed: the legacy Evolution relay, so messages go through the official WhatsApp Cloud API only.
* Removed: the Proxy API, the transport selector, QR code registration and the WhatsApp group listing.
* Removed: the scheduled phone connection check.
* Security: the API key embedded in the plugin is gone.
* Changed: `Transport` always resolves to the official API and gains `Transport::is_ready()`.
* Changed: `slots-manager.joinotify.com` is no longer contacted or listed as an external service.
* New: "Source code and build" section in this readme.
* Changed: no remote asset URL remains in the emoji picker.

= 2.3.1 =
* Changed: WordPress 7.0 is now required.
* Changed: AI uses the WordPress AI Client under Settings → Connectors instead of the plugin's own keys.
* Removed: the "PHP Snippet" action, and saved snippet steps no longer run.
* New: the builder warns when a step only works within the 24-hour window.
* Changed: the OTP login template is picked from the approved templates.
* Fixed: accounts with more than 100 templates lost the rest in the template pickers.
* Changed: translations are delivered by translate.wordpress.org.
* Changed: the Joinotify menu sits below the WordPress items.
* Changed: the standalone "Joinotify OTP Login" plugin is no longer deactivated on its own.
* Removed: country detection by IP address.
* Removed: the button that installed the cart recovery add-on.
* Changed: builder styles and the setup notice go through the WordPress asset queue.

= 2.3.0 =
* New: WhatsApp connection through the official Joinotify API.
* New: Meta-approved message templates and a template action.
* New: reply buttons, option lists, link buttons, location, contact cards, stickers and reactions.
* New: delivery confirmation (delivered, read, failed).
* New: six-step setup wizard shown after activation.
* New: anonymous usage reporting, off by default, identified by a random local value.
* New: `Joinotify/Settings/Saved`, `Joinotify/Sender_Selected`, `Joinotify/Notification_Queue/Item_Retried` and `Joinotify/Debug_Log/Recorded` actions.
* Changed: the plugin is free software under the GPLv2 with every feature unlocked.
* Changed: updates are delivered by WordPress.org.
* Changed: the Proxy API is off by default on new installs.
* Changed: OTP login codes are sent through an authentication template.
* Changed: the workflow template library is served by the Joinotify API.
* Removed: the extension installer that downloaded packages from an external URL.
* Fixed: deactivating the plugin now clears its scheduled tasks.
* Deprecated: the Proxy API setting under Settings → General.

= 2.2.0 =
* New: "Loop" action that runs its body once per item of a collection.
* New: loop variables inside the loop body.
* Removed: WhatsApp disconnection notice.
* Fixed: the default country code was not applied to numbers typed without one.

= 2.1.0 =
* New: WooCommerce digital product delivery from the workflow builder.
* New: attachments on e-mail (Resend) and WhatsApp media actions.

== Upgrade Notice ==

= 2.7.2 =
Recommended for WooCommerce stores: changing an order status in the admin no longer ends in a fatal error.

= 2.7.1 =
Builds the JSON body of the "HTTP request" action from a table of fields, with a code editor and syntax checking for typed JSON. Existing actions keep their JSON body.

= 2.7.0 =
Adds an "HTTP request" action, resending of failed messages and WooCommerce account and stock triggers. "New order (Processing)" becomes "Payment confirmed"; saved workflows move to it on their own.

= 2.6.2 =
The plugin has a new name, "Joinotify – Notifications & Marketing Automation". Workflows, settings and messages are not affected.

= 2.6.1 =
Variables take modifiers such as `remove_home_url`, and a new variable gives the review link of the first purchased product, ready to complete a WhatsApp template URL button.

= 2.6.0 =
Recommended for every installation. Templates with a media or location header, coupons, offers and carousels can be sent from workflows, a condition branches on the WhatsApp 24-hour window, and the API key is stored encrypted.

= 2.5.1 =
Recommended for sites not in English: brings back the translations 2.5.0 left out, so Joinotify no longer shows in English.

= 2.5.0 =
Recommended for every installation. Fixes the "Connect to Joinotify" button, the "New order" trigger on the checkout block and script running from a builder step's description. Adds Audiences & Contacts and the Joinotify Cloud sync, which stays off until you switch it on.

= 2.4.2 =
Recommended for sites that send WhatsApp templates: values with line breaks or prices no longer get a template refused. If a template uses `{{ wc_shipping_address }}` for the delivery address, switch it to `{{ wc_shipping_full_address }}` — the first has always returned the shipping cost.

= 2.4.1 =
Recommended for sites that send WhatsApp templates. Templates with named variables are no longer refused, and the message history now shows the text and values each template carried. Login codes are never stored.

= 2.4.0 =
Recommended for every installation. Failed sends now explain themselves on screen instead of only in the log, messages stuck in "queued" settle correctly, and a test message refused by WhatsApp is no longer reported as sent. Note one behaviour change: a failed message is now retried 5 times by default instead of 120, adjustable under Settings → General → Delivery retries.

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

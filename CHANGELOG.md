Version 2.3.2 (2026-08-23)
* Removed: the legacy transport (Evolution / slots-manager) is gone for good
     - **Heads-up:** sites still sending through that path stop sending until the Joinotify account is connected under Settings → General → WhatsApp Cloud API
     - The plugin shipped an embedded slots-manager API key, encrypted with the decryption key sitting on the next line. It was the same credential for every installation and anyone could extract it; it was removed along with the homegrown encryption that hid it
     - Also gone: the Proxy API and its routes, the "Message transport" selector, phone registration by QR Code/OTP, the connection state lookup and the group listing — features that only ever existed on the relay
     - The scheduled routine that checked phone connection every 6 hours was removed and is automatically unscheduled on update
     - Numbers are still connected in the Joinotify panel and imported with the "Sync numbers" button
     - `slots-manager.joinotify.com` was dropped from the external services list in readme.txt, since the plugin no longer contacts it
* Changed: the transport is no longer configurable and `Transport` always resolves to the official API
     - It remains the single outbound point for messages, so swapping transports in the future is still a one-file change
     - New `Transport::is_ready()` method, which reports whether the site already has a key to send with
     - The `whatsapp` channel identifier stays registered, pointing at the official API channel: messages already queued before the update still find a valid channel
     - The `Joinotify/Transport/Active` filter was removed along with the transport choice
* Documentation: readme.txt gains the "Source code and build" section required by the WordPress.org directory
     - It explains that the PHP is not compiled and that the complete Vue frontend source ships in the package, under `app/src`, with all the build configuration needed to reproduce `app/dist`
     - It lists the third-party libraries bundled into the build, each with its source code address and license
     - It points to the public development repository
* Changed: the build plugin that strips the emoji picker's CDN URL now targets the library constant, not the address
     - The address no longer appears in any plugin file, which was triggering a false positive in the directory review
     - It also stops depending on the host: if the library switches CDN in a future version, the URL is still removed from the package

Version 2.3.1 (2026-08-21)
* Changed: WordPress 7.0 is now the minimum required version
     - From that release on, WordPress ships the built-in AI Client, which is what the plugin's AI integration uses now
* Changed: AI no longer stores its own keys and now uses the WordPress AI Client
     - The provider and the key are configured once under Settings → Connectors, in WordPress itself, and apply to every plugin on the site
     - The "OpenAI" and "Anthropic" integrations were removed from Settings → Integrations, along with the key and model fields
     - The plugin no longer talks directly to api.openai.com or api.anthropic.com; WordPress makes the call, to whichever provider you chose
     - Global instructions and the default temperature stay in the plugin, now under Settings → General → Artificial Intelligence
     - The setup wizard no longer asks for a key: the AI step now reports whether the site already has a provider and links to the Connectors screen
     - **Heads-up:** anyone already using AI needs to configure the provider under Settings → Connectors. The old keys remain in the database but are no longer used
* Removed: the builder's "PHP Snippet" action
     - The action executed arbitrary PHP code through `eval()`, which the WordPress.org directory does not allow
     - AI snippet generation, which existed only for that action, was removed as well
     - **Heads-up:** saved automations using that step still open, but the snippet step is no longer executed. Anyone depending on it should move the logic into their own plugin, hooked into the `Joinotify/...` hooks
* New feature: the builder warns when a step cannot start a conversation
     - On the official API, text, media, interactive messages and AI-generated content only reach the contact within 24 hours of their last reply
     - A flow that starts the conversation — abandoned cart, "order shipped", post-sale — has to begin with an approved template; otherwise Meta rejects the send and the failure only showed up in the log
     - The warning appears while editing the step and changes tone once the flow already has a template step. It does not appear on the legacy transport, because that rule does not exist there
     - Nothing changed about sending: this is edit-time guidance
     - Extensions can include their own actions in the warning through the `Joinotify/Transport/Free_Form_Actions` filter
* Improvement: the OTP login template is now picked from a list instead of typed
     - The list only shows approved authentication templates on your account, with a button to sync when one has just been approved
     - Templates are still created in the Joinotify panel; the plugin only reads and caches them
     - The language now comes from the selected template instead of a second hand-filled field — sending a language other than the approved one is rejected by Meta. The field still exists as a fallback
* Fix: accounts with more than 100 message templates lost the remainder in the selectors
     - The listing did not request a limit and received only the API's first page, with no sign that there was more
* Changed: compiled translation files no longer ship in the package
     - WordPress.org generates and delivers each language through translate.wordpress.org, and the review team asks that the package not duplicate that channel
     - Until the strings are approved there, non-English installations display the text in English
     - Anyone installing outside the directory can build a package with the languages using `npm run build -- --ship-locales`
     - The ZIP dropped from 3.35 MB to 2.15 MB
* Adjustments requested in the WordPress.org review
     - The builder styles moved out of the HTML into an enqueued stylesheet (`assets/css/builder-boot.css`), and the loader's utility rules are now scoped to it instead of leaking into the rest of the screen
     - The setup wizard notice no longer uses inline JavaScript: "Don't remind me again" became a nonce-signed link handled on the server
     - The admin menu position no longer competes with the WordPress items; it now sits below them, adjustable through the `Joinotify/Admin/Menu_Position` filter
     - The plugin no longer deactivates the old "Joinotify OTP Login" on its own: it shows a notice linking to the Plugins screen and leaves the decision to you
     - Removed IP-based country detection, which queried ipinfo.io without being declared in the readme. The function had no callers and was broken (the URL never interpolated the address)
     - The emoji picker no longer loads images from an external CDN: the URL is stripped from the package at build time (the picker already used native emoji, so nothing changes on screen)
     - `giggsey/libphonenumber-for-php-lite` updated from 8.13.55 to 9.0.37
* Removed: the button that installed the cart recovery add-on from an external address
     - The button no longer worked (there was no code left responding to the click) and the remote address went against the directory guidelines
     - In its place, the builder now simply states which plugin needs to be installed and activated
* Compliance fixes reported by Plugin Check: prepared and documented database queries, output escaping on the integrations icon, `translators:` comments and numbered placeholders in every translatable string, `date()` replaced by `wp_date()`/`gmdate()`, `mt_rand()` by `wp_rand()`, `strip_tags()` by `wp_strip_all_tags()`, and the debug calls (`error_log`/`print_r`) routed to the plugin's own log
* Fix: the translation file (`.pot`) generator read the editor's history folder and brought back strings already removed from the code; it now also carries the `translators:` comments into the file, which used to be lost

Version 2.3.0 (2026-08-17)
* New feature: WhatsApp connection through the official Joinotify API, replacing the Proxy API format over the Evolution API
     - "Connect to Joinotify" button in the settings: the account is authorized in the panel and the API key is delivered to the site without any copy and paste (pasting the key manually is still available)
     - Numbers connected in the panel are imported into the site, with verified name, phone number identifier, Meta-assigned quality and 24-hour business-initiated conversation limit
     - Support for multiple numbers and multiple business accounts (WABA) on the same account: each action picks which number the message goes out from
     - Anyone still on the old format keeps working normally and starts seeing a deprecation notice
* New feature: Meta-approved message templates
     - Your account's templates are listed inside the builder, with a content preview, category, language and approval status
     - New "WhatsApp: Template message" action, with one Joinotify variable per template variable
     - Button to sync the templates when one is created straight in Business Manager
     - Warning when the chosen template is paused, disabled or rejected by Meta
     - Templates are the only way to reach someone outside the 24-hour window, which is the situation for most automatic flows
* New message types allowed by the official API: reply buttons, option list, link button, location, contact card, sticker and reaction
* New feature: real delivery confirmation
     - The site now receives the account's events and records when the message was delivered, read or rejected, instead of only "accepted by the API"
     - Delivery failures now appear in the debug log with the reason reported by Meta
     - Messages received from the contact open the 24-hour window, allowing free-form replies
* Improvement: the OTP login access code is now delivered through an authentication template on the official API, with configurable name and language
* Improvement: when the API asks to wait (rate limit), the retry respects exactly the time reported
* Deprecated: "Enable Proxy API", under Settings → General, is now marked as deprecated and will be removed in an upcoming version
     - The routes keep working for now, but sending is exclusively through the Joinotify API (official WhatsApp API)
     - The proxy settings now display a deprecation notice
     - The proxy route responses now report the deprecation in the "Deprecation" and "X-Joinotify-Deprecation" headers, and every call is recorded in the debug log to help identify integrations still on the old format
* Note: the official API does not offer sending to groups; in this mode the group actions are unavailable and explain why
* Improvement: the setup wizard is now displayed full screen, over the WordPress dashboard
     - The admin bar and menu are covered while the wizard is open, so the flow is not interrupted halfway
     - The page behind no longer scrolls along, and the loading skeleton already appears full screen, without the jump that happened while mounting
     - New close button in the top right corner, available in every step
* Usage data reporting, which had existed since 2.4.0 with no destination, now actually works
     - It stays **off by default**: nothing leaves the site before you accept it in the wizard
     - The site is identified by a random value generated right here — never derived from the address — plus the API key you already use to send messages
     - The identifier appears under Settings → About, so you can quote it in a support ticket; without it, support has no way to find your site
     - Events are accumulated and sent in batches by a scheduled task, at most every few hours, never during a page load
     - A repeated event on the same day counts once: a store with five thousand daily orders generates about ten events, not five thousand
     - Only what is in the catalog goes out, and every property has a closed type — there is no free-text field, so message content does not fit the format
     - Error codes are normalized before leaving; the detail stays in an identifier for the code point that produced the failure
     - Turning it off clears whatever was still queued and tells the server to stop counting this installation (the `Joinotify/Telemetry/Send_Opt_Out` filter suppresses that last notice)
     - The wizard now also shows examples of the events and how the site is identified
* Improvement: the workflow template library is now served by the Joinotify API, at the same address as the account (`api.joinotify.com`), instead of the dedicated address it used before
     - When a template is used, the download is counted in the library, which now feeds each template's install count
     - When a template is revised, the site notices through the signature published in the catalog and fetches the new version by itself, with no cache clearing
     - Failures loading the library now appear in the debug log with the reason reported by the API
* Fix: the plugin removed none of its own scheduled tasks on deactivation
     - A site that deactivated the plugin kept the schedules in the database until someone cleaned them by hand
     - Deactivation now clears all of them. Nothing else is erased — deactivating is not uninstalling
* New hooks for extensions
     - `Joinotify/Settings/Saved`, with the values before and after each save
     - `Joinotify/Sender_Selected`, when the site gains a sender number (it carries only how it was chosen, never the number)
     - `Joinotify/Notification_Queue/Item_Retried`, when a message fails and goes back into the queue
     - `Joinotify/Debug_Log/Recorded`, fired on errors even with logging turned off
* The plugin is now 100% free and open source software, under the GNU GPL v2 or later license
     - Every feature is unlocked: there is no paid version, trial period or license check anymore
     - The "License" screen and the licensing system were removed; the Joinotify API key is now the only credential
     - On update, the license key, the status and the licensing server data are automatically deleted from the database
* New feature: 6-step setup wizard, shown right after activation
     - Default country, pre-selected from the WooCommerce store address, the site language or the timezone
     - Connection to Joinotify: the API key is validated on the spot and the account's numbers are imported
     - AI provider (optional), with the provider's key
     - Plugin documentation, at https://docs.joinotify.com
     - Authorization for anonymous usage data reporting
     - At the end, create the first automation or go to the settings
     - Older installations that never went through the wizard also see it, once, when opening a Joinotify screen
* New feature: anonymous usage data reporting, off by default
     - The wizard shows exactly what would be sent before you decide
     - It never includes the site address, e-mail, phone numbers, contacts, message content, flows or credentials
     - It can be turned off at any time under Settings → About
* New feature: settings modal on the WhatsApp card, under Settings → Integrations
     - The "Configure" button opens the modal with the Joinotify API key, the message transport, the phone number ID and the business account ID
     - The "Connect" button validates the key against the API on the spot and imports the account's numbers, filling in the phone number ID and the business account ID automatically
     - If the key is rejected, the previous key is restored — the site is never left with a key that just failed
     - The "Disconnect" button erases the key without removing the numbers already imported
     - With a key saved, the field displays its public prefix (the rest is masked) plus a "Remove key" button, with confirmation
     - The key entered in the setup wizard appears already saved in the field, and saving the settings does not erase it
     - The API key is not sent to the browser on any screen: the settings, the automation builder and the save response receive only the public prefix and the fact that a key is stored
     - The WhatsApp API key, the Telegram token and the Resend key are now excluded from the settings export
* Changed: updates are now delivered by WordPress itself
     - The plugin's own update checker and the "Automatic updates" and "Update notices" options were removed
     - Sites installed outside the WordPress.org directory need to reinstall the plugin from the directory to receive updates again
* Changed: the Proxy API (deprecated) now ships turned off on new installations
* Removed: the extension installer that downloaded packages from an external address
* Changed: the builder no longer offers actions from disabled integrations
     - Telegram, Resend, WhatsApp and the WooCommerce discount coupon only appear in the action library when the corresponding integration is active under Settings → Integrations
     - Before, those actions showed up even when off and the automation delivered nothing when executed
     - Already saved automations keep opening and displaying those steps normally, even if the integration is disabled afterwards
* Changed: every WhatsApp action in the builder now displays the WhatsApp logo
     - Approved template, AI message, reply buttons, option list, link button, location, contact, sticker and reaction were using generic icons
* Compliance with the WordPress.org plugin directory guidelines: readme.txt declaring every external service used, GPL license and the Vue frontend source code distributed with the package
* Fix: monetary values arrived broken in the message
     - `{{ wc_currency_symbol }}` sent WooCommerce's internal code instead of the symbol: the Brazilian real showed up as `&#82;&#36;` instead of `R$`
     - Totals were sent with the order's raw number (`68.70`), without the decimal separator of the store's language and different from what the builder preview showed
     - `{{ joinotify_coupon_discount_formatted }}` sent the entire price HTML, with the `<span>` tags visible in the message
     - `{{ fcrc_cart_total }}` (Flexify Checkout abandoned cart) sent the value with no formatting at all
     - **Heads-up:** `{{ wc_order_total }}`, `{{ wc_total_discount }}`, `{{ wc_total_tax }}` and `{{ wc_total_refunded }}` now already include the currency symbol (`R$ 68,70`), as the builder preview always showed. Flows writing `{{ wc_currency_symbol }}{{ wc_order_total }}` need to drop the `{{ wc_currency_symbol }}`, otherwise the symbol appears twice

Version 2.2.0 (2026-08-03)
* New feature: "Loop" action in the flow builder, which walks through a collection and runs the body actions once per item, allowing several messages to be sent in sequence
     - Available collections: the order's digital files, the order's purchased items and a list from a variable (split by line or delimiter)
     - Delivers one file per message: for example, one media message for each downloadable file linked to the order
     - New "Loop item file" attachment source, which sends the current iteration's file without consuming the customer's download limit
     - Support for a wait time (delay) between iterations
* New loop variables, available inside the loop body: {{ loop_value }}, {{ loop_index }}, {{ loop_number }}, {{ loop_count }}, {{ loop_file_name }}, {{ loop_download_url }}, {{ loop_product_name }}, {{ loop_item_name }} and {{ loop_item_quantity }}
     - Loop variables are processed in any field they are inserted into (caption, media URL, attachment URL and recipient)
* Removed: Notify when WhatsApp disconnects
* Bug fixes
     - The default country code (dial code) was not applied when the number did not include the code, making the send fail; the configured fallback country is now used correctly

Version 2.1.0 (2026-07-27)
* New feature: WooCommerce digital product delivery straight from the flow builder, sending files and PDFs by e-mail or WhatsApp
* New feature: attachments in the e-mail (Resend) and WhatsApp media actions, with a reusable field that accepts media library files, links or the order's own digital files
     - Large e-mail attachments are automatically replaced by the download link, guaranteeing the message goes out
     - Multiple files can be sent in the WhatsApp media action
* New trigger: "Digital product access granted" (WooCommerce), recommended for delivery flows, since the download links already exist at that point
* New trigger: "Digital file downloaded" (WooCommerce), fired when the customer downloads a file, both with an optional per-product filter
* New WooCommerce variables for digital products: downloadable product list, file names and links, links without the name, expiration date, remaining downloads, link to the downloads area in the customer's account and download link for a specific product
* Improvement: variables now respect the chosen trigger at send time as well, preventing context-less variables from being filled in improperly
* Improvement: groundwork for the new license server, with automatic migration and no user intervention
     - The plugin switches to the new server as soon as the current one stops responding, keeping the same license key
     - Already activated sites are migrated in the background, without freezing the dashboard
     - No license is deactivated when the server is down or when there is a registration mismatch; in those cases the plugin keeps working and reports it on the license screen
     - Plugin updates are delivered by the new server after the migration
* Bug fixes
     - Argument order in the WooCommerce partial refund trigger, which made the order variable receive the refund identifier
     - An expired or server-rejected license kept the premium features unlocked for up to 24 hours
     - Opening the license screen could deactivate the site's license when the expiration date had already passed
     - Lifetime licenses marked as "Unlimited" or "Lifetime" were treated as expired
     - The license activation response could be reused on deactivation, since both shared the same cache

Version 2.0.0 (2026-07-02)
* New flow builder: fully redesigned interface as a visual canvas, with drag and drop, step connections, zoom, fit to screen and undo/redo buttons
* New feature: Artificial Intelligence in the flow builder
     - Automatic creation of complete flows from a text description
     - Generation of dynamic WhatsApp messages with AI
     - Generation of smart variables with AI
     - Creation of PHP snippets with AI assistance
* New feature: custom text variables, created by the user from the site's content types and fields (includes support for WooCommerce orders)
* New feature: sent message history, with filters and a month-and-year date picker
* New feature: passwordless login by code (OTP) sent via WhatsApp, with support for new channels in the future
* New feature: export and import all plugin settings in a JSON file
* New feature: "Password reset request" trigger, with a reset link variable
* New message editor with visual formatting (bold, italic, emoji) automatically converted to the WhatsApp standard
* Improvement: media preview right in the WhatsApp media message step
* Improvement: text variables highlighted and clickable inside the fields, with a warning when unavailable in the chosen trigger
* Improvement: actions flag when there are required settings pending
* Improvement: a more complete condition catalog, with list-based selection and a product picker in the values
* Improvement: message scheduling with a specific date and time (wait time), with a queue and reprocessing of failed notifications
* Improvement: "Integrations" tab renamed to "Apps"
* Improvement: manual update check in the "About" tab
* Improvement: automatic migration of flows from previous versions when updating the plugin
* Performance optimizations in plugin loading and in the admin screens
* Improved translation support (Portuguese, English and Spanish)
* New languages added: French, Italian, German and European Portuguese

Version 1.4.7 (2026-04-12)
* Added: queue for message processing
* Frontend technology switched to Vue.js, Vite and Tailwind CSS

Version 1.4.6 (2026-02-10)
* Optimizations
* Bug fixes
     - Fatal error due to the missing ElementorPro\Modules\Forms\Classes\Action_Base class

Version 1.4.5 (2026-01-24)
* Bug fixes
     - Fatal error loading the "All flows" page: Call to undefined function convert_to_screen()
* Optimizations
* Added: WooCommerce -> full address format (billing and shipping)

Version 1.4.4 (2025-12-12)
* Optimizations
     - Security improvements in class instantiation

Version 1.4.3 (2025-12-08)
* Bug fixes
     - Check whether the order was paid
     - Emoji encoding in messages
     - Post count in the All flows table

Version 1.4.2 (2025-11-27)
* Bug fixes
     - String validation error with the Proxy API
* Optimizations

Version 1.4.1 (2025-10-28)
* Change to the update check API

Version 1.4.0 (2025-08-29)
* Optimizations
* Added: caption for WhatsApp media messages
* Added: text variables {{ post_title }}, {{ post_date }}, {{ post_content }}, {{ post_link }}, {{ post_tags }}, {{ post_categories }} and {{ post_featured_image }}

Version 1.3.7 (2025-08-13)
* Bug fixes
     - Unable to edit flows with the Academy LMS plugin and similar

Version 1.3.6 (2025-07-11)
* Bug fixes
     - Priority and arguments of add_action() in the Woo_Subscriptions class passed outside the callback array
     - Failure checking order payment status

Version 1.3.5 (2025-07-09)
* Bug fixes
     - Fatal error when changing order status: Uncaught Error: Class name must be a valid object or a string in /woocommerce/src/Internal/DataStores/Orders/OrdersTableDataStore.php:1524
* Added: post status validation in the "Post status changed" trigger

Version 1.3.4 (2025-06-16)
* Bug fixes
     - Cart recovery link is empty (Flexify Checkout - Abandoned cart recovery)
* Optimizations
     - Responsiveness improvements on desktop
* Added: show version update notices

Version 1.3.3 (2025-06-10)
* Added: receive notices when WhatsApp is disconnected
* Removed: on entering step 1 of the Flexify Checkout integration
* Removed: on entering step 2 of the Flexify Checkout integration
* Removed: on entering step 3 of the Flexify Checkout integration
* Added: text variables {{ fcrc_first_name }}, {{ fcrc_last_name }}, {{ fcrc_phone }}, {{ fcrc_email }}, {{ fcrc_cart_total }} (Flexify Checkout - Abandoned cart recovery)
* Added: trigger "Lead captured via modal" (Flexify Checkout - Abandoned cart recovery)
* Added: trigger "Lead captured via checkout" (Flexify Checkout - Abandoned cart recovery)

Version 1.3.2 (2025-05-29)
* Bug fixes
     - Undefined set_default_options() method in the Helpers class at line 171
* Optimizations
     - Fill in the sender when importing a flow

Version 1.3.1 (2025-05-26)
* Bug fixes
     - Wait time action

Version 1.3.0 (2025-05-08)
* Bug fixes
* Optimizations
* Security fix for whether the sender is registered on the site
* Change to the WhatsApp message sending API

Version 1.2.5 (2025-03-24)
* Bug fixes
     - Fix to the hook calls of the Woo Subscriptions integration
* Optimizations

Version 1.2.2 (2025-03-17)
* Bug fixes:
     - Text variables in WooCommerce triggers in test mode were not being replaced correctly.
* Optimizations
* Changed: text variables {{ wc_order_total }}, {{ wc_total_discount }}, {{ wc_total_tax }}, {{ wc_total_refunded }} now return formatted values with the currency symbol.
* Removed: "Order status" condition in the "New order" trigger
* Added: adding actions between existing actions in the flow
* Added: text formatting with WhatsApp variables
* Added: English translation (en-US)
* Added: Spanish translation (es-ES)

Version 1.2.0 (2025-03-12)
* Bug fixes
* Optimizations
* Added: "giggsey/libphonenumber-for-php" library for formatting and validating phone numbers in international format
* Added: "Selectize" library for multi-selecting elements
* Removed: text variable {{ post_id }}
* Added: "Payment method", "Shipping method" and "Order paid" conditions
* Added: trigger "Payment processed by PayPal"
* Added: "Routines" class for running routines; and added a routine that checks phone connection and updates
* Added: text variables {{ fc_inter_pix_copia_cola }}, {{ fc_inter_pix_expiration_time }}, {{ fc_inter_bank_slip_url }} and {{ fcrc_recovery_link }}

Version 1.1.2 (2025-02-24)
* Bug fixes

Version 1.1.1 (2025-02-24)
* Bug fixes

Version 1.1.0 (2025-02-24)
* Bug fixes
* Optimizations
* Added: enable debug mode
* Added: integration with Elementor forms
* Removed: text variables {{ br }} and {{ phone }}
* Removed: automatic settings update
* Added: text variables {{ wc_billing_first_name }}, {{ wc_billing_last_name }}, {{ wc_billing_email }}, {{ wc_billing_phone }}, {{ wc_shipping_phone }}, {{ wc_order_status }}, {{ wc_billing_full_address }}, {{ wc_shipping_full_address }}, {{ wc_order_total }}, {{ wc_total_discount }}, {{ wc_total_tax }}, {{ wc_total_refunded }}, {{ wc_coupon_codes }}, {{ wc_payment_method_title }}, {{ wc_shipping_address }}, {{ wc_checkout_field=[FIELD_ID] }}
* Added: enable automatic updates
* Added: "PHP Snippet" action in the flow builder
* Added: "Discount coupon" action in the flow builder for the WooCommerce integration
* Added: get WhatsApp group information
* Changed: emoji library swapped (Picmo -> EmojioneArea)

Version 1.0.5 (2024-12-05)
* PHP 7.4 compatibility fix

Version 1.0.4 (2024-11-22)
* Bug fixes

Version 1.0.3 (2024-11-22)
* Bug fixes

Version 1.0.2 (2024-11-22)
* Bug fixes

Version 1.0.1 (2024-11-21)
* Bug fixes

Version 1.0.0 (2024-11-20)
* Initial release

# Changelog

All notable changes to Joinotify are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Releases before 2.0.0 did not strictly follow SemVer, so their numbering is not a compatibility promise.

## [Unreleased]

## [2.7.2] - 2026-11-05

### Fixed

- WooCommerce workflows run outside the storefront, such as an order status change in the admin, no longer end in a fatal error

## [2.7.1] - 2026-11-04

### Added

- The JSON body of the "HTTP request" action can be built from a table of fields, each with its own type
- The JSON body of the "HTTP request" action is checked as it is typed, showing the line and column of a syntax error
- The JSON body of the "HTTP request" action is edited in a code editor with highlighting, a "Format" button and variable suggestions

### Fixed

- Workflow titles no longer show HTML entities such as `&#8211;` in the admin pages

## [2.7.0] - 2026-11-03

### Added

- Failed messages can be sent again from the message history, one at a time or up to 50 at once
- "Customer account created" WooCommerce trigger
- "Product low in stock" and "Product out of stock" WooCommerce triggers
- "Product back in stock" WooCommerce trigger
- "Back in stock notice sent" WooCommerce trigger
- Customer and product variables for the new triggers, such as `{{ wc_customer_first_name }}` and `{{ wc_product_name }}`
- "User role" and "User metadata" conditions on the "Customer account created" trigger
- "HTTP request" workflow action, which calls any public API and keeps the response for the next actions
- Developers: `writes_payload` on `joinotify_register_action()` lets a handler pass data to the next actions
- Developers: `Joinotify/Builder/Sealed_Action_Fields` and `Joinotify/AI/Workflow_Generator/Excluded_Actions` filters

### Changed

- "New order (Processing)" is now "Payment confirmed", which runs once per order when the payment is confirmed
- The `Joinotify/Process_Workflows/Woocommerce/Checkout_Order_Processed` filter is replaced by `Joinotify/Process_Workflows/Woocommerce/Payment_Complete`
- WooCommerce order variables are no longer offered on the account and stock triggers
- The message history is always on, and its switch is gone from Settings → About
- Interactive, location, contact, reaction and sticker messages keep their WhatsApp payload in the history

### Fixed

- Saving a workflow no longer drops the backslashes typed in it
- Local attachments are no longer recorded in the history with a broken media URL

## [2.6.2] - 2026-11-02

### Changed

- The plugin is renamed "Joinotify – Notifications & Marketing Automation", following the WordPress.org trademark guidelines
- "Tested up to" is declared only in `readme.txt`

## [2.6.1] - 2026-10-28

### Added

- Placeholder modifiers after a pipe: `remove`, `replace` and `remove_home_url`
- `joinotify_register_placeholder_modifier()` and the `Joinotify/Builder/Placeholder_Modifiers` filter for custom modifiers
- `{{ wc_first_product_review_path }}` returns the review path of the first purchased product

## [2.6.0] - 2026-10-28

### Added

- The Joinotify API key can be defined in `wp-config.php` with `JOINOTIFY_CLOUD_API_TOKEN`
- `joinotify_send_whatsapp_template()` sends an approved WhatsApp template from code
- `joinotify_get_whatsapp_templates()`, `joinotify_get_whatsapp_template()`, `joinotify_build_whatsapp_template_components()` and `joinotify_render_whatsapp_template()` helpers for WhatsApp templates
- `joinotify_get_whatsapp_window()` tells whether a contact's 24-hour window is open
- The WhatsApp template action supports image, video and document headers, copy-code buttons and authentication code buttons
- The WhatsApp template action supports location headers, limited-time offers and carousels
- "WhatsApp 24-hour window" condition to branch a workflow on whether the window is open
- `Joinotify/Cloud_Sync/Links` filter to change the links an order event carries

### Changed

- The checkout block's consent checkbox is ticked for customers who already agreed to marketing messages

### Fixed

- The checkout block's marketing consent checkbox is offered to every customer of a headless store

### Security

- The Joinotify API key is stored encrypted, so changing `AUTH_KEY` or `AUTH_SALT` requires connecting the site again

## [2.5.1] - 2026-09-30

### Fixed

- The plugin ships its bundled translations again
- Counts such as "%d contacts" use the plural form in every translated language
- The labels of the workflow post type are translated

## [2.5.0] - 2026-09-30

### Added

- A review request asks administrators to rate Joinotify on WordPress.org after ten minutes of use
- Joinotify Cloud sync, off by default, sends the site's customers and events to the Joinotify account
- The sync delivers events in background batches, retries failures and pauses on a revoked key or a copied site
- Sync events for WooCommerce orders and subscriptions, sign-ups, forms and Flexify Checkout leads and carts
- Optional marketing consent checkbox in the checkouts, sign-up forms and My account
- "Send existing customers" sends the store's current customers to Joinotify once
- Sync monitor with its status, pending and failed events, and retry or discard buttons
- WordPress's personal data export and erase tools cover the sync
- "Send webhook" workflow action, which posts the trigger's data or a custom JSON to any public URL
- `joinotify_track()` sends custom events to Joinotify flows
- Joinotify → Audiences & Contacts screen for the contact base of the Joinotify account
- Contacts tab with search, filters, sorting, CSV export and a contact detail panel
- Contacts can be added by hand
- Fields & tags tab to manage the account's custom fields and tags
- A tag can be added to or removed from many contacts at once
- Sources tab that gathers the Cloud sync settings
- User and order meta can fill the contact's custom fields
- Per-form contact rules for WPForms and Elementor forms
- "Save contact" and "Tag contact" workflow actions
- Audiences tab with a filter builder and a count preview
- CSV import of contacts
- Duplicate contacts can be merged
- Suppression list tab
- Settings declared with the `placeholder` component get the variable picker

### Changed

- Admin and OTP login icons come from Boxicons
- Focused fields show a solid two-pixel outline instead of a glow

### Fixed

- Strings from the Vue admin screens reach translate.wordpress.org
- The "New order" trigger runs for orders placed with the checkout block
- The WPForms PayPal Standard trigger no longer raises PHP warnings
- "Connect to Joinotify" no longer ends in "invalid code"
- Workflow step descriptions can no longer run scripts in the builder
- Actions registered with `joinotify_register_action()` show their description in the action library

## [2.4.2] - 2026-09-11

### Added

- `{{ wc_shipping_method_total }}` returns the shipping cost and method as plain text
- "Items per page" selector on the workflows, history and queue tables
- JSON export on the workflows, history and queue tables

### Changed

- Refused sends show WhatsApp's own explanation in the builder test and the debug log
- Tables show 25 rows per page by default

### Fixed

- Template variables are sent as one line of plain text, as WhatsApp requires
- Saving the test number no longer announces a refused send as a success
- Actions in the history and queue tables no longer lose the active filters
- Template sends record their rendered text in the history from the first send
- Template components built outside the builder are no longer refused with error 132018
- **Heads-up:** `{{ wc_shipping_address }}` returns the shipping cost, not the address, so use `{{ wc_shipping_full_address }}` for the address

## [2.4.1] - 2026-09-11

### Added

- The message history records the rendered text of each template send
- Debug mode logs every delivered message with the `dispatch_sent` code
- `Joinotify/Api/Template_Dispatch_Log` filter to redact template values in the history
- The debug log viewer indents JSON context

### Fixed

- Templates with named variables were refused by WhatsApp
- OTP and builder-test sends were recorded under the `api` source
- The history type column showed the raw `template` slug
- The setup wizard's country list opened behind the wizard
- The "Joinotify account" field showed an empty input after a key was saved

### Removed

- The "Plugin updates" row in Settings → About

## [2.4.0] - 2026-09-03

### Added

- Delivery retries are configurable under Settings → General
- Pending resends can be cancelled from the history screen
- Error column in the history table

### Changed

- The setup wizard uses the same styling as the other admin screens

### Fixed

- `queued` history rows are settled as `sent` or `failed` once their retry finishes
- A test message refused by WhatsApp was announced as a success
- Failed sends explain the reason instead of a generic error

## [2.3.4] - 2026-09-01

### Fixed

- Logs written before `wp_loaded` no longer crash the site with `ValueError: Path cannot be empty`
- The `joinotify-workflow` post type is registered again, so Editors can no longer edit workflows
- The WooCommerce HPOS compatibility declaration reaches WooCommerce again

## [2.3.3] - 2026-08-28

Release tooling only; the shipped package is unchanged since 2.3.2.

### Added

- Build and deploy scripts read local credentials from a Git-ignored `.env`
- WordPress.org directory artwork lives in `.wordpress-org/` and is deployed to SVN `assets/`

### Fixed

- The SVN deploy no longer corrupts its working copy with `E155033`

## [2.3.2] - 2026-08-23

### Added

- "Source code and build" section in readme.txt
- `npm run lint:php` checks every source file against PHP 8.1
- `Transport::is_ready()` reports whether the site has a key to send with

### Changed

- The phone number library moves to `giggsey/libphonenumber-for-php-lite`, shrinking `admin/vendor` from 22 MB to 3 MB
- The transport always resolves to the official API
- The build strips the emoji picker's CDN URL more reliably

### Removed

- **Heads-up:** the legacy Evolution transport is removed, so sites using it must connect a Joinotify account to keep sending
- The `Joinotify/Transport/Active` filter

## [2.3.1] - 2026-08-21

### Added

- The builder warns when a step only works within the 24-hour window

### Changed

- WordPress 7.0 is now the minimum required version
- **Heads-up:** AI uses the WordPress AI Client under Settings → Connectors instead of the plugin's own keys
- The OTP login template is picked from a list of approved authentication templates
- Compiled translation files no longer ship in the package
- Adjustments requested in the WordPress.org review

### Removed

- **Heads-up:** the "PHP Snippet" action is removed, and saved snippet steps no longer run
- The button that installed the cart recovery add-on from an external address

### Fixed

- Accounts with more than 100 templates lost the rest in the selectors
- Compliance issues reported by Plugin Check
- The `.pot` generator kept removed strings and dropped `translators:` comments

## [2.3.0] - 2026-08-17

### Added

- WhatsApp connection through the official Joinotify API, with multiple numbers per account
- Meta-approved message templates and the "WhatsApp: Template message" action
- Reply buttons, option list, link button, location, contact card, sticker and reaction messages
- Delivery confirmation records when each message was delivered, read or rejected
- A 6-step setup wizard shown after activation
- Anonymous usage data reporting, off by default
- WhatsApp card modal to connect, validate and remove the Joinotify API key
- `Joinotify/Settings/Saved`, `Joinotify/Sender_Selected`, `Joinotify/Notification_Queue/Item_Retried` and `Joinotify/Debug_Log/Recorded` hooks

### Changed

- The plugin is 100% free and open source under the GNU GPL v2 or later
- Updates are delivered by WordPress instead of the plugin's own update checker
- The OTP login code is sent through an authentication template
- Rate-limited sends are retried after the wait time the API reports
- Group actions are unavailable on the official API
- The setup wizard is displayed full screen
- The workflow template library is served by `api.joinotify.com`
- The Proxy API ships turned off on new installations
- The builder no longer offers actions from disabled integrations
- WhatsApp actions display the WhatsApp logo
- Compliance with the WordPress.org plugin directory guidelines

### Deprecated

- "Enable Proxy API" under Settings → General

### Removed

- The extension installer that downloaded packages from an external address

### Fixed

- Scheduled tasks are removed on deactivation
- Monetary values arrived broken in messages
- **Heads-up:** `{{ wc_order_total }}` and the other totals include the currency symbol, so drop any `{{ wc_currency_symbol }}` prefix

## [2.2.0] - 2026-08-03

### Added

- "Loop" action, which runs its body once per item of a collection
- Loop variables such as `{{ loop_value }}`, `{{ loop_index }}` and `{{ loop_file_name }}`

### Removed

- Notify when WhatsApp disconnects

### Fixed

- The default country code was not applied to numbers without one

## [2.1.0] - 2026-07-27

### Added

- WooCommerce digital product delivery by e-mail or WhatsApp from the flow builder
- Attachments in the e-mail (Resend) and WhatsApp media actions
- "Digital product access granted" and "Digital file downloaded" WooCommerce triggers
- WooCommerce digital product variables, such as file names and download links

### Changed

- Variables respect the chosen trigger at send time
- Groundwork for the new license server

### Fixed

- The partial refund trigger passed the refund ID as the order
- An expired license kept premium features unlocked for up to 24 hours
- Opening the license screen could deactivate an expired license
- "Unlimited" and "Lifetime" licenses were treated as expired
- License activation and deactivation shared the same cache

## [2.0.0] - 2026-07-02

### Added

- AI in the flow builder, creating flows, messages and variables from a text description
- Custom text variables from the site's content types and fields
- Sent message history with filters
- Passwordless login by code (OTP) sent via WhatsApp
- Export and import of all plugin settings
- "Password reset request" trigger
- French, Italian, German and European Portuguese translations

### Changed

- New visual canvas flow builder with drag and drop, zoom and undo/redo
- New message editor with formatting converted to the WhatsApp standard
- Media preview in the WhatsApp media message step
- Text variables are highlighted inside the fields
- Actions flag pending required settings
- Larger condition catalog with list-based selection
- Message scheduling with a queue and reprocessing of failed notifications
- "Integrations" tab renamed to "Apps"
- Manual update check in the "About" tab
- Flows from previous versions are migrated automatically
- Performance optimizations
- Improved Portuguese, English and Spanish translations

## 1.4.7 - 2026-04-12

### Added

- Queue for message processing

### Changed

- Frontend switched to Vue.js, Vite and Tailwind CSS

## [1.4.6] - 2026-02-10

### Changed

- Optimizations

### Fixed

- Fatal error due to the missing `ElementorPro\Modules\Forms\Classes\Action_Base` class

## [1.4.5] - 2026-01-24

### Added

- WooCommerce full address format for billing and shipping

### Changed

- Optimizations

### Fixed

- Fatal error on the "All flows" page caused by the undefined `convert_to_screen()` function

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
- Post text variables, such as `{{ post_title }}` and `{{ post_link }}`

### Changed

- Optimizations

## [1.3.7] - 2025-08-13

### Fixed

- Unable to edit flows with the Academy LMS plugin and similar

## [1.3.6] - 2025-07-11

### Fixed

- `add_action()` arguments in the `Woo_Subscriptions` class passed outside the callback array
- Failure checking order payment status

## [1.3.5] - 2025-07-09

### Added

- Post status validation in the "Post status changed" trigger

### Fixed

- Fatal error in WooCommerce's `OrdersTableDataStore` when changing order status

## [1.3.4] - 2025-06-16

### Added

- Version update notices

### Changed

- Responsiveness improvements on desktop

### Fixed

- Empty cart recovery link (Flexify Checkout - Abandoned cart recovery)

## [1.3.3] - 2025-06-10

### Added

- Notices when WhatsApp is disconnected
- Flexify Checkout abandoned cart variables, such as `{{ fcrc_first_name }}` and `{{ fcrc_cart_total }}`
- "Lead captured via modal" and "Lead captured via checkout" triggers (Flexify Checkout - Abandoned cart recovery)

### Removed

- The step 1, 2 and 3 entry triggers of the Flexify Checkout integration

## [1.3.2] - 2025-05-29

### Changed

- The sender is filled in when importing a flow

### Fixed

- Undefined `set_default_options()` method in the `Helpers` class

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

- Check whether the sender is registered on the site

## [1.2.5] - 2025-03-24

### Changed

- Optimizations

### Fixed

- Hook calls of the Woo Subscriptions integration

## [1.2.2] - 2025-03-17

### Added

- Adding actions between existing actions in the flow
- Text formatting with WhatsApp variables
- English translation (en-US)
- Spanish translation (es-ES)

### Changed

- WooCommerce total variables, such as `{{ wc_order_total }}`, include the currency symbol
- Optimizations

### Removed

- "Order status" condition in the "New order" trigger

### Fixed

- Text variables in WooCommerce triggers were not replaced in test mode

## [1.2.0] - 2025-03-12

### Added

- "giggsey/libphonenumber-for-php" library for international phone numbers
- "Selectize" library for multi-selecting elements
- "Payment method", "Shipping method" and "Order paid" conditions
- "Payment processed by PayPal" trigger
- "Routines" class for scheduled routines
- Inter Pix, bank slip and cart recovery variables, such as `{{ fc_inter_pix_copia_cola }}` and `{{ fcrc_recovery_link }}`

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

- Debug mode
- Integration with Elementor forms
- WooCommerce text variables, such as `{{ wc_billing_first_name }}` and `{{ wc_checkout_field=[FIELD_ID] }}`
- Automatic updates
- "PHP Snippet" action in the flow builder
- "Discount coupon" action for the WooCommerce integration
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

- PHP 7.4 compatibility

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

[Unreleased]: https://github.com/meumouse/joinotify/compare/v2.7.2...HEAD
[2.7.2]: https://github.com/meumouse/joinotify/compare/v2.7.1...v2.7.2
[2.7.1]: https://github.com/meumouse/joinotify/compare/v2.7.0...v2.7.1
[2.7.0]: https://github.com/meumouse/joinotify/compare/v2.6.2...v2.7.0
[2.6.2]: https://github.com/meumouse/joinotify/compare/v2.6.1...v2.6.2
[2.6.1]: https://github.com/meumouse/joinotify/compare/v2.6.0...v2.6.1
[2.6.0]: https://github.com/meumouse/joinotify/compare/v2.5.1...v2.6.0
[2.5.1]: https://github.com/meumouse/joinotify/compare/v2.5.0...v2.5.1
[2.5.0]: https://github.com/meumouse/joinotify/compare/v2.4.2...v2.5.0
[2.4.2]: https://github.com/meumouse/joinotify/compare/v2.4.1...v2.4.2
[2.4.1]: https://github.com/meumouse/joinotify/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/meumouse/joinotify/compare/v2.3.4...v2.4.0
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

# Changelog

All notable changes to Joinotify are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Two notes on the history below. Releases before 2.0.0 did not strictly follow SemVer — features shipped as patch releases more than once (1.2.2 added two locales, 1.3.3 added triggers and placeholders, 1.4.5 and 1.4.7 added features) — so the numbering of that period cannot be read as a compatibility promise. And the entries before 2.0.0 were carried over from the original history, keeping the level of detail they had at the time.

## [Unreleased]

### Added

- `{{ wc_shipping_method_total }}` returns the shipping cost and method of the WooCommerce order as plain text, for example "R$ 20,00 via SEDEX", or only the method name when the shipping is free

### Changed

- A refused send now carries WhatsApp's own explanation: the builder test shows it after the description, and the debug log keeps it on every failed dispatch

### Fixed

- Template variables are sent as one line of plain text, as WhatsApp requires: line breaks become commas, tags and entities are removed and runs of spaces collapse, so a multi-line address no longer gets the template refused and a WooCommerce price no longer arrives as raw HTML
- Saving the test number and running the test announced a refused send in a success toast
- `{{ wc_shipping_address }}` was described as the shipping address, but it has always returned the shipping cost and method, so a template variable meant for the delivery address sent customers the price and carrier instead. Its value is unchanged, so existing messages send what they always sent, but it now arrives as plain text instead of WooCommerce price markup, and the builder describes it as the shipping cost and previews a cost instead of a street address. If you mapped it to an address, switch to `{{ wc_shipping_full_address }}`

## [2.4.1] - 2026-09-11

### Added

- The message history records the rendered text of each template send, with its language and variable values shown in the details modal
- While debug mode is on, every delivered message is logged with the `dispatch_sent` code
- New `Joinotify/Api/Template_Dispatch_Log` filter to redact template values before they are stored, while login codes are always masked
- The debug log viewer indents JSON context so nested details stay readable

### Fixed

- Templates with named variables (`{{nome}}`) were refused by WhatsApp because their values went out without `parameter_name`
- OTP and builder-test sends were recorded under the `api` source instead of `otp` and `test`
- The history type column showed the raw `template` slug instead of a translated label
- The setup wizard's country list opened behind the full-screen wizard shell
- The "Joinotify account" field showed an empty input after a key was saved instead of the key's masked prefix

### Removed

- The "Plugin updates" row in Settings → About, which only repeated what the plugin list already shows

## [2.4.0] - 2026-09-03

### Added

- Delivery retries are now configurable under Settings → General, with 5 attempts starting at 30 minutes by default and the option to not retry at all
- Pending resends can be cancelled from the history screen, which settles the rows under the new `cancelled` status
- The history table has an Error column with the human-readable failure reason

### Changed

- The setup wizard now uses the same flat panel and card styling as the rest of the admin screens

### Fixed

- `queued` history rows are now settled as `sent` or `failed` once their retry finishes
- A test message refused by WhatsApp was announced with a success toast
- Failed sends now explain the reason, such as the closed 24-hour window, in the test toasts and in the history instead of a generic error

## [2.3.4] - 2026-09-01

Three fixes for code that ran too late because `Core\Init` builds the plugin's classes from inside the `init`, `admin_init` and `wp_loaded` hooks.

### Fixed

- Any log written before `wp_loaded` crashed the site with a fatal `ValueError: Path cannot be empty`
- The `joinotify-workflow` post type was never registered, which let any Editor edit and delete workflows
- The WooCommerce HPOS compatibility declaration never reached WooCommerce, so the plugin was listed as incompatible

## [2.3.3] - 2026-08-28

Release tooling only, with no changes to the shipped package since 2.3.2.

### Added

- Local credentials and machine paths for the build and deploy scripts are read from a Git-ignored `.env`, documented in `.env.example`
- The WordPress.org directory artwork lives in `.wordpress-org/`, which the deploy mirrors into the SVN `assets/` directory

### Fixed

- The SVN deploy could corrupt its own working copy while creating a tag, failing with `E155033`

## [2.3.2] - 2026-08-23

### Added

- A "Source code and build" section in readme.txt, as required by the WordPress.org directory
- New `npm run lint:php`, which parses every source file with a PHP 8.1 binary
- New `Transport::is_ready()` method, which reports whether the site has a key to send with

### Changed

- The phone number library moves to `giggsey/libphonenumber-for-php-lite` 9.0.37, shrinking `admin/vendor` from 22 MB to 3 MB while PHP 8.1 stays the minimum
- The transport is no longer configurable and always resolves to the official API
- The build strips the emoji picker's CDN URL by targeting the library constant instead of the address

### Removed

- **Heads-up:** the legacy Evolution / slots-manager transport and its shared embedded API key are gone, so sites still using it stop sending until the Joinotify account is connected
- The `Joinotify/Transport/Active` filter, along with the transport choice

## [2.3.1] - 2026-08-21

### Added

- The builder warns when a step can only reach the contact within the 24-hour window, since only approved templates can start a conversation

### Changed

- WordPress 7.0 is now the minimum required version
- **Heads-up:** AI now uses the WordPress AI Client configured under Settings → Connectors, so the plugin's own OpenAI and Anthropic keys are no longer used
- The OTP login template is now picked from a list of approved authentication templates instead of typed
- Compiled translation files no longer ship in the package, since WordPress.org delivers them through translate.wordpress.org
- Adjustments requested in the WordPress.org review, such as enqueuing the builder styles, dropping inline JavaScript and undeclared external requests, and no longer deactivating the old "Joinotify OTP Login" plugin on its own

### Removed

- **Heads-up:** the builder's "PHP Snippet" action, which relied on `eval()`, so saved snippet steps are no longer executed
- The button that installed the cart recovery add-on from an external address

### Fixed

- Accounts with more than 100 message templates lost the remainder in the selectors
- Compliance issues reported by Plugin Check, such as unprepared queries, unescaped output and missing `translators:` comments
- The `.pot` generator picked up strings already removed from the code and dropped the `translators:` comments

## [2.3.0] - 2026-08-17

### Added

- WhatsApp connection through the official Joinotify API, with one-click account authorization and multiple numbers per account
- Meta-approved message templates, listed in the builder and sent through the new "WhatsApp: Template message" action
- New message types from the official API: reply buttons, option list, link button, location, contact card, sticker and reaction
- Real delivery confirmation, recording when each message was delivered, read or rejected
- A 6-step setup wizard, shown right after activation
- Anonymous usage data reporting, off by default, which never includes the site address, contacts, message content or credentials
- A settings modal on the WhatsApp card to connect, validate and remove the Joinotify API key, which is never sent to the browser
- New `Joinotify/Settings/Saved`, `Joinotify/Sender_Selected`, `Joinotify/Notification_Queue/Item_Retried` and `Joinotify/Debug_Log/Recorded` hooks for extensions

### Changed

- The plugin is now 100% free and open source under the GNU GPL v2 or later, with the licensing system removed
- Updates are now delivered by WordPress itself instead of the plugin's own update checker
- The OTP login code is now delivered through an authentication template on the official API
- Rate-limited sends are retried after exactly the wait time the API reports
- Group actions are unavailable on the official API, which does not support sending to groups
- The setup wizard is now displayed full screen, over the WordPress dashboard
- The workflow template library is now served by the Joinotify API at `api.joinotify.com`
- The Proxy API (deprecated) now ships turned off on new installations
- The builder no longer offers actions from disabled integrations
- Every WhatsApp action in the builder now displays the WhatsApp logo
- Compliance with the WordPress.org plugin directory guidelines, including declared external services and the distributed Vue source code

### Deprecated

- "Enable Proxy API" under Settings → General, which will be removed in an upcoming version

### Removed

- The extension installer that downloaded packages from an external address

### Fixed

- The plugin left its scheduled tasks behind on deactivation
- **Heads-up:** monetary values arrived broken in messages, and since `{{ wc_order_total }}` and the other total variables now include the currency symbol, flows that prefix them with `{{ wc_currency_symbol }}` must drop it

## [2.2.0] - 2026-08-03

### Added

- "Loop" action in the flow builder, which runs its body actions once per item of a collection such as the order's digital files
- New loop variables for the loop body, such as `{{ loop_value }}`, `{{ loop_index }}` and `{{ loop_file_name }}`

### Removed

- Notify when WhatsApp disconnects

### Fixed

- The default country code was not applied to numbers without one, making the send fail

## [2.1.0] - 2026-07-27

### Added

- WooCommerce digital product delivery straight from the flow builder, sending files and PDFs by e-mail or WhatsApp
- Attachments in the e-mail (Resend) and WhatsApp media actions, accepting media library files, links or the order's digital files
- New WooCommerce triggers "Digital product access granted" and "Digital file downloaded", both with an optional per-product filter
- New WooCommerce variables for digital products, such as file names, download links, expiration date and remaining downloads

### Changed

- Variables now respect the chosen trigger at send time as well
- Groundwork for the new license server, with automatic migration and no user intervention

### Fixed

- Argument order in the WooCommerce partial refund trigger, which made the order variable receive the refund identifier
- An expired or server-rejected license kept the premium features unlocked for up to 24 hours
- Opening the license screen could deactivate the site's license when the expiration date had already passed
- Lifetime licenses marked as "Unlimited" or "Lifetime" were treated as expired
- The license activation response could be reused on deactivation, since both shared the same cache

## [2.0.0] - 2026-07-02

### Added

- Artificial Intelligence in the flow builder, creating flows, messages, variables and PHP snippets from a text description
- Custom text variables created from the site's content types and fields, including WooCommerce orders
- Sent message history, with filters and a month-and-year date picker
- Passwordless login by code (OTP) sent via WhatsApp
- Export and import all plugin settings in a JSON file
- "Password reset request" trigger, with a reset link variable
- New languages added: French, Italian, German and European Portuguese

### Changed

- New visual canvas flow builder, with drag and drop, step connections, zoom and undo/redo
- New message editor with visual formatting automatically converted to the WhatsApp standard
- Media preview right in the WhatsApp media message step
- Text variables highlighted and clickable inside the fields, with a warning when unavailable in the chosen trigger
- Actions flag when there are required settings pending
- A more complete condition catalog, with list-based selection and a product picker in the values
- Message scheduling with a specific date and time, with a queue and reprocessing of failed notifications
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

- WooCommerce full address format for billing and shipping

### Changed

- Optimizations

### Fixed

- Fatal error loading the "All flows" page, caused by the undefined `convert_to_screen()` function

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
- Post text variables, such as `{{ post_title }}`, `{{ post_link }}` and `{{ post_featured_image }}`

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

- Fatal error in WooCommerce's `OrdersTableDataStore` when changing order status

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
- Flexify Checkout abandoned cart text variables, such as `{{ fcrc_first_name }}`, `{{ fcrc_phone }}` and `{{ fcrc_cart_total }}`
- Triggers "Lead captured via modal" and "Lead captured via checkout" (Flexify Checkout - Abandoned cart recovery)

### Removed

- The step 1, 2 and 3 entry triggers of the Flexify Checkout integration

## [1.3.2] - 2025-05-29

### Changed

- Fill in the sender when importing a flow

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

- WooCommerce total variables, such as `{{ wc_order_total }}`, now return formatted values with the currency symbol
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
- "Routines" class for scheduled routines, starting with a phone connection check
- Text variables for Inter Pix, bank slip and cart recovery, such as `{{ fc_inter_pix_copia_cola }}` and `{{ fcrc_recovery_link }}`

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
- WooCommerce text variables for billing, shipping, order status, totals, coupons and checkout fields, such as `{{ wc_billing_first_name }}` and `{{ wc_checkout_field=[FIELD_ID] }}`
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

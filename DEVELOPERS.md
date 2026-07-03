# Joinotify — Developer Extension API

Joinotify is **extensible end‑to‑end with PHP only**. Every part of the system — actions,
categories, triggers, integrations, conditions, placeholders, settings and REST endpoints — can be
extended by third‑party plugins through WordPress filters, with **no core edits and no JavaScript**.

There are two equivalent ways to extend it:

1. **Facade helpers** (recommended) — global `joinotify_register_*()` functions (and the
   `MeuMouse\Joinotify\Api\Extensions` class). Clean, discoverable, documented here.
2. **Raw filters** — the underlying `apply_filters()` hooks. The facade is just sugar over these,
   so you can always drop down to a filter when you need full control.

> All helpers are loaded with the plugin (`admin/src/Core/Functions.php`). Register your
> extensions on a normal hook such as `plugins_loaded` or `init`. A complete, runnable example
> lives in [`examples/joinotify-extension-example.php`](examples/joinotify-extension-example.php).

---

## Reference

| Facade helper | Underlying filter | Shape |
|---|---|---|
| `joinotify_register_action_category($cat)` | `Joinotify/Builder/Action_Categories` | `{id, label, icon?, priority?}` |
| `joinotify_register_action($def)` | `Joinotify/Builder/Actions` | action definition (see below) |
| `joinotify_register_action_handler($slug, $cb)` | `Joinotify/Workflow_Processor/Handle_Actions` | `cb($action_data, $action, $post_id, $event_data): bool` |
| `joinotify_register_action_description($slug, $cb)` | `Joinotify/Builder/Action_Description` | `cb($action_data, $workflow_action): string` |
| `joinotify_register_integration($int)` | `Joinotify/Settings/Tabs/Integrations` | `{slug, title, description, icon, setting_key?, ...}` |
| `joinotify_register_trigger($context, $trigger)` | `Joinotify/Builder/Get_All_Triggers` | `{data_trigger, title, description, require_settings?, settings?}` |
| `joinotify_dispatch_trigger($hook, $integration, $payload)` | `Workflow_Processor::process_workflows()` | runtime dispatch |
| `joinotify_register_conditions($trigger, $conds)` | `Joinotify/Validations/Get_Action_Conditions` | `{cond_key: {title, description}}` |
| `joinotify_register_condition_operators($type, $ops)` | `Joinotify/Conditions/Check_Condition_Type` | `['is','is_not',...]` |
| `joinotify_register_condition_value($type, $cb)` | `Joinotify/Conditions/Get_Compare_Value` | `cb($value_map, $type, $payload): mixed` |
| `joinotify_register_placeholders($integration, $ph)` | `Joinotify/Builder/Placeholders_List` | `{'{{ x }}': {triggers, description, replacement}}` |
| `joinotify_register_dynamic_placeholder($pattern, $cb)` | `Joinotify/Builder/Resolve_Dynamic_Token` | `cb($matches, $payload): string\|null` |
| `joinotify_register_settings_tab($tab)` | `Joinotify/Admin/Settings/Section_Tabs` | `{id, name, icon, section}` |
| `joinotify_register_settings_section($section)` | `Joinotify/Admin/Settings/Schema` | `{id, title, layout?, cards}` |
| `joinotify_register_rest_route($route)` | `Joinotify/Rest/Routes` | `{route, methods?, callback, permission?, args?}` |

Other useful filters: `Joinotify/Builder/Action_Settings_Schema` (custom/overridden action settings
schema), `Joinotify/Builder/Action_Default_Data` (custom/overridden action default data),
`Joinotify/Workflow_Processor/Delaying_Actions` and `Joinotify/Workflow_Processor/Branching_Actions`
(register structural — delay/branch — actions), `Joinotify/Builder/Trigger_Context_Icons`,
`Joinotify/Download_Template/Fill_Sender_Actions`, `Joinotify/Init/{Init,Admin_Init,WP_Loaded}_Classes`.

---

## Actions

An action is a step a user can drop on the workflow canvas. A fully data‑driven custom action needs
**four** things (all PHP): a **category** (tab), the **definition** (catalog + default data +
settings schema), a **runtime handler**, and a **canvas description**.

```php
// 1) A category = a tab in the "Add an action" modal.
// Optional: you can skip this call and pass `category_label`/`category_icon` to
// joinotify_register_action() below to have the tab auto-created (deduped by id).
joinotify_register_action_category([
    'id'       => 'my_app',
    'label'    => __( 'My App', 'my-textdomain' ),
    'icon'     => '<svg ...>...</svg>', // monochrome, fill="currentColor"
    'priority' => 40,
]);

// 2) The action. `settings_schema` drives the settings UI with NO JavaScript.
joinotify_register_action([
    'action'        => 'my_app_send_sms',
    'title'         => __( 'My App: Send SMS', 'my-textdomain' ),
    'description'   => __( 'Send an SMS through My App.', 'my-textdomain' ),
    'category'      => 'my_app',
    'icon'          => '<svg ...>...</svg>',
    'has_settings'  => true,
    'is_expansible' => true,   // can have actions chained after it
    'priority'      => 70,     // sort order inside the tab (lower first)
    'context'       => [],     // [] = available for every trigger; or ['woocommerce']
    'default_data'  => [
        'action'  => 'my_app_send_sms',
        'title'   => __( 'My App: Send SMS', 'my-textdomain' ),
        'to'      => '{{ wc_billing_phone }}',
        'message' => '',
    ],
    'settings_schema' => [
        [ 'key' => 'to',      'label' => __( 'Recipient', 'my-textdomain' ), 'component' => 'input',    'required' => true ],
        [ 'key' => 'message', 'label' => __( 'Message', 'my-textdomain' ),   'component' => 'textarea', 'required' => true, 'rows' => 4 ],
    ],

    // Convenience keys (auto-wired to their own filters):
    'fill_sender'     => false, // set true to auto-fill a WhatsApp sender on template import
    'category_label'  => __( 'My App', 'my-textdomain' ), // when set, the category tab is auto-created
    'category_icon'   => '<svg ...>...</svg>',            // optional icon for the auto-created tab
    // 'category_priority' => 40,                          // optional sort order for the auto-created tab

    // 3) Runtime handler — runs when the workflow reaches this action.
    'handler' => function( $action_data, $action, $post_id, $event_data ) {
        $to      = MeuMouse\Joinotify\Builder\Placeholders::replace_placeholders( $action_data['to'] ?? '', $event_data );
        $message = MeuMouse\Joinotify\Builder\Placeholders::replace_placeholders( $action_data['message'] ?? '', $event_data );
        // ... call your SMS gateway here ...
        return true; // return bool
    },
]);
```

> **4) Canvas description.** Because the array key `description` is already used for the action's
> static description text, register the **dynamic** node description (shown under the title on the
> canvas) separately:

```php
joinotify_register_action_description( 'my_app_send_sms', function( $data, $workflow_action ) {
    return esc_html( sprintf( __( 'SMS to %s', 'my-textdomain' ), $data['to'] ?? '' ) );
});
```

**Settings field components** (`settings_schema[].component`): `input`, `textarea`, `number`,
`select` (with `options: [{label, value}]`), `date`, `time`, `code`, `switch`, plus nested `group`
and `repeater`. Common keys: `key`, `label`, `component`, `required`, `placeholder`, `options`,
`rows`, `description`, `componentProps`, and `condition` (an array of `{key, value, operator}` for
conditional visibility — operators: `eq`, `neq`, `in`, `not_in`, `truthy`, `falsy`).

**How the settings form is rendered (no JavaScript).** When your action has **no** registered Vue
settings component, Joinotify renders your `settings_schema` **generically** — the exact same
schema-driven renderer the trigger settings use. So a standard action needs only PHP: declare
`settings_schema` and the fields appear in the node settings drawer, bound to flat top-level keys of
the action data (e.g. `to`, `message`). No frontend build required.

> **Advanced (optional).** A built-in-quality, bespoke settings UI (custom widgets, live previews)
> can still ship a Vue `settings_component`. When present it takes precedence over the schema; when
> absent the schema fallback above is used. This is opt-in and only needed for non-standard controls —
> see [Custom field components](#custom-field-components).

**Schema/defaults precedence.** For every action, an inline `settings_schema` / `default_data` on the
catalog entry is the single source of truth and can even **override a built-in action's** schema or
defaults (via the `Joinotify/Builder/Actions`, `Joinotify/Builder/Action_Settings_Schema` and
`Joinotify/Builder/Action_Default_Data` filters). Built-in hardcoded values remain only as a fallback.

### Structural actions (delay / branch)

Leaf actions run through the handler map above. The two **structural** behaviors — pausing the funnel
(delay) and splicing a branch (condition) — are dispatched by capability lists so third parties can add
their own. Register your slug on the relevant filter; it then reuses the same delay-resolution /
condition-evaluation data shape as the built-ins:

```php
// A custom delaying action (reuses delay_type/delay_value/delay_period/date_value/time_value data).
add_filter( 'Joinotify/Workflow_Processor/Delaying_Actions', function( $slugs ) {
    $slugs[] = 'my_app_wait';

    return $slugs;
});

// A custom branching action (reuses the condition-evaluation data shape).
add_filter( 'Joinotify/Workflow_Processor/Branching_Actions', function( $slugs ) {
    $slugs[] = 'my_app_switch';

    return $slugs;
});
```

---

## Triggers & integrations

A **trigger** starts a workflow. Triggers are grouped by **context** (an integration slug). For the
context to appear in the builder, register an **integration card** (Settings → Integrations) with a
matching `slug`, and make sure its `setting_key` option is enabled.

```php
joinotify_register_integration([
    'slug'        => 'my_app',
    'title'       => __( 'My App', 'my-textdomain' ),
    'description' => __( 'Automate messages for My App events.', 'my-textdomain' ),
    'icon'        => '<svg ...>...</svg>',
    'setting_key' => 'enable_my_app_integration', // option that toggles the context on/off
]);

joinotify_register_trigger( 'my_app', [
    'data_trigger'     => 'my_app_order_paid',
    'title'            => __( 'My App: order paid', 'my-textdomain' ),
    'description'      => __( 'Fires when a My App order is paid.', 'my-textdomain' ),
    'require_settings' => false,
]);

// Fire it at runtime from your own hook:
add_action( 'my_app_order_paid', function( $order_id ) {
    joinotify_dispatch_trigger( 'my_app_order_paid', 'my_app', [ 'order_id' => $order_id ] );
});
```

The `$payload` you pass to `joinotify_dispatch_trigger()` is available to placeholders and
conditions at send time (keys like `order_id`, `user_id`, `fields`, ...).

---

## Conditions

Conditions let a `condition` action branch the workflow. Register which conditions a trigger
exposes, the operators each condition allows, and how to resolve its value from the payload.

```php
joinotify_register_conditions( 'my_app_order_paid', [
    'my_app_plan' => [
        'title'       => __( 'Subscription plan', 'my-textdomain' ),
        'description' => __( 'Check the customer plan.', 'my-textdomain' ),
    ],
]);

joinotify_register_condition_operators( 'my_app_plan', [ 'is', 'is_not' ] );

joinotify_register_condition_value( 'my_app_plan', function( $value_map, $type, $payload ) {
    return get_post_meta( $payload['order_id'] ?? 0, '_my_app_plan', true );
});
```

---

## Placeholders

Placeholders (`{{ name }}`) are replaced inside messages. Each has a `sandbox` value (shown in the
builder preview) and a `production` value (used at send time).

```php
joinotify_register_placeholders( 'my_app', [
    '{{ my_app_plan }}' => [
        'triggers'    => [ 'my_app_order_paid' ], // [] = all triggers of this integration
        'description' => __( 'The customer plan name.', 'my-textdomain' ),
        'replacement' => [
            'sandbox'    => __( 'Premium', 'my-textdomain' ),
            'production' => get_post_meta( get_the_ID(), '_my_app_plan', true ),
        ],
    ],
]);
```

> The `production` value may also be a **callable** `fn( $payload )` resolved at send time.
> Tokens are matched whitespace-tolerantly, so `{{ my_app_plan }}` and `{{my_app_plan}}` both resolve.

### Parametric (bracket-syntax) tokens

Static `{{ name }}` tokens cover most cases. For tokens that carry an **argument** — e.g.
`{{ my_app_field=[order_total] }}` or `{{ my_app_meta[plan] }}` — register a **resolver** with a PCRE
pattern. The callback receives the `preg_match` result and the runtime payload, and returns the
replacement (or `null` to leave the token untouched):

```php
joinotify_register_dynamic_placeholder( '/\{\{\s*my_app_field=\[(.+?)\]\s*\}\}/', function( $matches, $payload ) {
    $field_id = $matches[1]; // first capture group

    return $payload['fields'][ $field_id ] ?? null; // null keeps the original token
});
```

Registered resolvers run **before** the built-in bracket handlers (`{{ field_id=[...] }}`,
`{{ wc_checkout_field=[...] }}`, `{{ user_meta[...] }}`), so you can also override those for your own
context. The built-ins remain as a backward-compatible fallback.

---

## Settings

Add your own settings tab + page (cards/fields) to the plugin's Settings screen.

```php
joinotify_register_settings_tab([
    'id'      => 'my_app',
    'name'    => __( 'My App', 'my-textdomain' ),
    'icon'    => '<svg class="joinotify-tab-icon" ...>...</svg>',
    'section' => 'my_app',
]);

joinotify_register_settings_section([
    'id'     => 'my_app',
    'title'  => __( 'My App', 'my-textdomain' ),
    'layout' => 'cards',
    'cards'  => [[
        'id'     => 'my_app-credentials',
        'title'  => __( 'Credentials', 'my-textdomain' ),
        'fields' => [
            [ 'type' => 'text', 'key' => 'my_app_api_key', 'label' => __( 'API key', 'my-textdomain' ) ],
        ],
    ]],
]);
```

**Standard field types (no JavaScript):** `toggle`, `text`, `textarea`, `richtext`, `select`,
`phone`, `color`, `color-scale`, `input-group`, `input-button`. Integration cards (Settings →
Integrations) are declared the same way through `joinotify_register_integration()` (see
[Triggers & integrations](#triggers--integrations)); a card with these fields and an HTML `modal` is
fully configurable from PHP alone. Toggle keys declared only in your schema (not in the plugin's
default options) are still reset correctly to `'no'` when unchecked.

### Custom field components

Standard fields need no JavaScript. If your app needs a **custom control** (an OAuth "Connect"
widget, a provider-specific picker, a bespoke modal block), you can ship a Vue component and register
it in the global field registry — then reference it from PHP by `component` name. The registry is
race-safe: it exposes `window.JoinotifyFieldComponents` and fires a `joinotify:field-registry-ready`
event once it is ready (and sets `.ready = true` for listeners that attach later).

```js
// my-app-fields.js — enqueue this AFTER the Joinotify settings bundle.
function registerMyAppFields( api ) {
    api.register( 'my-app-connect', MyAppConnectComponent ); // Vue component
}

if ( window.JoinotifyFieldComponents && window.JoinotifyFieldComponents.ready ) {
    registerMyAppFields( window.JoinotifyFieldComponents );
} else {
    window.addEventListener( 'joinotify:field-registry-ready', ( event ) => registerMyAppFields( event.detail ) );
}
```

```php
// Reference it from a settings field or an integration modal block:
[ 'type' => 'my-app-connect', 'key' => 'my_app_connection', 'label' => __( 'Connection', 'my-textdomain' ) ]
```

If a referenced `component` is not registered, the field degrades gracefully (a plain text field /
"custom block not available" notice) instead of breaking the page.

---

## REST endpoints

Register a route under the plugin namespace (`joinotify/v1`) without authoring a class.

```php
joinotify_register_rest_route([
    'route'      => '/admin/my-app/ping',
    'methods'    => 'GET',
    'callback'   => function ( WP_REST_Request $request ) {
        return rest_ensure_response( [ 'status' => 'success', 'pong' => true ] );
    },
    // 'permission' => fn() => current_user_can( 'manage_options' ), // default
]);
// → GET /wp-json/joinotify/v1/admin/my-app/ping
```

---

## Runtime helpers

Besides the `joinotify_register_*()` registration facade, Joinotify ships read/resolve helpers you
can call anywhere at runtime (inside action handlers, your own hooks, REST callbacks, …). They are
thin, documented wrappers over the internal classes, so you never have to couple to namespaced code.

| Helper | Wraps | Returns |
|---|---|---|
| `joinotify_replace_placeholders($message, $payload, $mode)` | `Placeholders::replace_placeholders` | `string` |
| `joinotify_prepare_message($message, $payload)` | `Placeholders` + `{{ ai:NAME }}` | `string` |
| `joinotify_convert_html_to_whatsapp($message)` | rich text HTML → WhatsApp markdown | `string` |
| `joinotify_prepare_receiver($receiver, $payload)` | placeholders + phone format | `string` (digits) |
| `joinotify_get_placeholders($integration, $trigger, $context)` | `Placeholders::get_placeholders_list` | `array` |
| `joinotify_get_workflows($args)` | `get_posts()` (post type locked) | `WP_Post[]`/`int[]` |
| `joinotify_get_workflow_content($post_id)` | `Helpers::get_workflow_content_meta` | `array` |
| `joinotify_get_workflow_context($post_id)` | `Utils::get_context_from_post` | `string\|null` |
| `joinotify_find_workflow_item($content, $item_id)` | `Utils::find_workflow_item_by_id` | `array\|null` |
| `joinotify_workflow_has_content($post_id, $type)` | `Utils::check_workflow_content` | `bool` |
| `joinotify_get_senders()` | `Phone_Manager::get_senders` | `array` |
| `joinotify_is_valid_sender($sender)` | `Helpers::allowed_sender` | `bool` |
| `joinotify_format_phone($phone)` | `Helpers::validate_and_format_phone` | `string` |
| `joinotify_get_first_sender()` | first connected sender | `string\|null` |
| `joinotify_get_setting($key)` | `Admin::get_setting` | `mixed` (false if unset) |
| `joinotify_get_message_history($args)` | `Message_History::get_items` | `array` |
| `joinotify_send_whatsapp_message_text($sender, $receiver, $message, $delay)` | `Controller::send_message_text` | `int` |
| `joinotify_send_whatsapp_message_media($sender, $receiver, $media_type, $media, $caption, $delay)` | `Controller::send_message_media` | `int` |

```php
// Resolve {{ ... }} tokens against a payload (eg: inside a custom action handler).
$text = joinotify_replace_placeholders( '{{ wc_billing_first_name }}, your order is ready!', $event_data );

// Send a WhatsApp message from the first connected sender.
$sender = joinotify_get_first_sender();

if ( $sender && joinotify_is_valid_sender( $sender ) ) {
    joinotify_send_whatsapp_message_text( $sender, joinotify_format_phone( '11999998888' ), $text );
}

// Introspect workflows: find every WooCommerce workflow that has at least one action.
foreach ( joinotify_get_workflows( array( 'fields' => 'ids' ) ) as $id ) {
    if ( joinotify_get_workflow_context( $id ) === 'woocommerce' && joinotify_workflow_has_content( $id, 'action' ) ) {
        // ... do something with $id ...
    }
}
```

---

## OTP login delivery channels

The passwordless login feature delivers verification codes through a pluggable
**channel** layer. WhatsApp ships by default; you can add e‑mail, Telegram, SMS
or any other transport by implementing `Channel_Interface` and registering it on
the `Joinotify/Otp_Login/Channels` filter — no core edits, no JavaScript. The
channel you register becomes selectable in the OTP Login integration modal
("Delivery channel").

```php
use MeuMouse\Joinotify\Otp_Login\Channel_Interface;
use MeuMouse\Joinotify\Otp_Login\Otp_Message;

class My_Email_Channel implements Channel_Interface {

    public function get_id() {
        return 'email';
    }

    public function get_label() {
        return __( 'E-mail', 'my-textdomain' );
    }

    public function is_configured() {
        // Everything an e-mail send needs is always available.
        return true;
    }

    public function supports( Otp_Message $message ) {
        // This channel needs a destination e-mail address.
        return '' !== trim( (string) $message->email );
    }

    public function send( Otp_Message $message ) {
        $sent = wp_mail(
            $message->email,
            __( 'Your verification code', 'my-textdomain' ),
            $message->body
        );

        return $sent ? true : new WP_Error( 'email_failed', __( 'Could not send the e-mail.', 'my-textdomain' ) );
    }
}

add_filter( 'Joinotify/Otp_Login/Channels', function( $channels ) {
    $channels['email'] = My_Email_Channel::class;

    return $channels;
});
```

`Otp_Message` carries everything a channel needs: `code`, `phone`, `email`,
`user` (the resolved `WP_User`, when any), `expiry_seconds`, the already‑composed
`body`, and a `context` array. The active channel is chosen by the
`otp_login_channel` setting and resolved through `Channel_Manager::send()`, which
validates `is_configured()`/`supports()`, dispatches, and logs failures.

**Related filters:** `Joinotify/Otp_Login/Message` (code message body),
`Joinotify/Otp_Login/Otp_Length`, `Joinotify/Otp_Login/Otp_Expiry_Time`,
`Joinotify/Otp_Login/Max_Attempts`, `Joinotify/Otp_Login/Sender` (WhatsApp sender),
`Joinotify/Otp_Login/Phone_Meta_Keys` (account lookup), and the
`Joinotify/Otp_Login/Code_Sent` action (fired after a successful delivery).

---

## Notification delivery channels

Workflow notifications are delivered through a pluggable **channel** layer
(`MeuMouse\Joinotify\Notifications`). WhatsApp (Evolution/slots proxy) ships by
default; you can add WhatsApp Official (Cloud API), Telegram, e‑mail, SMS or any
other transport by implementing `Channel_Interface` and registering it on the
`Joinotify/Notifications/Channels` filter — no core edits, no JavaScript.

This is the general-purpose sibling of the OTP channel layer above: OTP channels
deliver login codes (`Otp_Message`), notification channels deliver arbitrary
workflow messages (`Notification_Message`).

```php
use MeuMouse\Joinotify\Notifications\Channel_Interface;
use MeuMouse\Joinotify\Notifications\Notification_Message;
use MeuMouse\Joinotify\Notifications\Channel_Result;

class My_Telegram_Channel implements Channel_Interface {

    public function get_id() {
        return 'telegram';
    }

    public function get_label() {
        return __( 'Telegram', 'my-textdomain' );
    }

    public function is_configured() {
        return '' !== get_option( 'my_telegram_bot_token', '' );
    }

    public function get_capabilities() {
        return array( 'text', 'media' );
    }

    public function supports( Notification_Message $message ) {
        // Telegram needs a chat id (carried in meta) and a supported type.
        return '' !== (string) $message->get_meta( 'chat_id' )
            && in_array( $message->type, $this->get_capabilities(), true );
    }

    public function send( Notification_Message $message ) {
        $ok = my_telegram_send( $message->get_meta( 'chat_id' ), $message->content );

        return $ok
            ? Channel_Result::success( $this->get_id() )
            : Channel_Result::failure( $this->get_id(), 'telegram_failed', true );
    }
}

// 1 class + 1 filter line — that's it.
add_filter( 'Joinotify/Notifications/Channels', function( $channels ) {
    $channels['telegram'] = My_Telegram_Channel::class;

    return $channels;
});

// or, with the global helper:
joinotify_register_notification_channel( 'telegram', My_Telegram_Channel::class );
```

`Notification_Message` carries everything a channel needs: `channel`, `type`
(`text|media|audio`), `sender`, `receiver`, `content`, `media_type`, `media_url`,
`caption`, `delay`, a `context` array (e.g. `source`, `workflow_id`) and a
free‑form `meta` array for service‑specific fields (e‑mail subject, Telegram
`chat_id`, template params, ...). Read meta with `$message->get_meta( $key )`.

Send a notification through the layer with the global helper, which returns a
normalized `Channel_Result` (`is_success()`, `response_code`, `retryable`,
`queued`, `error`):

```php
$result = joinotify_dispatch_notification( array(
    'channel'  => 'telegram',  // omit to use the default channel (WhatsApp)
    'type'     => 'text',
    'receiver' => '123456',
    'content'  => 'Hello!',
    'meta'     => array( 'chat_id' => '123456' ),
) );

if ( $result->is_success() ) { /* ... */ }
```

`Channel_Manager::dispatch()` resolves the target channel (from the message or
the `Joinotify/Notifications/Default_Channel` filter), validates
`is_configured()`/`supports()`, dispatches, and logs failures. The default
WhatsApp channel delegates to `Api\Controller`, so its retry queue and message
history are preserved unchanged.

**Related filters/actions:** `Joinotify/Notifications/Channels` (registry),
`Joinotify/Notifications/Default_Channel` (fallback channel id),
`Joinotify/Notifications/Message_Before_Send` (mutate the message before send),
and the `Joinotify/Notifications/Message_Sent` action (fired after every
dispatch attempt, success or failure).

---

## Notes & gotchas

- **Register early.** Hook your registrations on `plugins_loaded`/`init` so the filters are wired
  before the builder bootstrap and REST catalog are built.
- **Trigger context visibility.** A trigger context only shows up when its integration card is
  registered **and enabled** (its `setting_key` option is `'yes'`).
- **Handler return value.** Action handlers must return `bool`. Returning a non‑bool may stop the
  funnel unexpectedly.
- **Placeholder replacement.** Use `joinotify_replace_placeholders( $text, $payload )` (or the
  underlying `MeuMouse\Joinotify\Builder\Placeholders::replace_placeholders()`) inside your handler
  to resolve `{{ ... }}` tokens. See the [Runtime helpers](#runtime-helpers) table for the full set.
- **No JavaScript required for standard cases.** The action library modal renders your category tab,
  and the node settings drawer renders your `settings_schema` fields generically (the same renderer
  the triggers use) — you never touch the Vue frontend. A bespoke Vue `settings_component` (actions)
  or a [custom field component](#custom-field-components) (settings/integrations) is **optional** and
  only needed for non-standard widgets.
- **Backward compatible by design.** Every new extension point is additive: built-in schemas,
  defaults, structural (delay/branch) slugs and bracket-token handlers all still work exactly as
  before and act as the fallback when you don't override them.

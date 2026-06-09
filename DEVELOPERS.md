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
| `joinotify_register_settings_tab($tab)` | `Joinotify/Admin/Settings/Section_Tabs` | `{id, name, icon, section}` |
| `joinotify_register_settings_section($section)` | `Joinotify/Admin/Settings/Schema` | `{id, title, layout?, cards}` |
| `joinotify_register_rest_route($route)` | `Joinotify/Rest/Routes` | `{route, methods?, callback, permission?, args?}` |

Other useful filters: `Joinotify/Builder/Action_Settings_Schema` (custom action settings schema
fallback), `Joinotify/Builder/Trigger_Context_Icons`, `Joinotify/Download_Template/Fill_Sender_Actions`,
`Joinotify/Init/{Init,Admin_Init,WP_Loaded}_Classes`.

---

## Actions

An action is a step a user can drop on the workflow canvas. A fully data‑driven custom action needs
**four** things (all PHP): a **category** (tab), the **definition** (catalog + default data +
settings schema), a **runtime handler**, and a **canvas description**.

```php
// 1) A category = a tab in the "Add an action" modal.
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
    'fill_sender' => false, // set true to auto-fill a WhatsApp sender on template import

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
`select` (with `options: [{label, value}]`), `date`, `time`, `code`. Common keys: `key`, `label`,
`component`, `required`, `placeholder`, `options`, `rows`, `description`, `componentProps`.

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
- **No JavaScript required.** The action library modal renders your category tab and your
  `settings_schema` fields automatically — you never touch the Vue frontend.

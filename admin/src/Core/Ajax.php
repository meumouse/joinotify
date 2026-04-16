<?php

namespace MeuMouse\Joinotify\Core;

defined('ABSPATH') || exit;

/**
 * AJAX dispatcher — kept as a compatibility stub.
 *
 * All AJAX logic has been moved to domain-specific classes:
 *
 *   - Ajax_License   — license activate / deactivate / sync
 *   - Ajax_Phones    — phone sender registration and management
 *   - Ajax_Workflows — workflow create / edit / export / import / test
 *   - Ajax_Settings  — settings save / reset / modules / misc
 *   - Ajax_Debug     — debug log read / clear / download
 *
 * Those classes are instantiated directly by Init so this class
 * no longer needs to register any wp_ajax_* actions.
 *
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Ajax {
    // Intentionally empty — all AJAX handlers live in the Ajax_* domain classes.
}

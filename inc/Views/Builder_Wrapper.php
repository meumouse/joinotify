<?php

use MeuMouse\Joinotify\Admin\Admin;

/**
 * Template file for load builder files
 * 
 * @since 1.0.0
 * @version 1.2.0
 * @package MeuMouse.com
 */

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="joinotify-automations-builder" class="wrapper canvas-dot">
    <?php $builder_files = apply_filters( 'Joinotify/Builder/Load_Files', array(
        'Loader.php',
        'Navbar.php',
        'Triggers.php',
        'Actions.php',
        'Funnel.php',
        'Choose_Template.php',
    ));

    foreach ( $builder_files as $file ) :
        include_once JOINOTIFY_INC . 'Views/Builder/' . $file;
    endforeach; ?>
</div>
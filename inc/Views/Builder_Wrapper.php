<?php

use MeuMouse\Joinotify\Admin\Admin;

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

<?php if ( Admin::get_setting('enable_visual_tour') === 'yes' ) : ?>
    <script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>

    <?php wp_enqueue_script( 'joinotify-driverjs-init', JOINOTIFY_ASSETS . 'vendor/driverjs/init-driverjs.js', array('jquery'), JOINOTIFY_VERSION ); ?>
<?php endif; ?>
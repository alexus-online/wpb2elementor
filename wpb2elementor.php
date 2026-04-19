<?php
/**
 * Plugin Name: WPB2Elementor
 * Description: Convert WPBakery shortcodes to Elementor JSON.
 * Version: 1.0.0
 * Author: Alexander Kaiser
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WPB2EL_VERSION', '1.0.0' );
define( 'WPB2EL_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPB2EL_URL', plugin_dir_url( __FILE__ ) );

require_once WPB2EL_PATH . 'includes/class-parser.php';
require_once WPB2EL_PATH . 'includes/class-mapper.php';
require_once WPB2EL_PATH . 'includes/class-converter.php';
require_once WPB2EL_PATH . 'includes/class-claude-api.php';
require_once WPB2EL_PATH . 'includes/class-prompt-export.php';
require_once WPB2EL_PATH . 'includes/class-admin-ui.php';

add_action( 'plugins_loaded', function() {
    new WPB2EL_Admin_UI();
} );

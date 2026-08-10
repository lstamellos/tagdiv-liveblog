<?php
/**
 * Plugin Name:       tagDiv Liveblog
 * Plugin URI:        https://github.com/lstamellos/tagdiv-liveblog
 * Update URI:        https://github.com/lstamellos/tagdiv-liveblog
 * Description:       Integrates Automattic Liveblog with Newspaper/tagDiv Composer as a native configurable block.
 * Version:           0.1.17
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Loukas Stamellos
 * License:           GPL-2.0-or-later
 * Text Domain:       tagdiv-liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAGDIV_LIVEBLOG_VERSION', '0.1.17' );
define( 'TAGDIV_LIVEBLOG_FILE', __FILE__ );
define( 'TAGDIV_LIVEBLOG_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAGDIV_LIVEBLOG_URL', plugin_dir_url( __FILE__ ) );

require_once TAGDIV_LIVEBLOG_PATH . 'includes/class-tagdiv-liveblog.php';
require_once TAGDIV_LIVEBLOG_PATH . 'includes/class-tagdiv-liveblog-pagination.php';
require_once TAGDIV_LIVEBLOG_PATH . 'includes/class-tagdiv-liveblog-updater.php';

Tagdiv_Liveblog_Plugin::init();
Tagdiv_Liveblog_Pagination::init();
Tagdiv_Liveblog_Updater::init();

<?php
/**
 * @wordpress-plugin
 *
 * Plugin Name:       Insider for Gravity Forms
 * Plugin URI:        https://github.com/jeffersonrucu/wp-gf-insider
 * Description:       Sends Gravity Forms submissions to Insider through the upsert API, mapped per form in the panel, and prints the Insider tag.
 * Version:           1.1.4
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Requires Plugins:  gravityforms
 * Author:            Jefferson Oliveira
 * Author URI:        https://github.com/jeffersonrucu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gf-insider
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/config.php';

require_once GF_INSIDER_DIR . 'includes/class-gf-insider-payload.php';
require_once GF_INSIDER_DIR . 'includes/class-gf-insider-tag.php';

/**
 * The add-on class extends a Gravity Forms base class, so it can only be
 * loaded once the plugin has published the feed framework.
 */
add_action(
	'gform_loaded',
	static function (): void {
		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		GFForms::include_feed_addon_framework();

		require_once GF_INSIDER_DIR . 'includes/class-gf-insider-addon.php';

		GFAddOn::register( 'GF_Insider_Addon' );
	},
	5
);

add_action( 'plugins_loaded', array( 'GF_Insider_Tag', 'init' ) );

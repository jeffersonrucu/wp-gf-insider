<?php
/**
 * The Insider tag in the head, kept out of the cache plugins' minification.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

final class GF_Insider_Tag {

	const EXCLUSIONS = array( 'useinsider.com' );

	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'render' ), 1 );

		// Minification would rewrite the src to a local copy and freeze the SDK.
		// Delaying it is left to the cache plugin: the send goes through the server.
		add_filter( 'rocket_minify_excluded_external_js', array( __CLASS__, 'exclude' ) );
	}

	/**
	 * The queue is declared before the tag because the SDK reads whatever is
	 * already in it on load, and replaces push() only afterwards.
	 */
	public static function render(): void {
		$settings = self::settings();

		if ( array() === $settings ) {
			return;
		}

		echo "<script>window.InsiderQueue = window.InsiderQueue || [];</script>\n";

		printf(
			'<script async src="https://%1$s.api.useinsider.com/ins.js?id=%2$s"></script>' . "\n",
			esc_attr( $settings['partner_name'] ),
			esc_attr( $settings['partner_id'] )
		);
	}

	/**
	 * @param mixed $exclusions
	 *
	 * @return mixed
	 */
	public static function exclude( $exclusions ) {
		if ( ! is_array( $exclusions ) ) {
			return $exclusions;
		}

		return array_merge( $exclusions, self::EXCLUSIONS );
	}

	/**
	 * @return array{partner_name: string, partner_id: string} Empty when the
	 *                                                         tag is off or unconfigured.
	 */
	private static function settings(): array {
		if ( ! class_exists( 'GF_Insider_Addon' ) ) {
			return array();
		}

		$addon = GF_Insider_Addon::get_instance();

		if ( '1' !== (string) $addon->get_plugin_setting( 'print_tag' ) ) {
			return array();
		}

		$partner_name = trim( (string) $addon->get_plugin_setting( 'partner_name' ) );
		$partner_id   = trim( (string) $addon->get_plugin_setting( 'partner_id' ) );

		if ( '' === $partner_name || '' === $partner_id ) {
			return array();
		}

		return array(
			'partner_name' => $partner_name,
			'partner_id'   => $partner_id,
		);
	}
}

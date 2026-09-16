<?php
/**
 * The Insider tag in the head, and the cache plugins that would hold it back.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

final class GF_Insider_Tag {

	/**
	 * Both the tag and the queue that feeds it: a cache plugin matches these
	 * against the whole tag, so the inline one is caught by its own variable.
	 */
	const EXCLUSIONS = array( 'useinsider.com', 'InsiderQueue' );

	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'render' ), 1 );
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'exclude' ) );
		add_filter( 'perfmatters_delay_js_exclusions', array( __CLASS__, 'exclude' ) );

		// Minification runs first and rewrites the src to a local copy, which
		// freezes the SDK and hides it from the delay exclusion above.
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

<?php
/**
 * Turns entry values into the two structures the Insider web SDK accepts.
 * Pure PHP on purpose: it is the part worth testing without WordPress.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

final class GF_Insider_Payload {

	/** Identifiers Insider reads from the user object, in its own spelling. */
	const CONTACT_KEYS = array( 'uuid', 'email', 'phone_number', 'name', 'surname' );

	/** Consent flags, all booleans on Insider's side. */
	const OPTIN_KEYS = array( 'gdpr_optin', 'email_optin', 'sms_optin', 'whatsapp_optin' );

	/**
	 * Brazilian numbers in E.164, which is the only format Insider accepts.
	 * A number that already carries a country code is kept as typed.
	 *
	 * @param mixed $value Raw field value.
	 */
	public static function phone( $value ): string {
		$digits = preg_replace( '/\D/', '', (string) $value );

		if ( '' === $digits ) {
			return '';
		}

		// Landline (10) and mobile (11) have no country code in a Brazilian mask.
		if ( 10 === strlen( $digits ) || 11 === strlen( $digits ) ) {
			return '+55' . $digits;
		}

		if ( strlen( $digits ) >= 11 && strlen( $digits ) <= 15 ) {
			return '+' . $digits;
		}

		return '';
	}

	/**
	 * Insider keeps first and last name apart, and every form here asks for the
	 * full name in one field.
	 *
	 * @param mixed $value Raw field value.
	 *
	 * @return array{0: string, 1: string} Name and surname.
	 */
	public static function name_parts( $value ): array {
		$full = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );

		if ( '' === $full ) {
			return array( '', '' );
		}

		$space = mb_strpos( $full, ' ' );

		if ( false === $space ) {
			return array( $full, '' );
		}

		return array( mb_substr( $full, 0, $space ), mb_substr( $full, $space + 1 ) );
	}

	/**
	 * Gravity Forms stores an unchecked consent as '0' and a checked one as '1'.
	 *
	 * @param mixed $value Raw field value.
	 */
	public static function boolean( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$normalized = strtolower( trim( (string) $value ) );

		return ! in_array( $normalized, array( '', '0', 'false', 'nao', 'não', 'no' ), true );
	}

	/**
	 * @param array<string, mixed> $contact Values keyed by CONTACT_KEYS.
	 * @param array<string, mixed> $optins  Values keyed by OPTIN_KEYS.
	 * @param array<string, mixed> $custom  Custom attributes, already keyed.
	 *
	 * @return array<string, mixed> Empty when there is no identifier to send.
	 */
	public static function user( array $contact, array $optins = array(), array $custom = array() ): array {
		$user = array();

		foreach ( self::CONTACT_KEYS as $key ) {
			$value = isset( $contact[ $key ] ) ? trim( (string) $contact[ $key ] ) : '';

			if ( '' === $value ) {
				continue;
			}

			if ( 'phone_number' === $key ) {
				$value = self::phone( $value );
			}

			if ( 'email' === $key && ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
				continue;
			}

			if ( '' !== $value ) {
				$user[ $key ] = $value;
			}
		}

		// A name field holding the full name also fills the surname.
		if ( isset( $user['name'] ) && ! isset( $user['surname'] ) ) {
			list( $name, $surname ) = self::name_parts( $user['name'] );

			$user['name'] = $name;

			if ( '' !== $surname ) {
				$user['surname'] = $surname;
			}
		}

		// Without one of these Insider has no contact to attach the event to.
		if ( ! isset( $user['uuid'] ) && ! isset( $user['email'] ) && ! isset( $user['phone_number'] ) ) {
			return array();
		}

		foreach ( self::OPTIN_KEYS as $key ) {
			if ( isset( $optins[ $key ] ) && '' !== (string) $optins[ $key ] ) {
				$user[ $key ] = self::boolean( $optins[ $key ] );
			}
		}

		$custom = self::clean( $custom );

		if ( array() !== $custom ) {
			$user['custom'] = $custom;
		}

		return $user;
	}

	/**
	 * @param array<string, mixed> $parameters Event parameters, already keyed.
	 *
	 * @return array<string, mixed> Empty when the event has no name.
	 */
	public static function event( string $name, array $parameters = array() ): array {
		$name = trim( $name );

		if ( '' === $name ) {
			return array();
		}

		$event = array( 'event_name' => $name );

		$parameters = self::clean( $parameters );

		if ( array() !== $parameters ) {
			$event['event_parameters'] = array( 'custom' => $parameters );
		}

		return $event;
	}

	/**
	 * @param array<string, mixed> $values
	 *
	 * @return array<string, mixed>
	 */
	private static function clean( array $values ): array {
		$clean = array();

		foreach ( $values as $key => $value ) {
			$key = trim( (string) $key );

			if ( '' === $key || is_array( $value ) || null === $value ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}
}

<?php
/**
 * Turns entry values into the body of Insider's upsert API.
 * Pure PHP on purpose: it is the part worth testing without WordPress.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

final class GF_Insider_Payload {

	/** Default attributes Insider reads from the user object, in its own spelling. */
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
	 * GMT date as Gravity Forms stores it, in the format the upsert API expects.
	 */
	public static function timestamp( string $date_gmt ): string {
		$time = strtotime( $date_gmt . ' UTC' );

		return gmdate( 'Y-m-d\TH:i:s.000\Z', false === $time ? time() : $time );
	}

	/**
	 * One entry of `users`. The document and the uuid identify the contact; without
	 * them the email does, and without an email the phone.
	 *
	 * @param array<string, mixed>       $contact     Values keyed by CONTACT_KEYS.
	 * @param array<string, mixed>       $optins      Values keyed by OPTIN_KEYS.
	 * @param array<string, mixed>       $identifiers Custom identifiers, already keyed.
	 * @param list<array<string, mixed>> $events      Built by event().
	 *
	 * @return array<string, mixed> Empty when there is no identifier to send.
	 */
	public static function user( array $contact, array $optins = array(), array $identifiers = array(), array $events = array() ): array {
		$values = array();

		foreach ( self::CONTACT_KEYS as $key ) {
			$value = isset( $contact[ $key ] ) ? trim( (string) $contact[ $key ] ) : '';

			if ( 'phone_number' === $key ) {
				$value = self::phone( $value );
			}

			if ( 'email' === $key && ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
				continue;
			}

			if ( '' !== $value ) {
				$values[ $key ] = $value;
			}
		}

		// A name field holding the full name also fills the surname.
		if ( isset( $values['name'] ) && ! isset( $values['surname'] ) ) {
			list( $name, $surname ) = self::name_parts( $values['name'] );

			$values['name'] = $name;

			if ( '' !== $surname ) {
				$values['surname'] = $surname;
			}
		}

		// Insider takes uuid, email and phone_number at the root and every other
		// identifier under `custom`; a custom one at the root fails the whole user.
		$custom = array_map(
			static fn ( $value ): string => self::identifier( (string) $value ),
			self::clean( $identifiers )
		);

		$ids = array() === $custom ? array() : array( 'custom' => $custom );

		if ( isset( $values['uuid'] ) ) {
			$ids['uuid'] = $values['uuid'];
		}

		foreach ( array( 'email', 'phone_number' ) as $key ) {
			if ( array() === $ids && isset( $values[ $key ] ) ) {
				$ids[ $key ] = $values[ $key ];
			}
		}

		// Without an identifier Insider has no contact to attach the event to.
		if ( array() === $ids ) {
			return array();
		}

		$attributes = array();

		foreach ( array( 'name', 'surname', 'email', 'phone_number' ) as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$attributes[ $key ] = $values[ $key ];
			}
		}

		foreach ( self::OPTIN_KEYS as $key ) {
			if ( isset( $optins[ $key ] ) && '' !== (string) $optins[ $key ] ) {
				$attributes[ $key ] = self::boolean( $optins[ $key ] );
			}
		}

		$user = array( 'identifiers' => $ids );

		if ( array() !== $attributes ) {
			$user['attributes'] = $attributes;
		}

		if ( array() !== $events ) {
			$user['events'] = $events;
		}

		return $user;
	}

	/**
	 * @param array<string, mixed> $parameters Event parameters, already keyed.
	 *
	 * @return array<string, mixed> Empty when the event has no name.
	 */
	public static function event( string $name, string $timestamp, array $parameters = array() ): array {
		$name = trim( $name );

		if ( '' === $name ) {
			return array();
		}

		$event = array(
			'event_name' => $name,
			'timestamp'  => $timestamp,
		);

		$parameters = self::clean( $parameters );

		if ( array() !== $parameters ) {
			$event['event_params'] = array( 'custom' => $parameters );
		}

		return $event;
	}

	/**
	 * The client's base stores documents without punctuation, and an identifier
	 * that differs by one dot creates a second profile instead of merging.
	 */
	private static function identifier( string $value ): string {
		return preg_match( '#^[\d.\-/]+$#', $value ) === 1
			? (string) preg_replace( '/\D/', '', $value )
			: $value;
	}

	/**
	 * A number stays a number: as a string Insider cannot segment it by range.
	 * A leading zero means the value is a code, not a quantity.
	 *
	 * @return int|float|string
	 */
	private static function scalar( string $value ) {
		if ( preg_match( '/^(0|[1-9]\d*)$/', $value ) === 1 ) {
			return (int) $value;
		}

		if ( preg_match( '/^(0|[1-9]\d*)\.\d+$/', $value ) === 1 ) {
			return (float) $value;
		}

		return $value;
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
				$clean[ $key ] = self::scalar( $value );
			}
		}

		return $clean;
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/** Checks the X-Signature header on a webhook. Kept free of WordPress so it can be tested alone. */
class UnivaPayment_Signature {
	const TOLERANCE_SECONDS = 300;

	public static function is_valid( $header, $body, $secret, $now ) {
		if ( ! is_string( $header ) || '' === $header || ! is_string( $secret ) || '' === $secret ) {
			return false;
		}
		$timestamp = null;
		$signature = null;
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signature = $pair[1];
			}
		}
		if ( null === $timestamp || null === $signature || ! ctype_digit( $timestamp ) ) {
			return false;
		}
		if ( abs( $now - (int) $timestamp ) > self::TOLERANCE_SECONDS ) {
			return false;
		}
		return hash_equals( hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ), $signature );
	}
}

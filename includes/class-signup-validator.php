<?php
/**
 * Signup input validation and tag building.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Pure validation of signup input against the `form` settings.
 */
final class Signup_Validator {

	const MAX_EMAIL     = 190;
	const MAX_NAME      = 100;
	const MAX_INTERESTS = 10;

	/**
	 * Validates and cleans signup input.
	 *
	 * @param array $input Raw input: email, name, interests, consent.
	 * @param array $form  `form` settings.
	 * @return array{data: array{email: string, name: string, interests: string[]}, errors: array<string, string>}
	 */
	public function validate( array $input, array $form ): array {
		$errors = array();

		$email = sanitize_email( trim( (string) ( $input['email'] ?? '' ) ) );
		if ( '' === $email || ! is_email( $email ) || strlen( $email ) > self::MAX_EMAIL ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'connector-for-bluefox-email' );
		}

		$name = '';
		if ( ! empty( $form['name_enabled'] ) ) {
			$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
			if ( self::length( $name ) > self::MAX_NAME ) {
				$errors['name'] = __( 'The name can be at most 100 characters long.', 'connector-for-bluefox-email' );
			} elseif ( '' === $name && ! empty( $form['name_required'] ) ) {
				$errors['name'] = __( 'Please enter your name.', 'connector-for-bluefox-email' );
			}
		}

		$interests = array();
		if ( ! empty( $form['interests_enabled'] ) ) {
			$interests = $this->pick_interests( $input['interests'] ?? array(), $form );
			if ( ! $interests && ! empty( $form['interests_required'] ) ) {
				$errors['interests'] = __( 'Please choose at least one topic.', 'connector-for-bluefox-email' );
			}
		}

		if ( true !== filter_var( $input['consent'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
			$errors['consent'] = __( 'You must give your consent to subscribe.', 'connector-for-bluefox-email' );
		}

		return array(
			'data'   => array(
				'email'     => strtolower( $email ),
				'name'      => $name,
				'interests' => $interests,
			),
			'errors' => $errors,
		);
	}

	/**
	 * Bluefox tags: whitelisted interests + `source-<source>`.
	 *
	 * @param string[] $interests Whitelisted interest tags.
	 * @param string   $source    Signup source.
	 * @param string   $place     Optional placement (shortcode place attribute).
	 * @return string[]
	 */
	public static function tags_for( array $interests, string $source, string $place = '' ): array {
		$tags   = array_values( $interests );
		$tags[] = 'source-' . sanitize_key( $source );
		$place  = self::place( $place );
		if ( '' !== $place ) {
			$tags[] = 'placement-' . $place;
		}
		return array_values( array_unique( $tags ) );
	}

	/**
	 * Tag slugs from untrusted input: lowercase letters, digits and hyphens, max 50 chars, max 20 tags.
	 *
	 * @param array $tags Raw tags.
	 * @return string[]
	 */
	public static function clean_tags( array $tags ): array {
		$clean = array();
		foreach ( $tags as $tag ) {
			if ( ! is_scalar( $tag ) ) {
				continue;
			}
			$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( (string) $tag ) ) ), '-' );
			if ( '' !== $slug ) {
				$clean[] = substr( $slug, 0, 50 );
			}
		}
		return array_slice( array_values( array_unique( $clean ) ), 0, 20 );
	}

	/**
	 * Placement slug from the shortcode `place` attribute (sent by the browser, so kept short and tame).
	 *
	 * @param string $place Raw value.
	 */
	public static function place( string $place ): string {
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $place ) ) ), '-' );
		return substr( $slug, 0, 30 );
	}

	/**
	 * Tag slug from a label: lowercase, no accents, hyphens, prefixed.
	 *
	 * @param string $label  Label, e.g. „Juh, kecske”.
	 * @param string $prefix Prefix.
	 */
	public static function slugify( string $label, string $prefix = 'interest-' ): string {
		$slug = strtolower( remove_accents( $label ) );
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $slug ), '-' );
		if ( '' === $slug ) {
			return '';
		}
		return 0 === strpos( $slug, $prefix ) ? $slug : $prefix . $slug;
	}

	/**
	 * Keeps only configured interest tags; `select` mode allows one.
	 *
	 * @param mixed $raw  Submitted interests (string or array).
	 * @param array $form `form` settings.
	 * @return string[]
	 */
	private function pick_interests( $raw, array $form ): array {
		$raw     = is_array( $raw ) ? $raw : array( $raw );
		$allowed = array_column( (array) ( $form['interests'] ?? array() ), 'tag' );
		$picked  = array();
		foreach ( $raw as $value ) {
			$value = sanitize_key( (string) $value );
			if ( in_array( $value, $allowed, true ) && ! in_array( $value, $picked, true ) ) {
				$picked[] = $value;
			}
		}
		$max = 'select' === ( $form['interests_mode'] ?? 'select' ) ? 1 : self::MAX_INTERESTS;
		return array_slice( $picked, 0, $max );
	}

	/**
	 * Multibyte-safe length.
	 *
	 * @param string $text Text.
	 */
	private static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}
}

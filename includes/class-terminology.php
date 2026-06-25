<?php
/**
 * NES terminology layer.
 *
 * New England Society memberships are presented in respectful, institutional language.
 * This class rewrites MemberPress's own customer-facing wording so members never see
 * consumer/billing terms like "subscription" or "access until".
 *
 * Scope: front-end (member-facing) requests only, and only strings in the `memberpress`
 * text domain. The MemberPress admin (menus, Memberships, Subscriptions, Transactions,
 * settings) is intentionally left untouched. NES plugin strings are written in NES voice
 * directly and are not handled here.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Terminology {

	/**
	 * Case-sensitive substring replacements applied to MemberPress strings.
	 *
	 * Order matters: plural forms are listed before singular so "Subscriptions"
	 * is handled before "Subscription".
	 */
	private const REPLACEMENTS = array(
		// More specific phrases first so "for access until" reads cleanly (e.g. the price string
		// "$140 for access until December 31, 2026" becomes "$140 — valid through December 31, 2026").
		'for access until' => '— valid through',
		'For access until' => '— Valid through',
		'access until' => 'valid through',
		'Access until' => 'Valid through',
		'Subscriptions' => 'Memberships',
		'subscriptions' => 'memberships',
		'Subscription'  => 'Membership',
		'subscription'  => 'membership',
	);

	/**
	 * Full-string replacements (exact match only) to avoid over-matching common words.
	 */
	private const EXACT = array(
		'Sign Up' => 'Join NES',
		'Sign up' => 'Join NES',
	);

	public function hooks(): void {
		// NES voice applies to member-facing (front-end) output only. The MemberPress
		// admin — menus, Memberships, Subscriptions, Transactions, settings — is left
		// exactly as MemberPress ships it.
		if ( is_admin() ) {
			return;
		}

		add_filter( 'gettext', array( $this, 'filter' ), 20, 3 );
		add_filter( 'gettext_with_context', array( $this, 'filter_with_context' ), 20, 4 );
		add_filter( 'ngettext', array( $this, 'filter_plural' ), 20, 5 );
	}

	/**
	 * Filter callbacks deliberately use loose typing: core passes these many times per
	 * request and `ngettext` passes an integer $number, so a strict type hint could throw.
	 *
	 * @param mixed  $translation Translated text.
	 * @param mixed  $text        Original text (unused).
	 * @param string $domain      Text domain.
	 * @return mixed
	 */
	public function filter( $translation, $text, $domain ) {
		return ( 'memberpress' === $domain && is_string( $translation ) ) ? $this->apply( $translation ) : $translation;
	}

	/**
	 * @param mixed  $translation Translated text.
	 * @param mixed  $text        Original text (unused).
	 * @param mixed  $context     Gettext context (unused).
	 * @param string $domain      Text domain.
	 * @return mixed
	 */
	public function filter_with_context( $translation, $text, $context, $domain ) {
		return ( 'memberpress' === $domain && is_string( $translation ) ) ? $this->apply( $translation ) : $translation;
	}

	/**
	 * @param mixed  $translation Selected singular/plural translation.
	 * @param mixed  $single      Singular source (unused).
	 * @param mixed  $plural      Plural source (unused).
	 * @param mixed  $number      Count (unused).
	 * @param string $domain      Text domain.
	 * @return mixed
	 */
	public function filter_plural( $translation, $single, $plural, $number, $domain ) {
		return ( 'memberpress' === $domain && is_string( $translation ) ) ? $this->apply( $translation ) : $translation;
	}

	private function apply( string $text ): string {
		if ( isset( self::EXACT[ $text ] ) ) {
			return self::EXACT[ $text ];
		}

		foreach ( self::REPLACEMENTS as $from => $to ) {
			if ( false !== strpos( $text, $from ) ) {
				$text = str_replace( $from, $to, $text );
			}
		}

		return $text;
	}
}

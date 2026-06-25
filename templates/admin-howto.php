<?php
/**
 * How To admin template — quick reference for the NES team.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'How To', 'nes-calendar-memberships' ); ?></h2>
<p><?php esc_html_e( 'A quick reference for the NES team. These steps cover the most common membership tasks.', 'nes-calendar-memberships' ); ?></p>

<div class="nescm-howto">
	<h3><?php esc_html_e( 'Approve an offline (cheque / Zelle) payment', 'nes-calendar-memberships' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Open the Offline Payments / Manual Renewals tab.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Find the member under Pending Offline Payments and click Review.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Check the Calculation Preview — membership year, valid-through date, and target membership.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Confirm the Payment Received Date (the date NES received the cheque or Zelle payment).', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Click Complete & Activate. The membership becomes valid immediately through December 31 of that year.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'If a duplicate warning appears, continue only if a second membership for that year is intended.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'To reject a payment instead, click Cancel / Void.', 'nes-calendar-memberships' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'Record a payment that did not come through the website', 'nes-calendar-memberships' ); ?></h3>
	<p><?php esc_html_e( 'Use Create Manual Renewal (same tab) only when a member paid offline without checking out online. Enter the member (ID, login, or email), membership type, payment date, method, and amount.', 'nes-calendar-memberships' ); ?></p>

	<h3><?php esc_html_e( "Create next year's memberships", 'nes-calendar-memberships' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Open the Generate Year tab.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Set Source Year (this year) and Target Year (next year); keep the clone options on.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Use Draft first, then review pricing and access before publishing — or Publish immediately.', 'nes-calendar-memberships' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'Add a brand-new membership type', 'nes-calendar-memberships' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Open the Membership Types tab and add the new type by name.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Create its year-specific MemberPress membership products as usual.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'In each product, enable NES Calendar Membership Rules and select the new membership family.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Run Checkup to confirm everything is configured.', 'nes-calendar-memberships' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'What the settings mean', 'nes-calendar-memberships' ); ?></h3>
	<ul>
		<li><?php esc_html_e( 'Annual Cutoff Date — renewals after this date count toward next year.', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Cheque/Zelle Checkout Message — controls the wording members see at checkout (display only).', 'nes-calendar-memberships' ); ?></li>
		<li><?php esc_html_e( 'Test Date Override — staging only; must be blank in production.', 'nes-calendar-memberships' ); ?></li>
	</ul>
</div>

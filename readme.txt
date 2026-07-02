=== NES Calendar Memberships ===
Contributors: ciderhouse
Tags: memberpress, memberships, renewals
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calendar-year membership routing, checkout messaging, dashboard helpers, and admin renewal tools for NES MemberPress memberships.

== Description ==

NES Calendar Memberships is a focused MemberPress extension for calendar-year memberships. It routes renewals to the correct year-specific MemberPress membership, displays clear checkout messaging, provides dashboard shortcodes, helps admins record check/Zelle renewals, and validates common configuration issues.

This plugin does not replace MemberPress, process payments, store cards, or implement custom Stripe billing anchors.

== Installation ==

1. Upload `nes-calendar-memberships.zip`.
2. Activate the plugin.
3. Confirm MemberPress is active.
4. Go to `MemberPress > NES Calendar Memberships`.
5. Configure settings, tag memberships, and run Checkup.

== Changelog ==

= 1.0.7 =
* [nescm_membership_status badge="yes"] renders a state-colored badge (green active, amber pending, red expired), with matching styles shipped in the plugin stylesheet.

= 1.0.6 =
* Fixed: the renew button now appears for expired auto-renew members. It stays hidden for active auto-renew members.

= 1.0.5 =
* Fixed: smart login routing now works from the MemberPress login form (its hidden redirect field no longer disables routing).
* Members see a "Membership Profile" link in the WooCommerce account navigation.
* Users with order history see a "My Orders" link in the MemberPress account navigation.

= 1.0.4 =
* [nescm_membership_expiration] gains part="label|date" and fallback attributes for composing dashboard rows.
* Auto-renew members see their subscription's next billing date; expired memberships read "Expired on <date>".
* [nescm_renew_cta] gains an href attribute; new [nescm_auto_renew_url] shortcode; [nescm_renewal_url] resolves the current member's family automatically.
* Members-only dashboard gate on the configured Dashboard Page, and smart post-login routing (members to the dashboard, others to the shop account).
* WooCommerce: members-only "Member Dashboard" account menu item, and member/billing address sync (toggleable).

= 1.0.3 =
* Dashboard now shows "Renews <date>" for recurring memberships and "Expires <date>" for yearly ones.
* Added a [nescm_renew_cta] shortcode that prompts yearly members to renew once the new year is available.
* Changed "Cheque" to the American spelling "Check" throughout.

= 1.0.2 =
* Adopted New England Society membership language across member-facing text (no "subscription"; uses "valid through" instead of "access until"/"expires"), including a layer that adjusts MemberPress's own wording.
* Renamed the "Sales Window" field to "Enrollment Period".
* Added a Membership Types screen so new membership families can be added without code changes.
* Added a How To reference tab for the NES team.
* Offline payment completion no longer overwrites the transaction's original created date.
* Hardened the manual renewal preview and clarified the Cheque/Zelle checkout message setting.
* Various validation, escaping, and metadata fixes.

= 1.0.1 =
* Added test date override support.
* Improved settings date controls.
* Added pending offline payment review and completion workflow.
* Added manual renewal calculation preview.
* Added MemberPress membership admin columns and stronger checkup warnings.

= 1.0.0 =
* Initial v1 release.

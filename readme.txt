=== NES Calendar Memberships ===
Contributors: ciderhouse
Tags: memberpress, memberships, renewals
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calendar-year membership routing, checkout messaging, dashboard helpers, and admin renewal tools for NES MemberPress memberships.

== Description ==

NES Calendar Memberships is a focused MemberPress extension for calendar-year memberships. It routes renewals to the correct year-specific MemberPress membership, displays clear checkout messaging, provides dashboard shortcodes, helps admins record cheque/Zelle renewals, and validates common configuration issues.

This plugin does not replace MemberPress, process payments, store cards, or implement custom Stripe billing anchors.

== Installation ==

1. Upload `nes-calendar-memberships.zip`.
2. Activate the plugin.
3. Confirm MemberPress is active.
4. Go to `MemberPress > NES Calendar Memberships`.
5. Configure settings, tag memberships, and run Checkup.

== Changelog ==

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

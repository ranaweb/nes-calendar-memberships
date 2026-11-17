=== NES Calendar & Memberships ===
Contributors: ranaweb, ciderhouse
Tags: events, series, memberpress, memberships, renewals
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Event series and repeat schedules for The Events Calendar, plus calendar-year membership and renewal tools for MemberPress. Created by Rana Mustafiz and Cider House.

== Description ==

NES Calendar Memberships is a focused MemberPress extension for calendar-year memberships. It routes renewals to the correct year-specific MemberPress membership, displays clear checkout messaging, provides dashboard shortcodes, helps admins record check/Zelle renewals, and validates common configuration issues.

This plugin does not replace MemberPress, process payments, store cards, or implement custom Stripe billing anchors.

== Installation ==

1. Upload `nes-calendar-memberships.zip`.
2. Activate the plugin.
3. Confirm MemberPress is active.
4. Go to `MemberPress > NES Calendar Memberships`.
5. Configure settings, tag memberships, and run Checkup.

== Event series ==

Open Events > Series > Add New Series. Enter a title and description. Use Find events to add existing events, then publish. View Series opens the public page with upcoming and past dates.

To repeat an event, set its first date and time in the event editor. Under Event Series select Schedule multiple events. For the NES meetup choose Monthly, Weekday of the month, Second, Monday. Preview dates, then save the event. Extra dates and exceptions can be added as needed.

An existing event has three edit scopes. This event changes one date. This and following events starts a new schedule on that date within the same named series. All events in this schedule also updates earlier dates. Previously customized dates keep their individual changes.

The Series box on an event lets you select a series or create a draft. Publish the draft under Events > Series to make its page available. Tickets are managed on the individual event. The rolling generation window is 24 months.

On a site using the earlier NES Calendar Extension, update this membership plugin first and visit the WordPress admin as an administrator. It takes over the existing calendar and retires the old extension without restoring Pro. Keep the old extension files available for rollback. The meetup migration and recovery controls are under Tools > NES Calendar Migration.

== Changelog ==

= 1.1.1 =
* Added the Series link to Elementor single-event templates using the Post Content widget.

= 1.1.0 =
* Combined membership version 1.0.7 and the NES calendar extension into one plugin. Added named Series pages and event grouping, the Time & Date repeat editor and date previews, and scoped updates that retain matching event IDs.

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

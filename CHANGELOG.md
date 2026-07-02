# Changelog

## 1.0.4 - 2026-06-27

- `[nescm_membership_expiration]` gains `part="label"` (outputs `Renews` / `Expires` / `Expired on`), `part="date"` (date only), and a `fallback` attribute — so Elementor dashboards can compose fully dynamic label/value rows with no static text.
- Auto-renew members now see the subscription's **next billing date** (resolved from the active MemberPress subscription, falling back to the transaction expiration). Expired memberships now read "Expired on <date>".
- `[nescm_renew_cta]` gains an `href` attribute so the renewal button can point at a renewal chooser page instead of deep-linking to checkout.
- New `[nescm_auto_renew_url]` shortcode: the checkout URL of the recurring (auto-renew) membership for the current member's family (or an explicit `family` attribute). `[nescm_renewal_url]` with no attribute now resolves the current member's family too — one generic renewal chooser page works for every member.
- New members-only **dashboard gate**: the Dashboard Page ID setting now protects the member dashboard. Members — including expired members — may view it; other logged-in users are redirected to the WooCommerce account page; logged-out visitors are sent to the login page and returned after logging in.
- New **smart login routing**: after login, members land on the dashboard and everyone else on the shop account page. Staff keep the WordPress default. An explicit `redirect_to` always wins; disable entirely with the `nescm_login_routing_enabled` filter.
- New **WooCommerce integration** (active only when WooCommerce is installed): a members-only "Member Dashboard" item in the account navigation, and **address sync** — the MemberPress member address and the WooCommerce billing address stay in step whichever one is updated (Settings → Address Sync to disable).

## 1.0.3 - 2026-06-26

- Dashboard wording now distinguishes the two membership types: recurring memberships show "Renews <date>" and yearly (one-time) memberships show "Expires <date>".
- Added a `[nescm_renew_cta]` shortcode: once the next membership year is available (after the annual cutoff) or the current membership has expired, yearly members see a "Renew now for <year>" button that routes them to the correct year's checkout. Recurring members never see it.
- Renamed "Cheque" to the American spelling "Check" throughout member-facing and admin text (and the manual-renewal payment-method value).

## 1.0.2 - 2026-06-25

- Adopted institutional New England Society membership language across member-facing strings: no "subscription" wording, and "valid through" replaces "access until"/"expires".
- Added a terminology layer that rewrites MemberPress's own customer-facing wording (e.g. "Subscription" → "Membership", "access until" → "valid through") so it matches NES voice at checkout, in the account area, and in emails.
- Renamed the membership metabox "Sales Window" field to "Enrollment Period" (label only; stored data unchanged).
- Added a **Membership Types** admin screen so new membership families can be added without editing the plugin. The family key is auto-generated from the name and locked after creation; built-in families cannot be removed and a type in use cannot be deleted.
- `nescm_get_families()` now reads built-in defaults plus admin-managed custom types, with a new `nescm_families` filter.
- Added a **How To** settings tab with a quick reference for the NES team (approving offline payments, creating memberships, generating years).
- Fixed: completing an offline payment no longer overwrites the transaction's original `created_at`; the payment-received date is stored as `_nescm_payment_received_date` metadata instead.
- Hardened the manual-renewal calculation preview against unescaped admin-set values.
- Clarified the "Cheque/Zelle Checkout Message" setting (formerly "Manual Payment Access") to make clear it only changes checkout wording.
- Corrected plugin/author header URLs, fixed the Checkup settings-validation copy and added renewal-date validation, replaced `current_time('timestamp')` with `time()`, removed unused code, and added missing translator comments.

## 1.0.1 - 2026-06-24

- Added `NES_MEMBERSHIP_TEST_DATE` and admin test-date override support.
- Changed cutoff calculations so the cutoff date is inclusive; renewals after the cutoff route to the next membership year.
- Updated the default annual cutoff to September 30.
- Replaced numeric cutoff/renewal fields with month and day dropdowns.
- Added admin-only test-date warning banner.
- Renamed the manual renewal tab to Offline Payments / Manual Renewals.
- Added pending offline payment review, completion, and void workflows.
- Added calculation previews for pending offline payments and manual renewal creation.
- Added duplicate prevention to pending offline payment completion.
- Added NES columns to the MemberPress memberships list.
- Expanded Checkup warnings for year/date mismatches, pending offline payments, and dashboard data readiness.

## 1.0.0 - 2026-06-24

- Added safe plugin bootstrap and MemberPress availability checks.
- Added settings screen with September 1 default next-year renewal start.
- Added calendar-year calculator.
- Added MemberPress adapter for product, transaction, and checkout interactions.
- Added NES metadata metabox for MemberPress memberships.
- Added renewal router at `/membership-renew/?family=...`.
- Added checkout notices.
- Added transaction metadata sync.
- Added dashboard shortcodes.
- Added manual cheque/Zelle renewal helper.
- Added next-year membership generator.
- Added configuration checkup screen.
- Added release package builder and metadata checks.

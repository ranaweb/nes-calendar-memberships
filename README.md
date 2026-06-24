# NES Calendar Memberships

NES Calendar Memberships is a focused MemberPress extension for NES calendar-year memberships. It does not replace MemberPress, process payments, store cards, or implement custom Stripe billing-cycle anchoring.

## What It Does

- Adds NES calendar-year metadata to MemberPress memberships.
- Routes `/membership-renew/?family=resident` style renewal links to the correct year-specific MemberPress checkout.
- Uses September 30 as the default annual cutoff date. Renewals after the cutoff route to the next membership year.
- Shows clear checkout notices with membership year and December 31 expiration.
- Records NES transaction metadata when MemberPress transactions are stored.
- Provides dashboard shortcodes for membership level, status, expiration, renewal method, CTA, and summary.
- Adds an admin helper for cheque, Zelle, and other offline renewals.
- Adds a read-only checkup screen for configuration issues.
- Adds a conservative next-year membership generator.

## What It Does Not Do

- It does not create a replacement membership system.
- It does not process payments directly.
- It does not store card data or gateway credentials.
- It does not add Stripe webhooks, subscription schedules, or January 1 billing anchoring.
- It does not clone separate MemberPress rules/access posts in the year generator.
- It does not modify MemberPress core files or theme files.

## Requirements

- WordPress 6.5 or later. Tested up to 7.0.
- PHP 8.1 or later.
- MemberPress active for runtime integrations.

The plugin is defensive when MemberPress is inactive: settings remain available, but checkout routing, transaction sync, and dashboard data are disabled.

## MemberPress Source Review Notes

The bundled source reviewed for v1 was `memberpress-plus-1.12.15.zip`.

- Header name: `MemberPress Plus 10 (Legacy)`.
- Version: `1.12.15`.
- Requires at least: WordPress `6.5`.
- Tested up to: WordPress `6.9`.
- Requires PHP: `7.4`.
- Membership product CPT: `memberpressproduct`, exposed as `MeprProduct::$cpt`.
- Checkout URL: `MeprProduct::url()`.
- Checkout notice hook: `mepr_above_checkout_form`.
- Account hook exists: `mepr_account_home`; this plugin primarily uses shortcodes for Elementor/account-page portability.
- Transaction model: `MeprTransaction`.
- Transaction store hook: `mepr_txn_store`.
- Transaction status strings: `pending`, `complete`, `confirmed`, `refunded`, `failed`.
- Manual gateway string: `manual`.
- One-time fixed expiration uses product meta/model fields `period_type = lifetime`, `expire_type = fixed`, and `expire_fixed = YYYY-MM-DD`.
- `MeprTransaction::store()` normalizes non-lifetime expiration values to `Y-m-d 23:59:59`.
- Offline gateway class: `MeprArtificialGateway`; it can require admin manual completion.

If MemberPress changes these internals, update `includes/class-memberpress-adapter.php` first.

## Setup

1. Activate MemberPress.
2. Activate NES Calendar Memberships.
3. Go to `MemberPress > NES Calendar Memberships`.
4. Confirm the annual cutoff date. The default is September 30.
5. Edit each year-specific MemberPress membership.
6. Enable `NES Calendar Membership Rules`.
7. Assign the family key, public label, membership year, valid dates, and renewal method.
8. Run `Checkup`.
9. Add renewal links to dashboards using `/membership-renew/?family=family_key`.
10. Add dashboard shortcodes to the account/dashboard page.

## Family Keys

- `resident`
- `joint_resident`
- `junior`
- `nonresident`
- `pilgrim_individual`
- `pilgrim_joint`
- `firewood_individual`
- `firewood_joint`

## Renewal Routing

Use:

```text
/membership-renew/?family=resident
```

Or shortcodes:

```text
[nescm_renewal_url family="resident"]
[nescm_renewal_router]
```

The router calculates the target membership year from the current date and configured next-year renewal start date, then finds the published manual MemberPress membership for that family/year.

## Dashboard Shortcodes

```text
[nescm_membership_level]
[nescm_membership_status]
[nescm_membership_expiration]
[nescm_renewal_method]
[nescm_auto_renew_cta]
[nescm_membership_summary]
```

Manual memberships use `Expires`. The plugin only uses automatic-renewal wording when it can detect an active MemberPress subscription.

## Offline Payments / Manual Renewals

Use `MemberPress > NES Calendar Memberships > Offline Payments / Manual Renewals`.

The primary workflow is reviewing pending cheque/Zelle/offline MemberPress transactions. The screen shows pending NES transactions, calculates the target membership year from the payment received date and annual cutoff, then lets an admin complete and activate the transaction or cancel/void it.

The secondary workflow is `Create Manual Renewal Manually`. Use it only if the member did not complete an online cheque/Zelle checkout and you need to record a payment received outside the website.

The default action creates a pending manual renewal. Admins may choose completed renewal when recording money already received.

## Generate Year

Use `MemberPress > NES Calendar Memberships > Generate Year`.

The generator creates target-year MemberPress membership products from source-year NES memberships. It updates NES metadata, sets the fixed expiration to December 31, and links the source product to the generated next-year product.

Generated products default to draft. Review pricing, access rules, and public visibility before launch.

## Checkup

The checkup screen flags issues such as:

- MemberPress inactive.
- Invalid settings.
- Missing family/year metadata.
- Duplicate family/year manual memberships.
- Missing current or next-year products.
- Draft products that renewal routing expects to be published.
- Manual products that appear recurring.
- NES valid-through dates that do not match MemberPress fixed expiration.
- Auto-renew CTA enabled without a URL.
- `NES_MEMBERSHIP_TEST_DATE` or admin test date override active.

## Developer Test Date Override

For testing only:

```php
define( 'NES_MEMBERSHIP_TEST_DATE', '2026-12-16' );
```

Date source priority:

1. `NES_MEMBERSHIP_TEST_DATE` constant, if defined and valid.
2. Admin test date override, if set and valid.
3. Current date in the WordPress site timezone.

When a test date is active, NES admin tabs show a large warning banner. Remove the constant or clear the setting before production.

## Release Packaging

Use:

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-zip.ps1
```

The upload target is:

```text
dist/nes-calendar-memberships.zip
```

The ZIP contains exactly one top-level folder:

```text
nes-calendar-memberships/nes-calendar-memberships.php
```

Do not upload a ZIP that adds a versioned outer folder around the plugin folder.

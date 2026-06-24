# Changelog

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

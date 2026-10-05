# Distributor onboarding

## Routes

- `/distributors`: public distribution information and application link.
- `/distributor/register`: five-step PDF application, after account creation/sign-in.
- `/distributor`: saved application/status, or approved distributor workspace.
- `/dashboard/distributors`: admin-only review queue.

The existing `/distribution-partner` entry point also links to registration.

## Storage and access

`DistributorService::ensureSchema()` creates the separate `distributor_applications`
table. Applications are associated with the authenticated account. Approval creates
or links one vendor record and grants the existing vendor role; it does not create
a second user. Admins must supply a positive product allowance when approving.
Vendor subscription pricing is unchanged. No distributor price was specified.

Compliance answers may be "Not available yet". Document availability questions
do not require uploads. Review outcomes and submissions queue emails. Drafts do
not grant selling permissions. Existing approved applications cannot be demoted
through onboarding; selling suspension remains in vendor administration.

## Verification

Run `php -n tests/distributor_test.php` and
`php -n tests/distributor_review_test.php`. Review tests use an isolated fake
database and mail queue, not production records.

Run `php -n -S 127.0.0.1:8098 -t .`, then run
`PLAYWRIGHT_MODULE=/path/to/playwright node tests/distributor_browser.cjs`.
`/tests/distributor_preview.php` is loopback-only and must not be deployed.

Deployed on 2026-09-22. Production backup:
`/home/sellerafrica/deploy-backups/distributor-before-20260922.tar.gz`.
The distributor application table was created successfully. Live public pages
returned HTTP 200 and passed mobile/desktop overflow checks; protected routes
redirected anonymous visitors to login. No production applications were submitted
or approved during testing. Database-backed application submission, approval, and
actual email delivery still need an authenticated end-to-end check.

## Design and signup update (2026-09-22)

`/distributors` and `/distribution-partner` now render the dedicated retail
landing page. Anonymous visitors to `/distributor/register` create an account on
that same URL, using the shared registration controller with a server-defined
`DISTRIBUTOR_SIGNUP` context. The buyer registration UI is not used. Existing
CSRF, rate limits, captcha, password checks, email verification and duplicate
account checks remain in that controller. Sign-in uses `login?as=distributor`.

Design backup: `/home/sellerafrica/deploy-backups/distributor-design-before-20260922.tar.gz`.
The new generated retail hero is `public/assets/images/distribution-retail-hero.png`.
Run `tests/distributor_design_browser.cjs` for account UI and landing layout tests.

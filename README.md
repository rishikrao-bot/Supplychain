# Compass MEP Job & Supply Chain Portal

Order intake, Wates approvals, contractor dispatch, engineer visit reports, exceptions, valuations and self-billing.

## Prototype

`prototype/index.html` is a self-contained clickable demo (sample data, saved in the browser only). Open it in any browser. Three views:

- **Compass helpdesk**: log orders (email/phone), live rate-card estimates, dispatch with compliance checks, review reports, issue valuations, self-billing & CIS, rules & settings.
- **Wates portal**: approve estimates with order numbers, decide on exceptions, raise orders, view reports and valuations (no contractor costs or margins shown).
- **Supply chain partner**: accept or decline job offers within their remit (trade, zone, job limit, compliance), choose the attending engineer, upload compliance renewals, view self-billed invoices.
- **Engineer app**: check-in with GPS, safety checklist, timestamped photos, materials, outcome, site sign-off, check-out.

Also: certificate-checked competencies, monthly applications for payment (Compass submits, Wates certifies in the portal or Compass records the payment notice), partner payment periods, and a Compass-only profitability view.

Pricing follows the Wates Schedule of Rates (11 Nov 2026). Terms still under discussion (return-visit call-outs, multi-trade call-outs, approval policy, inspection fee window) are settings.

## Hosted site (compass-mep.jobsavvy.co.uk)

- `site/compassmep/index.html`: start page with two ways in: the live portal and the demo.
- `site/compassmep/portal/`: the live portal (placeholder until the real system is deployed).
- `site/compassmep/demo/`: the demo, built from `prototype/index.html` with a permanent DEMO banner. Rebuild with `scripts/build-site.sh`, which also creates `site/compassmep-upload.zip` for cPanel.
- `site/jobsavvy/`: holding page for jobsavvy.co.uk.

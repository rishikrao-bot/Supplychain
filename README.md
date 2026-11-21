# Job & Supply Chain Portal

- `portal-app/`: the **live system** (Laravel 12, PHP 8.3, MySQL). See `portal-app/README.md` and `portal-app/DEPLOY.md`.
- `prototype/`: the clickable **demo** (single HTML file, made-up sample data).
- `site/portal-site/`: static pages for the hosting (start page, hosted demo, portal placeholder).

Order intake, client approvals, contractor dispatch, engineer visit reports, exceptions, valuations and
self-billing, plus a CRM, resource planner and Power BI reports for the operator's own staff.

Nothing company-specific is kept in this repository: the operator's name and logo are set on the server
(`PORTAL_COMPANY`, `PORTAL_NAME`, `PORTAL_LOGO` in `.env`), and real starting data (clients, rates,
contracts, rules) is read from `portal-app/database/seeders/data/portal.json`, which lives only on the server.

## Prototype

`prototype/index.html` is a self-contained clickable demo (sample data, saved in the browser only). Open it in any browser. Views:

- **Operator helpdesk**: log orders (email/phone), live rate-card estimates, dispatch with compliance checks, review reports, issue valuations, self-billing & CIS, rules & settings.
- **Client portal**: approve estimates with order numbers, decide on exceptions, raise orders, view reports and valuations (no contractor costs or margins shown).
- **Supply chain partner**: accept or decline job offers within their remit (trade, zone, job limit, compliance), choose the attending engineer, upload compliance renewals, view self-billed invoices.
- **Engineer app**: check-in with GPS, safety checklist, timestamped photos, materials, outcome, site sign-off, check-out.

## Hosted site

- `site/portal-site/index.html`: start page with two ways in: the live portal and the demo.
- `site/portal-site/portal/`: placeholder until the real system is deployed.
- `site/portal-site/demo/`: the demo, built from `prototype/index.html` with a permanent DEMO banner. Rebuild with `scripts/build-site.sh`, which also creates `site/site-upload.zip` for cPanel.

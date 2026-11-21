# Compass MEP portal (live system)

Laravel 12 application for the live reactive maintenance portal at
`https://compass-mep.jobsavvy.co.uk/portal`. The clickable demo lives separately in `../prototype`.

## What's built (stage 1, part 1)

- Database for the whole of stage 1 (see `database/migrations`): accounts (company separation),
  organisations (Compass, clients, partners), users, rate cards, contracts, sites, competencies and
  certificates, compliance documents, jobs (`work_orders`, Q-/P- numbers), trades, attachments,
  offers, availability requests and slots, visits, exceptions, quotes, valuations, audit log, settings.
- Starting data (`database/seeders/PortalSeeder.php`): Compass MEP, Wates with the 11 Nov 2026 schedule
  of rates, Castle Point and South East contracts, 40 competencies with certificate rules, and the
  rules marked agreed / proposed / on hold.
- Logins: email + password, then two-step login with an authenticator app (recovery codes included).
- Invites: emailed link, valid 7 days, used once.
- Users & access: Compass admins manage everyone; client admins their own company; partner managers
  their own site staff. Change role, remove/restore access, resend invite, reset two-step login.
  The last active Compass admin can't be removed. Every change is written to the audit log.

- Completion report: a branded PDF (job, attendance, safety checks, work done, materials, before/after
  photos, sign-off; no prices) is created and attached automatically when a job is completed. Sample:
  `docs/sample-completion-report.pdf`.
- Email: sends through Microsoft 365 (Rishi's mailbox for now); if sending isn't set up, invite links
  are shown on screen to copy.

- Microsoft 365 (optional, see `MICROSOFT.md`): Compass staff can sign in with Microsoft; completion
  reports are copied to a SharePoint library by client and contract; a Dataverse CRM connection for
  syncing clients and jobs. Compass admins test it all under **Microsoft 365**.

- Set-up screens (Compass admin and helpdesk):
  - Sites: register by client and contract, with zone, contacts, access, asbestos notes and GPS.
  - Contractors: contact details, type, zones, job limit, their rates, competencies (multi-select with
    certificate checks), compliance documents with expiry and file upload (only those expiring within
    30 days shown unless "Show all"), suspend / lift.
  - Competencies: the grouped list, how many partners hold each, add new (admin) with any required certificate.
  - Rates & rules: the client rate card (TBC items marked) and the rules with agreed / proposed / on hold
    status, editable by admins.

## Run locally

    composer install
    cp .env.example .env && php artisan key:generate
    touch database/database.sqlite && php artisan migrate --seed
    php artisan portal:invite-admin you@example.com "Your Name"   # prints the invite link
    php artisan serve

Tests: `php artisan test`

See `DEPLOY.md` for the hosting set-up and `MICROSOFT.md` for Microsoft 365 (staff sign-in, SharePoint, Dataverse CRM).

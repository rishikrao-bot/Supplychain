# Deploying the live portal (cPanel, portal.example.com)

The live portal runs at `https://portal.example.com/portal`. The app code lives outside the
web folder; only its `public` folder is exposed, at `/portal`.

Server: PHP 8.3 (extensions pdo_mysql, mbstring, intl, gd, zip, fileinfo, exif, bcmath, opcache,
curl, openssl), MySQL database `portal_db`, user `portal_db`.

## First deployment (SSH, once)

1. Get the code (outside public_html and outside the subdomain folder):

       cd ~
       git clone -b claude/exciting-cerf-918foh <repo URL> portal
       cd portal/portal-app
       composer install --no-dev --optimize-autoloader

2. Settings file. Copy and edit; the database password is typed in by the admin, never committed:

       cp .env.example .env

   Set these lines in `.env`:

       APP_NAME="Maintenance Portal"
       APP_ENV=production
       APP_DEBUG=false
       APP_URL=https://portal.example.com/portal
       APP_LOCALE=en
       DB_CONNECTION=mysql
       DB_HOST=localhost
       DB_PORT=3306
       DB_DATABASE=portal_db
       DB_USERNAME=portal_db
       DB_PASSWORD=   (the admin enters this)
       SESSION_DRIVER=database
       SESSION_SECURE_COOKIE=true
       MAIL_MAILER=log   (until Microsoft 365 sending is set up; see below)
       MAIL_FROM_ADDRESS=portal@example.com
       MAIL_FROM_NAME="Maintenance Portal"

   Then:

       php artisan key:generate --force
       php artisan migrate --force
       php artisan db:seed --force
       php artisan config:cache && php artisan route:cache && php artisan view:cache
       chmod -R ug+rwX storage bootstrap/cache

3. Expose it at /portal. In the subdomain folder, move the static placeholder aside and link the app's
   public folder in its place:

       cd ~/portal.example.com        # the subdomain's document root
       mv portal portal-placeholder-old
       ln -s ~/portal/portal-app/public portal

   Check `https://portal.example.com/portal` shows the login page.

4. Create the first admin. This prints an invite link (valid 7 days):

       cd ~/portal/portal-app
       php artisan portal:invite-admin admin@example.com "Your Name"

   Open the link, set a password, then scan the QR code with an authenticator app.

## Your name, logo and starting data (kept off GitHub)

Nothing company-specific is in the code. On the server:

1. Branding: add to `.env`, then put your logo in `public/brand/` (that folder is never committed):

       PORTAL_COMPANY="Your Company Ltd"
       PORTAL_NAME="Your Company Portal"
       PORTAL_LOGO=brand/logo.png

2. Starting data: upload your private `portal.json` (clients, rate cards, contracts, rules) to
   `database/seeders/data/portal.json` **before** running `php artisan db:seed --force`. Without it, the
   made-up example data in `portal.example.json` is used. Keep a copy somewhere safe (e.g. SharePoint);
   it is never stored in Git.

## Updates

    cd ~/portal && git pull
    cd portal-app && composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan config:cache && php artisan route:cache && php artisan view:cache

Never run `migrate:fresh` or `db:wipe` on the server: they delete all data.

## Sending email (invites and notifications)

For now the portal sends from the admin's Microsoft 365 mailbox, and replies come back to it (set up
Outlook rules for them). Later, point replies at a helpdesk mailbox by changing only
MAIL_REPLY_TO_ADDRESS, or switch the sender to a dedicated mailbox such as portal@example.com.

In `.env` on the server:

    MAIL_MAILER=smtp
    MAIL_HOST=smtp.office365.com
    MAIL_PORT=587
    MAIL_SCHEME=null
    MAIL_USERNAME=admin@example.com
    MAIL_PASSWORD=   (typed in by the admin on the server only, never committed)
    MAIL_FROM_ADDRESS=admin@example.com
    MAIL_FROM_NAME="Maintenance Portal"
    MAIL_REPLY_TO_ADDRESS=admin@example.com

Then `php artisan config:cache`.

Microsoft 365 must allow the mailbox to send this way. In the Microsoft 365 admin centre: Users >
Active users > the admin > Mail > Manage email apps > tick **Authenticated SMTP**. If the your company
tenant has Security Defaults or blocks SMTP AUTH (common when two-step login is on), your company's
Microsoft 365 administrator needs to allow it for this mailbox, or provide a dedicated mailbox for the
portal. Until it works, set `MAIL_MAILER=log`: invites are then shown on screen as a link to copy and
send by hand, so nobody is blocked.

## Completion reports

When a job is marked complete, a PDF completion report is created automatically and attached to the
job (stored under `storage/app/private/reports/`). Your own staff, the job's client and the partner
who did the work can download it at `/portal/jobs/{id}/completion-report.pdf`. It shows no prices.
A sample is in `docs/sample-completion-report.pdf`. Back up `storage/app/private` along with the
database.

## Scheduled tasks (cron job, once)

The portal runs small background tasks: To Do sync every 5 minutes and retrying SharePoint copies
every 15 minutes.
In cPanel → **Cron Jobs** → Add New Cron Job, "Once Per Minute" (`* * * * *`), command:

    cd ~/portal/portal-app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1

(If `php -v` on the server shows a different PHP than 8.3, use the path cPanel's MultiPHP gives for 8.3,
e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.)

## Resource planner and Power BI

After the update that adds them: `php artisan migrate --force`, then follow `RESOURCES.md` to import the
Resources Tracker and `POWERBI.md` for reports and API keys.

## Microsoft 365 (sign-in, SharePoint, Dataverse)

See `MICROSOFT.md`. After an update that adds it, run `php artisan migrate --force` (adds one column
to users) and then `php artisan portal:microsoft-check`.

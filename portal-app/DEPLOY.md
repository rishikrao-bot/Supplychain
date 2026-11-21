# Deploying the live portal (cPanel, compass-mep.jobsavvy.co.uk)

The live portal runs at `https://compass-mep.jobsavvy.co.uk/portal`. The app code lives outside the
web folder; only its `public` folder is exposed, at `/portal`.

Server: PHP 8.3 (extensions pdo_mysql, mbstring, intl, gd, zip, fileinfo, exif, bcmath, opcache,
curl, openssl), MySQL database `messaxwp_compass`, user `messaxwp_compass`.

## First deployment (SSH, once)

1. Get the code (outside public_html and outside the subdomain folder):

       cd ~
       git clone -b claude/exciting-cerf-918foh <repo URL> compass-portal
       cd compass-portal/portal-app
       composer install --no-dev --optimize-autoloader

2. Settings file. Copy and edit; the database password is typed in by Rishi, never committed:

       cp .env.example .env

   Set these lines in `.env`:

       APP_NAME="Compass MEP Portal"
       APP_ENV=production
       APP_DEBUG=false
       APP_URL=https://compass-mep.jobsavvy.co.uk/portal
       APP_LOCALE=en
       DB_CONNECTION=mysql
       DB_HOST=localhost
       DB_PORT=3306
       DB_DATABASE=messaxwp_compass
       DB_USERNAME=messaxwp_compass
       DB_PASSWORD=   (Rishi enters this)
       SESSION_DRIVER=database
       SESSION_SECURE_COOKIE=true
       MAIL_MAILER=log   (until Microsoft 365 sending is set up; see below)
       MAIL_FROM_ADDRESS=portal@jobsavvy.co.uk
       MAIL_FROM_NAME="Compass MEP Portal"

   Then:

       php artisan key:generate --force
       php artisan migrate --force
       php artisan db:seed --force
       php artisan config:cache && php artisan route:cache && php artisan view:cache
       chmod -R ug+rwX storage bootstrap/cache

3. Expose it at /portal. In the subdomain folder, move the static placeholder aside and link the app's
   public folder in its place:

       cd ~/compass-mep.jobsavvy.co.uk        # the subdomain's document root
       mv portal portal-placeholder-old
       ln -s ~/compass-portal/portal-app/public portal

   Check `https://compass-mep.jobsavvy.co.uk/portal` shows the login page.

4. Create the first admin. This prints an invite link (valid 7 days):

       cd ~/compass-portal/portal-app
       php artisan portal:invite-admin rishi.rao@compass-mep.com "Rishi Rao"

   Open the link, set a password, then scan the QR code with an authenticator app.

## Updates

    cd ~/compass-portal && git pull
    cd portal-app && composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan config:cache && php artisan route:cache && php artisan view:cache

Never run `migrate:fresh` or `db:wipe` on the server: they delete all data.

## Sending email (invites and notifications)

For now the portal sends from Rishi's Microsoft 365 mailbox, and replies come back to it (set up
Outlook rules for them). Later, point replies at a helpdesk mailbox by changing only
MAIL_REPLY_TO_ADDRESS, or switch the sender to a dedicated mailbox such as portal@jobsavvy.co.uk.

In `.env` on the server:

    MAIL_MAILER=smtp
    MAIL_HOST=smtp.office365.com
    MAIL_PORT=587
    MAIL_SCHEME=null
    MAIL_USERNAME=rishi.rao@compass-mep.com
    MAIL_PASSWORD=   (typed in by Rishi on the server only, never committed)
    MAIL_FROM_ADDRESS=rishi.rao@compass-mep.com
    MAIL_FROM_NAME="Compass MEP Portal"
    MAIL_REPLY_TO_ADDRESS=rishi.rao@compass-mep.com

Then `php artisan config:cache`.

Microsoft 365 must allow the mailbox to send this way. In the Microsoft 365 admin centre: Users >
Active users > Rishi Rao > Mail > Manage email apps > tick **Authenticated SMTP**. If the Compass
tenant has Security Defaults or blocks SMTP AUTH (common when two-step login is on), Compass's
Microsoft 365 administrator needs to allow it for this mailbox, or provide a dedicated mailbox for the
portal. Until it works, set `MAIL_MAILER=log`: invites are then shown on screen as a link to copy and
send by hand, so nobody is blocked.

## Completion reports

When a job is marked complete, a PDF completion report is created automatically and attached to the
job (stored under `storage/app/private/reports/`). Compass staff, the job's client and the partner
who did the work can download it at `/portal/jobs/{id}/completion-report.pdf`. It shows no prices.
A sample is in `docs/sample-completion-report.pdf`. Back up `storage/app/private` along with the
database.

## Scheduled tasks (cron job, once)

The portal runs small background tasks (for example retrying SharePoint copies every 15 minutes).
In cPanel → **Cron Jobs** → Add New Cron Job, "Once Per Minute" (`* * * * *`), command:

    cd ~/compass-portal/portal-app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1

(If `php -v` on the server shows a different PHP than 8.3, use the path cPanel's MultiPHP gives for 8.3,
e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.)

## Microsoft 365 (sign-in, SharePoint, Dataverse)

See `MICROSOFT.md`. After an update that adds it, run `php artisan migrate --force` (adds one column
to users) and then `php artisan portal:microsoft-check`.

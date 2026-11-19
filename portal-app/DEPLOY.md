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

## Sending email (invites)

Until email is set up, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` instead of sending
them; `portal:invite-admin` prints its link on screen. jobsavvy.co.uk's email is on Microsoft 365, so
emails must be sent through Microsoft 365 (sending from the hosting server would fail SPF checks and
land in spam). Needed: a mailbox such as portal@jobsavvy.co.uk with SMTP AUTH enabled, then:

    MAIL_MAILER=smtp
    MAIL_HOST=smtp.office365.com
    MAIL_PORT=587
    MAIL_SCHEME=null
    MAIL_USERNAME=portal@jobsavvy.co.uk
    MAIL_PASSWORD=   (entered on the server only)

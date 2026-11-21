# Connecting the portal to Microsoft 365

One **app registration** in Compass MEP's Microsoft Entra ID gives the portal three things:

| Connection | What it does | Turned on by |
|---|---|---|
| Sign in with Microsoft | Compass staff log in with their Microsoft 365 account (and Microsoft's MFA). Clients and partners keep email + password + authenticator app. | `MICROSOFT_SIGN_IN=true` |
| SharePoint | Every completion report is copied to `<site>/<library>/Portal/Completion reports/<Client>/<Contract>/`. Failed copies retry every 15 minutes. | `SHAREPOINT_SITE=...` |
| Dataverse CRM | The portal can read and write CRM tables (accounts, contacts, opportunities, custom tables) as its own application user. | `DATAVERSE_URL=...` |

Each part is optional and can be set up on its own. Nothing changes in the portal until the settings
are in `.env`. You need to be a Microsoft 365 **Global Administrator** (or Application Administrator +
SharePoint Administrator + Power Platform Administrator) for Compass MEP's tenant.

> These steps are for **Compass MEP's own** Microsoft 365. If a SharePoint site or Dataverse
> environment belongs to Wates, Wates's IT has to do steps 1, 3 and 4 in their tenant instead.

---

## 1. Register the app (Microsoft Entra admin centre), about 10 minutes

1. Go to <https://entra.microsoft.com> → **Identity → Applications → App registrations → New registration**.
2. Name: `Compass MEP Portal`.
   Supported account types: **Accounts in this organizational directory only (single tenant)**.
   Redirect URI: platform **Web**, address
   `https://compass-mep.jobsavvy.co.uk/portal/login/microsoft/callback`
   → **Register**.
3. On the **Overview** page, copy:
   - **Application (client) ID** → `MICROSOFT_CLIENT_ID`
   - **Directory (tenant) ID** → `MICROSOFT_TENANT_ID`
4. **Certificates & secrets → Client secrets → New client secret**. Description `portal`, expiry 24 months.
   Copy the **Value** straight away (it is shown once) → `MICROSOFT_CLIENT_SECRET`.
   Put a reminder in your calendar a month before it expires; see "Renewing the secret" below.
5. **API permissions**: `User.Read` (delegated) is already there, which is all sign-in needs.
   For SharePoint add: **Add a permission → Microsoft Graph → Application permissions → `Sites.Selected`**
   → **Grant admin consent for Compass MEP**.
   (Dataverse needs no permission here; it's granted in step 4.)

## 2. Make sure Microsoft MFA is on

Microsoft sign-in replaces the portal's authenticator-app step for Compass staff, so Microsoft must
ask for MFA itself. In Entra: **Identity → Overview → Properties → Manage security defaults** = Enabled,
or a Conditional Access policy requiring MFA for all users. (If you already get an Authenticator prompt
when you log in to Outlook, this is on.)

## 3. Give the app access to one SharePoint site

`Sites.Selected` means the app can only reach sites you name, not all of SharePoint.

1. Choose or create the site, e.g. `https://compassmep.sharepoint.com/sites/Operations`.
2. Open <https://developer.microsoft.com/graph/graph-explorer> and sign in as the admin.
   (First time: click your profile → **Consent to permissions**, search `Sites.FullControl.All`, Consent.)
3. Get the site ID: **GET**
   `https://graph.microsoft.com/v1.0/sites/compassmep.sharepoint.com:/sites/Operations`
   → copy the `id` from the response.
4. Grant the portal write access: **POST**
   `https://graph.microsoft.com/v1.0/sites/<site id>/permissions`
   with request body:

   ```json
   {
     "roles": ["write"],
     "grantedToIdentities": [{ "application": { "id": "<MICROSOFT_CLIENT_ID>", "displayName": "Compass MEP Portal" } }]
   }
   ```

   A `201 Created` response means it worked.

Settings:

    SHAREPOINT_SITE=compassmep.sharepoint.com:/sites/Operations
    SHAREPOINT_LIBRARY=Documents
    SHAREPOINT_FOLDER="Portal/Completion reports"

(`Documents` is the default library, shown as "Shared Documents" in some places.)

## 4. Give the app access to Dataverse

1. <https://admin.powerplatform.microsoft.com> → **Environments** → your CRM environment.
   Copy the **Environment URL** (e.g. `https://compassmep.crm11.dynamics.com`) → `DATAVERSE_URL`.
2. **Settings → Users + permissions → Application users → New app user → Add an app** → pick
   `Compass MEP Portal`. Business unit: the top one.
3. Security role: for now **Basic User** plus read/write on the tables the portal should use.
   Best practice is a custom role (copy Basic User, name it `Portal integration`) so the portal can
   only touch what it needs. Application users don't use a paid licence.

## 5. Put the settings on the server

SSH into MilesWeb (cPanel → **Terminal**), then:

    cd ~/compass-portal/portal-app
    nano .env

Add (the secret is typed in here only; never email it or commit it):

    MICROSOFT_TENANT_ID=...
    MICROSOFT_CLIENT_ID=...
    MICROSOFT_CLIENT_SECRET=...
    MICROSOFT_SIGN_IN=true
    SHAREPOINT_SITE=compassmep.sharepoint.com:/sites/Operations
    SHAREPOINT_LIBRARY=Documents
    SHAREPOINT_FOLDER="Portal/Completion reports"
    DATAVERSE_URL=https://compassmep.crm11.dynamics.com

Then:

    php artisan config:cache
    php artisan portal:microsoft-check

Each line shows ✔ working, – not set up, or ✘ with Microsoft's reason. The same test is in the
portal under **Microsoft 365 → Test connections** (Compass admins only).

Make sure the scheduler cron job from DEPLOY.md is set up: it retries SharePoint copies.

## How Microsoft sign-in matches people

- The person must already have portal access (invited by a Compass admin) **and** belong to the
  Compass MEP organisation in the portal. Wates and partner users can't use it.
- First time: matched on email (their Microsoft sign-in name). After that, on their Microsoft account ID,
  so renaming an email later doesn't break it.
- An invited Compass user can skip the invite email and just sign in with Microsoft.
- Removing someone's access in the portal stops them at once, even if their Microsoft account still works.
  Disabling their Microsoft account stops Microsoft sign-in, but **not** email + password if they set one:
  remove their portal access too when someone leaves.

## Renewing the secret

Entra → App registrations → Compass MEP Portal → Certificates & secrets → New client secret.
Put the new value in `.env`, run `php artisan config:cache` and `php artisan portal:microsoft-check`,
then delete the old secret in Entra.

## Turning it off

Set `MICROSOFT_SIGN_IN=false` (or blank out `SHAREPOINT_SITE` / `DATAVERSE_URL`) and run
`php artisan config:cache`. Email + password logins keep working throughout.

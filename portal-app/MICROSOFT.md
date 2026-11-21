# Connecting the portal to Microsoft 365

One **app registration** in your company's Microsoft Entra ID gives the portal three things:

| Connection | What it does | Turned on by |
|---|---|---|
| Sign in with Microsoft | Your own staff log in with their Microsoft 365 account (and Microsoft's MFA). Clients and partners keep email + password + authenticator app. | `MICROSOFT_SIGN_IN=true` |
| SharePoint | Every completion report is copied to `<site>/<library>/Portal/Completion reports/<Client>/<Contract>/`. Failed copies retry every 15 minutes. | `SHAREPOINT_SITE=...` |
| Outlook & To Do (CRM) | Each your company person connects their own mailbox and To Do: CRM tasks sync both ways with a To Do list called "Maintenance Portal", and company/contact pages show their Outlook emails with those people. | Each person clicks **Connect** on the Tasks page (or just uses Microsoft sign-in) |
| Dataverse CRM | Used once, to import the old CRM into the portal's own CRM (`php artisan portal:crm-import`). | `DATAVERSE_URL=...` |

Each part is optional and can be set up on its own. Nothing changes in the portal until the settings
are in `.env`. You need to be a Microsoft 365 **Global Administrator** (or Application Administrator +
SharePoint Administrator + Power Platform Administrator) for your company's tenant.

> These steps are for **your own** Microsoft 365. If a SharePoint site or Dataverse
> environment belongs to the client, the client's IT has to do steps 1, 3 and 4 in their tenant instead.

---

## 1. Register the app (Microsoft Entra admin centre), about 10 minutes

1. Go to <https://entra.microsoft.com> → **Identity → Applications → App registrations → New registration**.
2. Name: `Maintenance Portal`.
   Supported account types: **Accounts in this organizational directory only (single tenant)**.
   Redirect URI: platform **Web**, address
   `https://portal.example.com/portal/login/microsoft/callback`
   → **Register**.
3. On the **Overview** page, copy:
   - **Application (client) ID** → `MICROSOFT_CLIENT_ID`
   - **Directory (tenant) ID** → `MICROSOFT_TENANT_ID`
4. **Certificates & secrets → Client secrets → New client secret**. Description `portal`, expiry 24 months.
   Copy the **Value** straight away (it is shown once) → `MICROSOFT_CLIENT_SECRET`.
   Put a reminder in your calendar a month before it expires; see "Renewing the secret" below.
5. **API permissions** → **Add a permission → Microsoft Graph**:
   - **Delegated permissions** (each person's own data, only when they connect): `offline_access`,
     `Mail.Read`, `Tasks.ReadWrite` (`User.Read`, `openid`, `profile`, `email` are already there or added the same way).
   - **Application permissions** (the portal itself): `Sites.Selected` for SharePoint.
   - Then **Grant admin consent for your company** so staff aren't each asked to approve.
   (Dataverse needs no permission here; it's granted in step 4.)

   `Mail.Read` lets the portal *read* the connected person's own mailbox to show emails with a client;
   it can't send, and nothing is stored unless they click "Save to record". `Tasks.ReadWrite` covers their
   To Do. Nobody's mailbox is reachable unless they connect it themselves.

## 2. Make sure Microsoft MFA is on

Microsoft sign-in replaces the portal's authenticator-app step for your own staff, so Microsoft must
ask for MFA itself. In Entra: **Identity → Overview → Properties → Manage security defaults** = Enabled,
or a Conditional Access policy requiring MFA for all users. (If you already get an Authenticator prompt
when you log in to Outlook, this is on.)

## 3. Give the app access to one SharePoint site

`Sites.Selected` means the app can only reach sites you name, not all of SharePoint.

1. Choose or create the site, e.g. `https://yourcompany.sharepoint.com/sites/Operations`.
2. Open <https://developer.microsoft.com/graph/graph-explorer> and sign in as the admin.
   (First time: click your profile → **Consent to permissions**, search `Sites.FullControl.All`, Consent.)
3. Get the site ID: **GET**
   `https://graph.microsoft.com/v1.0/sites/yourcompany.sharepoint.com:/sites/Operations`
   → copy the `id` from the response.
4. Grant the portal write access: **POST**
   `https://graph.microsoft.com/v1.0/sites/<site id>/permissions`
   with request body:

   ```json
   {
     "roles": ["write"],
     "grantedToIdentities": [{ "application": { "id": "<MICROSOFT_CLIENT_ID>", "displayName": "Maintenance Portal" } }]
   }
   ```

   A `201 Created` response means it worked.

Settings:

    SHAREPOINT_SITE=yourcompany.sharepoint.com:/sites/Operations
    SHAREPOINT_LIBRARY=Documents
    SHAREPOINT_FOLDER="Portal/Completion reports"

(`Documents` is the default library, shown as "Shared Documents" in some places.)

## 4. Give the app access to Dataverse

1. <https://admin.powerplatform.microsoft.com> → **Environments** → your CRM environment.
   Copy the **Environment URL** (e.g. `https://yourorg.crm11.dynamics.com`) → `DATAVERSE_URL`.
2. **Settings → Users + permissions → Application users → New app user → Add an app** → pick
   `Maintenance Portal`. Business unit: the top one.
3. Security role: for now **Basic User** plus read/write on the tables the portal should use.
   Best practice is a custom role (copy Basic User, name it `Portal integration`) so the portal can
   only touch what it needs. Application users don't use a paid licence.

## 5. Put the settings on the server

SSH into MilesWeb (cPanel → **Terminal**), then:

    cd ~/portal/portal-app
    nano .env

Add (the secret is typed in here only; never email it or commit it):

    MICROSOFT_TENANT_ID=...
    MICROSOFT_CLIENT_ID=...
    MICROSOFT_CLIENT_SECRET=...
    MICROSOFT_SIGN_IN=true
    SHAREPOINT_SITE=yourcompany.sharepoint.com:/sites/Operations
    SHAREPOINT_LIBRARY=Documents
    SHAREPOINT_FOLDER="Portal/Completion reports"
    DATAVERSE_URL=https://yourorg.crm11.dynamics.com

Then:

    php artisan config:cache
    php artisan portal:microsoft-check

Each line shows ✔ working, – not set up, or ✘ with Microsoft's reason. The same test is in the
portal under **Microsoft 365 → Test connections** (admins only).

Make sure the scheduler cron job from DEPLOY.md is set up: it retries SharePoint copies.

## Moving the old CRM across (once)

The portal has its own CRM now (Companies, Contacts, Pipeline & tenders, Tasks). To bring over what's in
Dataverse, after steps 1, 4 and 5:

    php artisan portal:crm-import --dry-run    # shows how many of each it found; saves nothing
    php artisan portal:crm-import

It brings over active accounts (as companies: Customer → client, Supplier/Vendor → supplier, others →
prospect), contacts, opportunities (open ones as "Qualifying", plus won/lost), open tasks, and notes,
phone calls and appointments as history. Owners are matched to portal users by email. Companies whose
name matches one already in the portal (e.g. The client) are linked, not duplicated. Running it again updates
rather than duplicates, so you can import, keep using Dataverse for a few days, and import again before
switching off.

## Outlook & To Do for each person

- On the **Tasks** page, click **Connect**, and sign in with your own work Microsoft 365 account.
  (Staff who log in with "Sign in with Microsoft" are connected automatically.)
- A list called **Maintenance Portal** appears in Microsoft To Do (and Outlook's tasks). Tasks assigned
  to you in the portal appear there; ticking off, renaming or changing the due date in either place
  updates the other within 5 minutes (or click **Sync now**). Tasks you add to that list in To Do appear in
  the portal. Reassigning a task moves it to the other person's list.
- Company and contact pages show your Outlook emails with those people; **Save to record** adds one
  to the shared history.
- **Disconnect** on the Tasks page stops it. Changing your Microsoft password or an admin revoking access
  also disconnects you; just connect again.

## How Microsoft sign-in matches people

- The person must already have portal access (invited by a admin) **and** belong to the
  your company organisation in the portal. The client and partner users can't use it.
- First time: matched on email (their Microsoft sign-in name). After that, on their Microsoft account ID,
  so renaming an email later doesn't break it.
- An invited your company user can skip the invite email and just sign in with Microsoft.
- Removing someone's access in the portal stops them at once, even if their Microsoft account still works.
  Disabling their Microsoft account stops Microsoft sign-in, but **not** email + password if they set one:
  remove their portal access too when someone leaves.

## Renewing the secret

Entra → App registrations → Maintenance Portal → Certificates & secrets → New client secret.
Put the new value in `.env`, run `php artisan config:cache` and `php artisan portal:microsoft-check`,
then delete the old secret in Entra.

## Turning it off

Set `MICROSOFT_SIGN_IN=false` (or blank out `SHAREPOINT_SITE` / `DATAVERSE_URL`) and run
`php artisan config:cache`. Email + password logins keep working throughout.

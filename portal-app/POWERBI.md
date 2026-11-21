# Power BI, Power Automate and agents

## 1. Management reports in the portal

Portal menu: **Resources → Management reports**. admins add reports and choose which roles see them.

1. In Power BI (app.powerbi.com), open the report → **File → Embed report → Website or portal** and copy the
   link. (The report's address from the browser bar works too.) **Never use "Publish to web"**: it makes the
   report public on the internet.
2. In the portal: **Management reports → Add a Power BI report**, paste it, tick who sees it.
3. Share the report (or its workspace) in Power BI with the managers who should see it.

Viewers sign in to Power BI with their work Microsoft account (they have Pro/E5 licences). Power BI's own
permissions and row-level security still apply. The portal only decides who sees the link.

## 2. Getting portal data into Power BI

The portal has a read-only data API. A admin creates a key under **Microsoft 365 → API keys**
(name it "Power BI"). The key is shown once.

Tables (each returns `{"value": [ ... ]}`):

| URL | One row per |
|---|---|
| `/api/v1/jobs` | job (ref, client, contract, site, priority, status, amounts, dates) |
| `/api/v1/companies`, `/contacts`, `/activities` | CRM company / person / note-call-meeting-email |
| `/api/v1/opportunities` | enquiry or tender (stage, value, weighted value, return date, won/lost) |
| `/api/v1/tasks` | task (status, due, overdue, who) |
| `/api/v1/projects` | project (days allowed and planned) |
| `/api/v1/staff` | person in the resource planner |
| `/api/v1/allocations` | person × project × week (days). Optional `?from=2026-01-01&to=2026-12-31` |
| `/api/v1/utilisation` | person × week (days booked, available, %) |

In Power BI Desktop: **Get data → Blank query → Advanced editor**, for example:

    let
        Source = Json.Document(Web.Contents("https://portal.example.com/portal/api/v1/utilisation", [ApiKeyName = "key"])),
        Rows = Table.FromRecords(Source[value])
    in
        Rows

When asked for credentials choose **Web API** and paste the key. Repeat for each table (or copy the query and
change the last part of the address). Publish, then in the Power BI service set the dataset's credentials to
the same Web API key and schedule refresh (e.g. 8 times a day). No gateway is needed: the portal is on the
internet.

Revoke a key on the Microsoft 365 page at any time. Anything using it stops immediately.

## 3. Power Apps / Power Automate, and agents later

The portal is the single place the data lives. Rather than a Power App with its own copy, connect Microsoft's
tools to the portal:

- **Power Automate** can call the API with the HTTP action (premium) or a custom connector, e.g. "every Monday,
  email each manager their week from /api/v1/allocations".
- **Agents** (Claude or Copilot Studio) use the same API and key: "who's free next week for a 3-day TSM job?",
  "which tenders are due back this fortnight?".

Today the API is read-only. Write actions (create a task, log a call, plan a week) are the next step, added
one at a time with their own permissions, so an agent or flow can only do what it's been allowed to.

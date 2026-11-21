# Resource planner (replaces the Resources Tracker spreadsheet)

Portal menu: **Resources → Resource planner** and **Projects**. Compass admins change the plan;
Compass admin, helpdesk and finance staff can view it.

## What changed for Mike

| In the spreadsheet | In the portal |
|---|---|
| A new project had to be typed into every person's sheet (16 sheets; no sheet had the full list) | Add it once under **Projects → New project**. It's available for everyone straight away. |
| A new person meant copying a sheet, renaming it and linking their total row into "Utilisation" by hand (5 people were never linked) | **People → Add a person**. They appear in the planner immediately. Set a start date for new starters. |
| Typing the same figure across 10–20 week columns | **Plan several weeks at once**: person, project, days a week, from and to. |
| Extending the date columns every few weeks | Weeks run on forever. Use the ← / → buttons. |
| Utilisation summary built from cell links | Always up to date and colour-coded. Over 100% shows red, including on the project page while you plan. |
| Fractions of a week (0.2) | Days (1). Half days allowed. Part-time people get their own working days per week. |

Also: days allowed vs days planned per project, a project page showing everyone on it, and **Download
for Excel** (CSV) for anyone who still wants a spreadsheet.

## Moving the spreadsheet in (once)

Upload the latest copy of the tracker to the server (e.g. with cPanel File Manager into `~/`), then:

    cd ~/compass-portal/portal-app
    php artisan migrate --force
    php artisan portal:resources-import ~/Compass_MEP_Resources_Tracker.xlsx --dry-run
    php artisan portal:resources-import ~/Compass_MEP_Resources_Tracker.xlsx

- **People** come from each person's sheet name (the name cell is ignored: several sheets still say
  "Reuben Bisla"). Position and team come from the Utilisation sheet. Hidden sheets come in as not shown.
- **Projects** come from one combined list: the master list (lead, type, client, completion) plus every project
  typed into any sheet. They're matched by project number, or by name for "TBC" projects.
- **Figures** are converted from fractions of a week to days (0.2 → 1 day, 0.1 → ½ day).
- It's safe to run again with a newer copy. It updates rather than duplicates, so Mike can keep using the
  spreadsheet until the switch-over and import once more on the day.

Checked against the October tracker: 16 people, 50 projects, 699 weekly entries. Every person's weekly total
(1,024 of them) matches the spreadsheet's own Total rows exactly.

After importing: set start dates for new starters (e.g. Mick Shipp, Danny Akers), working days for anyone
part-time, and days allowed on projects (the spreadsheet's master list had none filled in).

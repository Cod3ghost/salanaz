# Salanaz Estate Platform

Public marketing website plus a role-based estate management system for
**Salanaz Global Services Ltd**.

The project is deliberately split in two:

| Component | Path | Responsibility |
| --- | --- | --- |
| **Theme** | `wp-content/themes/salanaz` | Presentation only — templates, styles, markup. |
| **Plugin** | `wp-content/plugins/salanaz-estate` | All business logic — roles, post types, tables, payments, automation, dashboards. |

Nothing in `functions.php` knows about transactions, roles or payments. The
site's look can be replaced without taking the estate system down.

---

## Previewing locally

There is no PHP, MySQL or Docker on this machine, so previews run through
[WordPress Playground](https://wordpress.github.io/wordpress-playground/) —
real WordPress on PHP 8.3 compiled to WebAssembly, backed by SQLite, running
inside Node.

```bash
npm install     # once
npm run preview
```

Then open **<http://127.0.0.1:9400>**. You are logged in as `admin` /
`password` automatically.

The theme and plugin folders are **mounted live**: edit a PHP, CSS or JS file
and refresh the browser to see the change. No rebuild step.

- `npm run preview:debug` — same, with verbose Playground logging.
- The site is rebuilt from `tools/playground/blueprint.json` on every boot, so
  the database resets each start. That is intentional: every run is a clean,
  reproducible install, and the blueprint re-runs the demo seeder automatically.
- Harmless `lockWholeFile: unlock failed` lines on Windows can be ignored.

### Test accounts

Seeded on every boot. All use the password `password`.

| Login | Role | Account status |
| --- | --- | --- |
| `admin` | Administrator | — |
| `cofounder` | Co-Founder | approved |
| `staff1`, `staff2` | Sales Staff | approved |
| `client1`, `client2` | Client | approved |
| `client3` | Client | **pending** — for testing the approval flow |

### Demo data

`Salanaz_Demo_Data::seed()` creates 4 estates, 86 plots (73 available, 4
reserved, 9 sold), the six test accounts, standard pages, three menus and three
news posts. It is **idempotent** and **never runs automatically** — it is
invoked explicitly from the blueprint, so a production site can carry the
plugin with no risk of demo inventory appearing.

> Playground is for development only. Production runs on ordinary
> PHP + MySQL hosting; nothing in the theme or plugin depends on Playground.

---

## Build progress

| Batch | Scope | Status |
| --- | --- | --- |
| 0 | Preview environment | ✅ Done |
| 1 | Data model — roles, CPTs, custom tables, profile meta | ✅ Done |
| 2 | Auth flow — registration, approval, staff accounts, assignment | ✅ Done |
| 3 | Public website templates + estate/plot listing | ✅ Done |
| 4 | Client dashboard — browse, select, manual payment upload | ✅ Done |
| 5 | Co-founder dashboard — verification queue, assignment, inventory | ✅ Done |
| 6 | Sales staff dashboard | ✅ Done |
| 7 | Paystack integration + webhook verification | ✅ Built — live API calls untested (no keys, no network in Playground) |
| 8 | PDF receipts + allocation letters | ✅ Done |
| 9 | Email automation + cron reminders | ✅ Done |
| 10 | Analytics / reporting | ✅ Done |
| 11 | Seed data, docs, per-role test checklists | ✅ Done |

Everything above is verified working in the local preview, with one exception:
**Paystack's two live API calls have never run** — there are no keys in this
build and the preview has no outbound networking. Work through section 6 of
[TESTING.md](TESTING.md) with test keys before going live.

---

## Public site

| Template | Covers |
| --- | --- |
| `front-page.php` | Home — hero + plot search, stat bar, featured estates, value props, process, payment plans, testimonials, FAQ, news, CTA |
| `archive-salanaz_estate.php` | Estates listing with the filter bar, result count, pagination and empty state |
| `single-salanaz_estate.php` | Estate detail — hero, key facts, amenities, plot availability table, map link, reservation sidebar |
| `single.php` / `page.php` / `index.php` / `404.php` | News posts, standard pages, fallback, not-found |

Public browse filters (`estate_location`, `min_size`, `max_price`, plus
`min_price` / `max_size` / `plot_status` / `sort`) are parsed and applied by
`Salanaz_Query` in the plugin via `pre_get_posts`, **not** by the theme — the
meta keys stay with the data model.

Because size and price describe *plots* rather than *estates*, filtering the
estates archive resolves to the set of estates owning at least one matching
plot. An empty match set uses a `post__in` sentinel so "no results" cannot
silently fall back to showing everything.

Estate and plot imagery falls back to a branded inline-SVG placeholder seeded
by post ID, so listings render without stock photography and without any
external request. Adding a featured image overrides it.

---

## Auth flow

Clients and sales staff never see wp-admin. Login, registration and (from Batch
4) the dashboard are ordinary pages driven by shortcodes:

| Page | Shortcode | Notes |
| --- | --- | --- |
| `/login/` | `[salanaz_login]` | Wraps `wp_login_form()`; errors and the post-registration confirmation render above it |
| `/register/` | `[salanaz_register]` | Client self-registration; accepts `?plot=ID` to carry a reservation through signup |

`Salanaz_Auth` also filters `login_url`, redirects each role after sign-in,
hides the admin bar, and bounces clients and staff out of wp-admin.

### Registration → approval → assignment

1. Visitor registers. Account is created as `salanaz_client` with account status
   **pending**, NDPR consent is timestamped, and phone numbers are normalised to
   `+234…`.
2. Confirmation email to the client; alert to every co-founder.
3. Co-founder approves or rejects from **Salanaz → Approvals** in wp-admin.
   Approval emails a welcome; rejection emails the reason.
4. Co-founder assigns a sales officer from **Salanaz → Clients**. Both the staff
   member and the client are emailed the introduction.

**Pending clients can still sign in** — deliberately, so they have somewhere to
see their status. They simply hold no purchasing capabilities until approved.
**Rejected and suspended accounts are blocked at `authenticate`.**

Sales staff cannot self-register. A co-founder creates the account from
**Salanaz → Sales Staff**, which generates a one-time password and emails it.

Reassignment deactivates the previous `salanaz_assignments` row rather than
deleting it, so the history of who handled an account survives.

### Security measures on this flow

- Nonce verification on every form, plus `check_admin_referer()` on the
  admin-post router.
- Every operation in `Salanaz_Users` **re-checks its own capability** rather
  than trusting the caller, so the nonce is not the only line of defence.
- Registration is rate-limited to 5 attempts per IP per hour, with a honeypot
  field positioned off-screen rather than `display:none`.
- Passwords are never sanitised (only validated) so they are not silently
  altered, and are never written to the validation-state transient.
- Every approval, rejection, staff creation and assignment is written to
  `salanaz_activity_log` with the acting user and IP.

### Emails

`Salanaz_Notifications` sends branded HTML mail through `wp_mail()`. Batch 2
covers the account-lifecycle messages; payment and installment mail arrives in
Batch 9 with the cron wiring.

> Route `wp_mail()` through a real SMTP service (Brevo, Mailgun). Shared-hosting
> `mail()` is routinely dropped by Gmail and Yahoo, which is where most clients
> read their mail.

---

## Client dashboard

`/dashboard/` carries `[salanaz_dashboard]` and is role-aware: clients get the
purchase journey, sales staff get their assigned client list, and co-founders
are pointed at wp-admin.

### The purchase journey

1. **Browse** — estate pages list plots; each available plot has a Reserve
   button. Signed-out visitors are routed through registration, which carries
   the plot id so it survives signup.
2. **Reserve** — the client picks a payment plan. The plot flips to *reserved*
   and, for an installment plan, the schedule is generated immediately.
3. **Pay** — the manual route shows the corporate bank details, then takes an
   amount and a proof file. The transaction lands as *pending verification*.
4. **Track** — the overview shows a progress bar, amount paid, outstanding
   balance, next due date, the full schedule and payment history.

Pending or rejected accounts see a status screen instead, and hold no
purchasing capabilities.

### Payment plans

Defined in `Salanaz_Plans` rather than per plot, so a commercial term can change
without editing inventory. A generated schedule is a snapshot — later changes to
the catalogue never alter an existing client's plan.

| Plan | Deposit | Term | Carrying charge |
| --- | --- | --- | --- |
| Outright | 100% | — | — |
| 6 months | 30% | 6 monthly | none |
| 12 months | 30% | 12 monthly | none |
| 24 months | 20% | 24 monthly | 10% |

Rounding gives every instalment the same rounded amount and pushes the
remainder onto the final one, so deposit + schedule always equals the total
exactly.

### Payment proof storage

Proofs are financial documents, so they never enter the media library.

- **Validation**: content is sniffed with `finfo` and cross-checked against
  `wp_check_filetype_and_ext()`. Only JPG, PNG, WebP and PDF are accepted — no
  SVG, which can carry script. Size is capped at 5 MB.
- **Naming**: the original filename is discarded; files are stored as
  `{client_id}-{24 random chars}.{ext}` under a `YYYY/MM` folder.
- **Location**: by default a directory *above* the web root, which no server
  can serve whatever its configuration. Only if that is not writable does it
  fall back inside `wp-content/uploads`, guarded by `.htaccess`, `web.config`
  and `index.php`.
- **Override**: define `SALANAZ_PRIVATE_DIR` (or filter `salanaz_private_dir`)
  to pin storage to a path you know is durable.
- **Serving**: never by URL. `?salanaz_proof={id}` requires a login, a
  per-transaction nonce, and one of: owning the transaction, holding
  `salanaz_verify_payments`, or being the client's assigned officer. The
  resolved path is re-checked against the storage root so a tampered database
  value cannot escape it.

> **nginx**: `.htaccess` is ignored entirely. If proofs fall back inside the web
> root, add an explicit rule. A warning appears in wp-admin whenever storage is
> inside the web root:
>
> ```nginx
> location ~* /wp-content/salanaz-private-.* { deny all; return 404; }
> ```

---

## Sales staff dashboard

Sales staff share `/dashboard/` with clients; the shortcode routes by role.

| View | URL | Shows |
| --- | --- | --- |
| Overview | `/dashboard/` | Assigned clients, collected vs outstanding totals, follow-ups due |
| Client file | `/dashboard/?view=client&client={id}` | Plots and payment progress, notes timeline, payment history, contact and next-of-kin |

### Notes and follow-ups

An officer logs an interaction against a client — general note, phone call,
WhatsApp, meeting or site inspection — with an optional follow-up date. Dated
notes surface in the Follow-ups panel on the overview, ordered by date, with
anything due today or earlier highlighted and counted in a badge.

Only the **most recent** dated note per client appears there, so the panel shows
one row per client rather than every follow-up ever set.

### Containment

An officer must never see another officer's client. Both the client-file view
and `Salanaz_Notes` call `Salanaz_Users::staff_can_view_client()`, which passes
only for a holder of `salanaz_view_all_clients` or the officer that client is
actively assigned to.

That check sits **inside the notes repository**, not only in the view, so it
holds even against a request carrying a legitimate nonce — verified by taking a
valid nonce from one officer's own client file and posting it against another
officer's client: rejected, with nothing written.

Staff hold no capability to approve payments, create accounts or edit
inventory, and every wp-admin screen returns 403 for them.

---

## Payment verification

**Salanaz → Payments** in wp-admin is the verification queue. Each submitted
proof shows the client, plot, amount, payer note and a link to view the file.

### Verifying recalculates, it never increments

`Salanaz_Verification::reconcile()` rebuilds a client's whole position on a plot
from the sum of *verified* transactions:

1. Everything above the deposit flows into the schedule, oldest instalment
   first, each row marked `paid`, `partial`, `pending` or `overdue`.
2. The plan is marked `completed` once the total is met, `active` otherwise.
3. The plot flips to `sold` when fully paid, back to `reserved` when not.

Because it recalculates rather than adding, the operation is **idempotent**:
re-running it produces the same result, and **rejecting a payment that was
previously verified correctly gives back the credit** — the plan reopens, the
affected instalments revert, the plot returns to reserved and the ownership
marker is cleared.

> Reconciliation deliberately looks up the plan with `plan_for_plot()` rather
> than `active_plan()`. A completed plan is not "active", and a later correction
> still has to reopen it — using the status-filtered lookup here silently skips
> the reversal.

Both decisions email the client, and both are written to the activity log with
the acting co-founder and their IP.

---

## Analytics

**Salanaz → Analytics**, gated on `salanaz_view_analytics`.

| Section | Answers |
| --- | --- |
| Headline cards | Collected, outstanding, plots sold, overdue value |
| Needs attention | Payments waiting to be checked; approved clients with no officer |
| Revenue chart | Verified revenue per month, last 12 months |
| Sales by officer | Clients, plots held, collected and outstanding per staff member |
| Inventory by estate | Plot counts by status and the value still unsold |
| Overdue instalments | Who is late, by how long, and whose client they are |

Two definitions worth stating, because they are easy to get wrong:

- **Collected** counts *verified* transactions only. A payment sitting in the
  verification queue is not revenue.
- **Outstanding** is what is still owed on plots **currently held** by a client
  — not the value of everything unsold. Unsold stock is reported separately, per
  estate.

### Implementation notes

Aggregation happens in PHP over simple, portable `SELECT`s rather than in
clever SQL. Volumes here are hundreds to low thousands of rows, and the same
code has to behave identically on MySQL and on the SQLite translation layer used
for local development.

The portfolio walk is cached for five minutes and invalidated by
`salanaz_payment_verified`, `salanaz_payment_rejected`, `salanaz_paystack_verified`,
`salanaz_plot_allocated` and any plot save — so a verification is reflected
immediately rather than up to five minutes later.

The revenue chart is CSS bars with an accompanying screen-reader summary. No
charting library, so nothing to load from a CDN and nothing to keep patched.

---

## Automation and email

**Salanaz → Automation** shows when the sweep last ran, when it runs next, and
whether a real cron is driving it. It also has a **Run the sweep now** button,
which is how the behaviour below was tested.

### The daily sweep

Once a day (07:00 local) `Salanaz_Cron::run()` does three things:

| Trigger | Who is emailed |
| --- | --- |
| Instalment due in **7 days** | Client |
| Instalment due **tomorrow** | Client |
| Instalment **past its due date** | Client, their sales officer, and every co-founder |

An overdue instalment is also flipped to `overdue` status. The first notice goes
out as soon as it slips, then repeats **weekly** — one email rarely collects a
debt. Change that with the `salanaz_overdue_repeat_days` filter.

Each message stamps its own column on the instalment row
(`reminder_7d_sent_at`, `reminder_1d_sent_at`, `overdue_notice_sent_at`)
**before** sending, so a reminder is never sent twice even if cron fires more
than once. Runs are capped at 100 instalments so a sweep cannot time out.

### Use a real cron, not WP-Cron

WP-Cron only fires when somebody visits the site. On a quiet day, a reminder due
at 07:00 might not go out until the afternoon — or at all. For a system that
chases money, wire up a real cron:

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```cron
# Every 15 minutes — cPanel, or crontab -e
*/15 * * * * curl -s https://your-site/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

The Automation screen tells you which mode is active.

### SMTP is not optional

`wp_mail()` on shared hosting sends through PHP `mail()`, which Gmail and Yahoo
routinely drop — and that is where most Nigerian clients read their mail. Every
message this system sends is one somebody is waiting for, so install an SMTP
plugin (WP Mail SMTP, FluentSMTP) and point it at a real service:

- **Brevo** — generous free tier, good deliverability into Nigeria.
- **Mailgun** — pay as you go, strong reputation management.

Send yourself a test from the plugin, then approve a client and confirm the
welcome message actually arrives.

### Seeing email in development

Local environments rarely deliver mail, so when `SALANAZ_DEV_MODE` is defined
every outgoing message is recorded and listed on the Automation screen. It is
inert without the constant, so it never runs in production.

The demo seeder also builds a live installment plan whose instalments fall
overdue, due tomorrow and due in seven days — otherwise there is nothing for the
sweep to act on for a month, and the automation cannot be demonstrated.

---

## Documents

Every verified payment produces a **receipt**. The moment a plot becomes fully
paid it produces an **allocation letter**, fired from the `salanaz_plot_allocated`
action on the transition into `sold` — so a later reconcile never reissues it.

Samples of both live in `tools/samples/`.

### Why the PDF writer is hand-rolled

`Salanaz_PDF` writes PDF 1.4 directly rather than depending on dompdf or TCPDF.
That was a deliberate call: this has to run on ordinary Nigerian shared hosting
where Composer, GD and shell access are all unreliable, and a bundled library is
a maintenance liability. The cost is real and worth stating:

- Documents use the built-in Helvetica faces, encoded Windows-1252 — Latin-1
  only, no Unicode.
- **The Naira sign is not in that character set**, so documents print
  `NGN 3,800,000.00`. Formal Nigerian receipts spell the currency out anyway.
- Layout is explicit positioning, not flowed HTML.

If richer documents are ever needed, swap the writer for a real library behind
the same `Salanaz_Documents` API — nothing else has to change.

### Storage and access

Documents share the private directory with payment proofs and are served only
through `?salanaz_doc={type}&salanaz_id={id}` with a per-document nonce and a
capability check: the owning client, a holder of
`salanaz_view_all_transactions`, or the client's assigned sales officer. The
resolved path is re-checked against the storage root before anything is sent.

---

## Paystack

Card, bank transfer and USSD checkout, configured under **Salanaz → Settings**
(requires `manage_options`, so a co-founder cannot change keys).

### Setup

1. Enter your `pk_` / `sk_` keys, or better, define the secret in
   `wp-config.php` so it never sits in the database — the constant wins over
   the stored value:
   ```php
   define( 'SALANAZ_PAYSTACK_SECRET', 'sk_live_…' );
   define( 'SALANAZ_PAYSTACK_PUBLIC', 'pk_live_…' );
   ```
2. Paste the webhook URL shown on the settings screen into
   **Paystack → Settings → API Keys & Webhooks**:
   `https://your-site/wp-json/salanaz/v1/paystack-webhook`
3. Tick *Enable card payment*. The bank-transfer route stays available either
   way — if Paystack is off, the payment page simply shows transfer only.

### How money gets credited

Nothing the browser reports is trusted. A payment is credited only from a
**signed webhook** or a **direct verify call to Paystack**, both of which land
in the same `apply_gateway_result()`:

- The webhook is authenticated by `hash_hmac( 'sha512', $body, $secret )`
  compared with `hash_equals()` against the `x-paystack-signature` header.
- **The amount Paystack reports is authoritative**, not the amount posted from
  the form — so tampering with the form field cannot inflate a credit.
- Non-NGN payments are refused.
- An already-verified reference returns early, so the webhook and the
  return-URL verify racing each other cannot double-credit.
- Unknown references answer `200`, so Paystack stops retrying something that
  will never resolve; genuine processing failures answer `422` so it does retry.

Gateway credits are logged with no actor (`actor_id` null) and
`{"source":"paystack"}`, distinguishing them from a co-founder's manual
verification.

### What is and is not verified

The signature, idempotency, amount-tampering, currency, event-routing and
error-handling paths are all tested and passing. **The live API calls —
`transaction/initialize` and `transaction/verify` — have not been exercised**,
because this environment has no Paystack keys and Playground has no outbound
networking. Before go-live, run one real test-mode payment end to end and
confirm the webhook arrives.

---

## Inventory management

Estates and plots are edited through ordinary wp-admin screens, with meta boxes
supplying the commercial fields:

| Post type | Fields |
| --- | --- |
| Estate | Address, title document, latitude/longitude |
| Plot | Estate, plot number, size (sqm), price, availability |

The plots list table gains sortable Estate, Size, Price and Availability
columns. Setting a plot back to *Available* clears its held-by marker, and
changing a plot busts the cached figures for both its old and new estate.

Editing is gated on `salanaz_manage_inventory`, so sales staff can browse
inventory but cannot touch pricing. Every save re-checks the capability and the
nonce, and an estate id is only accepted if it really is an estate.

---

## Roles and capabilities

Three custom roles sit alongside the WordPress defaults. **Every endpoint gates
on a capability, never on a role name or `is_user_logged_in()`**, so
capabilities can be moved between roles without touching endpoint code.
Administrators receive all `salanaz_*` capabilities automatically.

| Capability | Co-Founder | Sales Staff | Client |
| --- | :---: | :---: | :---: |
| `salanaz_approve_clients` | ✅ | | |
| `salanaz_manage_staff` | ✅ | | |
| `salanaz_assign_clients` | ✅ | | |
| `salanaz_verify_payments` | ✅ | | |
| `salanaz_manage_inventory` | ✅ | | |
| `salanaz_view_inventory` | ✅ | ✅ | ✅ |
| `salanaz_view_all_clients` | ✅ | | |
| `salanaz_view_own_clients` | ✅ | ✅ | |
| `salanaz_view_all_transactions` | ✅ | | |
| `salanaz_view_own_transactions` | ✅ | ✅ | ✅ |
| `salanaz_add_client_notes` | ✅ | ✅ | |
| `salanaz_view_analytics` | ✅ | | |
| `salanaz_purchase_plot` | | | ✅ |
| `salanaz_upload_payment_proof` | | | ✅ |
| `salanaz_download_documents` | ✅ | | ✅ |

Role slugs: `salanaz_cofounder`, `salanaz_sales_staff`, `salanaz_client`.

Roles are reinstalled automatically whenever `SALANAZ_VERSION` changes, so
capability edits reach existing sites without a deactivate/reactivate cycle.

---

## Content model

Estates and plots are **custom post types** because they need a public face
(archives, single templates, galleries, media library, SEO).

| Post type | Slug | Archive | Notes |
| --- | --- | --- | --- |
| Estate | `salanaz_estate` | `/estates/` | Location, coordinates, gallery, amenities |
| Plot | `salanaz_plot` | `/plots/` | Belongs to an estate; size, price, status |

Taxonomies: `salanaz_location` (hierarchical) and `salanaz_amenity`, both on
Estate.

Plot status is one of `available`, `reserved`, `sold`.

---

## Database tables

Transactional data lives in dedicated tables rather than post meta so reporting
queries stay indexable as volume grows. All are prefixed `{wp_prefix}salanaz_`.

| Table | Purpose |
| --- | --- |
| `salanaz_transactions` | One row per payment attempt — Paystack or uploaded bank-transfer proof. Holds status, proof path, verifier and receipt path. |
| `salanaz_installment_plans` | Header record for a staged purchase: total, down payment, tenure, frequency. |
| `salanaz_installments` | Generated schedule rows. Reminder timestamps live here so cron never double-sends. |
| `salanaz_assignments` | Client-to-staff assignment history. Reassignment deactivates the old row rather than deleting it. |
| `salanaz_client_notes` | Sales staff notes and follow-ups against a client. |
| `salanaz_activity_log` | Audit trail of approvals, verifications and record access (NDPR accountability). |

Schema version is tracked in the `salanaz_db_version` option and upgraded on
`admin_init` when it falls behind `SALANAZ_DB_VERSION`.

---

## Profile fields

Stored as user meta and readable only by the account owner or a user holding
`salanaz_view_all_clients` / `salanaz_view_own_clients`:

phone (normalised to `+234…`), residential address, city/LGA, state, NIN,
occupation, and next-of-kin name / phone / relationship / address — the last
group because Nigerian land allocation contracts routinely require them.

Account status (`salanaz_account_status`) is one of `pending`, `approved`,
`rejected`, `suspended`. New client registrations start at `pending`.

---

## Hooks

### Actions

| Hook | Fires when | Arguments |
| --- | --- | --- |
| `salanaz_client_registered` | A visitor completes registration | `$user_id` |
| `salanaz_client_approved` | A co-founder approves a registration | `$client` (WP_User) |
| `salanaz_client_rejected` | A co-founder rejects a registration | `$client`, `$reason` |
| `salanaz_client_assigned` | A client is assigned a sales officer | `$client`, `$staff` |
| `salanaz_payment_submitted` | A client uploads proof of transfer | `$txn_id`, `$client_id` |
| `salanaz_payment_verified` | A co-founder verifies a payment | `$txn_id` |
| `salanaz_payment_rejected` | A co-founder rejects a payment | `$txn_id`, `$reason` |
| `salanaz_paystack_verified` | A gateway payment is credited | `$txn_id` |
| `salanaz_plot_allocated` | A plot becomes fully paid | `$client_id`, `$plot_id` |
| `salanaz_reminders_sent` | The daily sweep finishes | `$counts` |

`salanaz_payment_verified` and `salanaz_paystack_verified` both generate a
receipt; `salanaz_plot_allocated` generates the allocation letter. Anything that
moves money also flushes the reporting cache.

### Filters

| Hook | Controls | Default |
| --- | --- | --- |
| `salanaz_payment_plans` | The plan catalogue | Outright, 6, 12, 24 months |
| `salanaz_private_dir` | Where proofs and documents are stored | Above the web root when writable |
| `salanaz_overdue_repeat_days` | Days between repeat overdue notices | `7` |
| `salanaz_audit_ip_address` | IP recorded in the audit log | `REMOTE_ADDR` |

### Constants

| Constant | Purpose |
| --- | --- |
| `SALANAZ_PAYSTACK_SECRET` | Live secret key, kept out of the database |
| `SALANAZ_PAYSTACK_PUBLIC` | Live public key |
| `SALANAZ_PRIVATE_DIR` | Pin document storage to a known-durable path |
| `SALANAZ_DEV_MODE` | Enables the development mail log |

---

## Project layout

```
wp-content/
  themes/salanaz/            Presentation only
    front-page.php           Home
    archive-salanaz_estate.php, single-salanaz_estate.php
    inc/template-functions.php
    template-parts/          Cards, filter bar
    assets/css/main.css
  plugins/salanaz-estate/
    includes/
      class-salanaz-roles.php        Roles and capabilities
      class-salanaz-schema.php       Custom tables
      class-salanaz-post-types.php   Estate and Plot
      class-salanaz-inventory.php    Inventory queries
      class-salanaz-query.php        Public browse filters
      class-salanaz-auth.php         Registration, login, wp-admin lockout
      class-salanaz-users.php        Approval, staff, assignment
      class-salanaz-dashboard.php    Client and staff dashboards
      class-salanaz-plans.php        Payment plans and schedules
      class-salanaz-transactions.php Transaction repository
      class-salanaz-verification.php Payment verification and reconciliation
      class-salanaz-paystack.php     Gateway, webhook
      class-salanaz-uploads.php      Proof upload and serving
      class-salanaz-pdf.php          PDF writer
      class-salanaz-documents.php    Receipts and allocation letters
      class-salanaz-notes.php        Client notes and follow-ups
      class-salanaz-cron.php         Daily reminder sweep
      class-salanaz-reports.php      Analytics queries
      class-salanaz-notifications.php  All email
      class-salanaz-activity.php     Audit log
      admin/                         wp-admin screens and meta boxes
    templates/                       Overridable in yourtheme/salanaz/
tools/
  playground/blueprint.json          Local preview
  samples/                           Example receipt and allocation letter
TESTING.md                           Per-role manual test checklists
```

---

## Testing

Per-role manual checklists, a full end-to-end journey, security spot-checks and
a go-live list are in **[TESTING.md](TESTING.md)**.

---

## Security and data protection

- Payment proofs are written to `wp-content/uploads/salanaz-private-{random}/`,
  guarded by `.htaccess`, `web.config` and an `index.php` stub. The directory
  suffix is randomised per install so the path is not guessable.
- Uninstall is non-destructive by default. Tables and roles are only dropped
  when the `salanaz_delete_data_on_uninstall` option is set — an accidental
  plugin delete must not destroy client financial records.
- Deactivation never touches roles, tables or uploads.
- NDPR: personal and financial data access is capability-gated and written to
  `salanaz_activity_log`.

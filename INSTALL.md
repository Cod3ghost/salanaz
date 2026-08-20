# Installing on a live WordPress site

## Just upload one file

**`dist/salanaz-theme.zip`** is all you need.

**Appearance → Themes → Add New → Upload Theme** → choose it → Install →
**Activate**. That's it.

On activation the theme:

- installs and switches on the estate management plugin, which it carries
  inside itself,
- creates the roles, database tables and the `/login/`, `/register/` and
  `/dashboard/` pages,
- adds four sample estates with plots, so you land on a complete-looking site
  you can edit rather than an empty one.

Then go to **Settings → Permalinks** and click **Save**. This is the one manual
step — WordPress needs it before `/estates/` will resolve.

`dist/salanaz-estate.zip` is the same plugin on its own. You only need it if
your host blocks the automatic install (see below).

Requirements: **PHP 8.0+**, **WordPress 6.4+**, MySQL 5.7+/MariaDB 10.3+.

### Why is the system a separate plugin at all?

So your clients and their payment records do not live inside a theme. If you
ever redesign the site or switch themes, the estates, transactions, receipts
and accounts stay exactly where they are. You just never have to think about it,
because the theme installs it for you.

### If the automatic install fails

Some hosts lock the plugins folder. If that happens you will see a red banner
telling you so. Install `salanaz-estate.zip` by hand from
**Plugins → Add New → Upload Plugin**, and everything else proceeds normally.

---

## 2. Make it yours

Everything below is editable from wp-admin — no code.

### Appearance → Customise

| Section | What you can change |
| --- | --- |
| Site Identity | Your logo, site title, tagline |
| Salanaz → Contact details | Office address, phone, email, WhatsApp number, RC number |
| Salanaz → Home page | Every heading and paragraph on the home page |
| Salanaz → Brand colours | Primary navy and accent gold — shades derive automatically |
| Menus | Header, footer and legal menus |

Clearing a Home page field restores the wording the theme shipped with.

### Estates → and Plots →

Add your own inventory. Each estate takes a name, description, featured image,
address, title document and map coordinates. Each plot belongs to an estate and
takes a number, size, price and availability.

The sample estates are ordinary posts — edit them, or delete them and start
fresh.

### Salanaz → Settings

Bank details for the transfer route, Paystack keys, and a button to remove all
the demo content once you no longer need it.

---

## 3. Create your first co-founder

The plugin does not create accounts for you.

1. **Users → Add New**, set **Role** to **Co-Founder**.
2. Use a strong password and a real email address.
3. Sign in as that account and confirm you can reach **Salanaz → Approvals**.

Then create sales staff from **Salanaz → Sales Staff** — never from Users → Add
New, because that route skips the welcome email and the profile fields.

---

## 4. Configure it

### Email — do this before anything else

Every message the system sends is one somebody is waiting for: approvals,
receipts, payment reminders. `wp_mail()` on shared hosting goes out through PHP
`mail()`, which Gmail and Yahoo routinely drop.

1. Install **WP Mail SMTP** or **FluentSMTP**.
2. Connect **Brevo** or **Mailgun**.
3. Send the plugin's test email **to a Gmail address** and confirm it arrives in
   the inbox, not spam.

### Bank details for the transfer route

**Salanaz → Settings → Bank details**. Account name, bank and account number.
These appear on the client's payment page when they pay by transfer, so get them
right — and use the corporate account, never an individual one.

### Paystack

**Salanaz → Settings**, or better, put the keys in `wp-config.php` so they never
sit in the database:

```php
define( 'SALANAZ_PAYSTACK_SECRET', 'sk_test_...' );
define( 'SALANAZ_PAYSTACK_PUBLIC', 'pk_test_...' );
```

Then paste the webhook URL shown on that screen into
**Paystack → Settings → API Keys & Webhooks**.

Start with **test keys** and work through section 6 of
[TESTING.md](TESTING.md) before switching to live. Those two API calls have
never run against the real Paystack API — that is the one part of this build
that is unproven.

### Real cron

WP-Cron only fires when somebody visits the site, so on a quiet day a 07:00
reminder may go out in the afternoon, or not at all.

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```cron
*/15 * * * * curl -s https://your-site/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

**Salanaz → Automation** shows which mode is active.

---

## 5. Protect the payment proofs

Payment proofs are bank statements and transfer screenshots. The plugin stores
them above the web root when it can, and falls back inside
`wp-content/uploads/` when it cannot.

**Salanaz → Settings** shows the exact path in use.

If that path is inside the web root, the bundled `.htaccess` covers Apache and
IIS — but **nginx ignores `.htaccess` entirely**. On nginx, add:

```nginx
location ~* /wp-content/uploads/salanaz-private-.* {
    deny all;
    return 404;
}
```

Then verify: copy a stored proof path from **Salanaz → Payments → View proof**,
strip the query string, and request the file directly. It must not download.

---

## 6. Add your own content

A fresh install already contains four sample estates so you can see the site
working. Treat them as a starting point.

- **Estates → Add New** — name, description, featured image, then the address,
  title document and coordinates in the *Estate details* box.
- **Plots → Add New** — pick the estate, then plot number, size, price and
  availability. A plot with no estate will not appear in listings.

The sample content also creates test accounts that all share the password
`password`. When you are done exploring, use **Salanaz → Settings → Remove all
demo content** — it deletes only what the seeder made, keeps anything you added
yourself, and refuses to run once real payments exist.

---

## 7. Before real clients arrive

- [ ] Demo estates, plots and test accounts deleted
- [ ] Every account using the password `password` removed or changed
- [ ] 2FA on the co-founder accounts — they control payment verification
- [ ] Allocation letter wording replaced with your own contract language,
      including RC number and signatory
- [ ] Privacy policy and terms written
- [ ] SMTP confirmed working to Gmail
- [ ] Real cron confirmed running
- [ ] Proof storage confirmed unreachable by URL
- [ ] Paystack tested with test keys, then live keys with one small real payment
- [ ] Backups covering **both** the database and the private uploads directory —
      receipts and allocation letters live on disk, not in the database

---

## Updating later

**You should not need to upload anything again.** The site checks
`Cod3ghost/salanaz` for new releases and offers them under
**Dashboard → Updates**, like any other theme. See [UPDATES.md](UPDATES.md) for
how to publish one.

Uploading a ZIP by hand still works if you ever need it — WordPress will offer
to replace the existing copy.
Deactivating never touches roles, tables or uploads. Deleting the plugin also
leaves your data alone unless you explicitly set:

```bash
wp option update salanaz_delete_data_on_uninstall 1
```

---

## WP-CLI

```bash
wp salanaz status      # collected, outstanding, overdue, config health
wp salanaz pages       # create any missing required page
wp salanaz reminders   # run the reminder sweep now
wp salanaz seed        # create demo data (testing only)
```

---

## If something looks wrong

| Symptom | Likely cause |
| --- | --- |
| `/estates/` 404s | Save permalinks again |
| Sign-in or dashboard 404s | **Salanaz → Settings → Check and create pages** |
| No email arriving | SMTP not configured — the most common issue by far |
| Reminders not sending | WP-Cron on a quiet site; set up a real cron |
| "Card payment is not available" | Paystack not enabled, or a key is missing |
| Proof upload rejected | Not a JPG/PNG/WebP/PDF, or over 5 MB |
| Storage warning in wp-admin | Proofs are inside the web root; add the nginx rule |

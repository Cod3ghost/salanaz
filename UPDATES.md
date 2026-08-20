# Updating the site from GitHub

Instead of zipping folders and uploading them, you tag a release and the site
offers the update in wp-admin like any other theme.

Your repository is **`Cod3ghost/salanaz`**, and it is **public**, so the site can
read it without any credentials. The plugin already defaults to that repository —
there is nothing to configure.

---

## How it fits together

```
you push a tag  ──►  GitHub Action builds the ZIPs  ──►  attaches them to the release
                                                                    │
        wp-admin shows "Update available"  ◄──  the site checks that release
```

Three things make this work, and all three matter:

1. **The Action attaches built ZIPs.** GitHub's own "Source code (zip)" unpacks
   to a folder named after the repo and tag — `salanaz-1.0.1/`. WordPress would
   install that as a *new theme* with the wrong folder name and your site would
   lose its theme. The workflow builds archives that unpack to `salanaz/` and
   `salanaz-estate/`, which is what the updater downloads.
2. **The tag must match the version headers.** The site compares the release tag
   against the `Version:` line in `style.css` and `salanaz-estate.php`. If they
   drift, the update either never appears or is offered forever. The workflow
   fails the build rather than let that happen.
3. **The theme carries the plugin.** One release, one update, both components.

---

## One-time setup

### Push this repository

```bash
git push -u origin main
```

If GitHub asks for a password, use a **personal access token**, not your account
password — GitHub stopped accepting passwords over HTTPS in 2021.

That is the whole setup. The site already points at `Cod3ghost/salanaz` and the
repository is public, so no token and no settings are needed.

To confirm it is working, go to **Salanaz → Settings → Updates** and press
**Check for updates now**. It reports the latest released version against what
you are running.

### If you make the repository private later

Updates would stop, because the site could no longer read it. You would then
need a token:

1. GitHub → **Settings → Developer settings → Personal access tokens →
   Fine-grained tokens → Generate new token**
2. **Repository access**: *Only select repositories* → `Cod3ghost/salanaz`
3. **Permissions** → Repository permissions → **Contents: Read-only**

```php
// wp-config.php, above the "stop editing" line
define( 'SALANAZ_GITHUB_TOKEN', 'github_pat_...' );
```

Put it in `wp-config.php` rather than the settings screen so it stays out of
database backups. Note that such tokens expire — and when one does, updates stop
silently.

---

## Releasing a new version

Every release is three commands.

```bash
# 1. Bump BOTH version headers to the same number
#    wp-content/themes/salanaz/style.css              -> Version: 1.0.1
#    wp-content/plugins/salanaz-estate/salanaz-estate.php -> Version: 1.0.1
#    ...and SALANAZ_VERSION in that same plugin file

git commit -am "Release 1.0.1"
git tag v1.0.1
git push origin main --tags
```

The Action runs, checks the versions line up, lints every PHP file, builds the
two ZIPs and publishes the release.

On the site: **Dashboard → Updates**, or wait — WordPress checks periodically on
its own. The site caches its lookup for six hours; **Check for updates now**
clears it.

### Bumping the version

There are three places, and they must agree:

| File | Line |
| --- | --- |
| `wp-content/themes/salanaz/style.css` | `Version: 1.0.1` |
| `wp-content/plugins/salanaz-estate/salanaz-estate.php` | `* Version:           1.0.1` |
| `wp-content/plugins/salanaz-estate/salanaz-estate.php` | `define( 'SALANAZ_VERSION', '1.0.1' );` |

Forget one and the build fails with a clear message, which is the point.

---

## What an update does and does not touch

**Replaced:** the theme and plugin files.

**Untouched:** your estates and plots, clients and accounts, transactions,
instalment schedules, notes, receipts and allocation letters, uploaded payment
proofs, Paystack keys, bank details, and everything you set in the Customizer.

Content lives in the database and in the private uploads directory. Neither is
inside the theme or plugin folder, which is exactly why the system was split in
two.

If the schema changes between versions, `Salanaz_Schema` upgrades the tables on
the first admin page load after the update.

---

## Rolling back

Every release stays on GitHub. To go back, download the previous
`salanaz-theme.zip` from the Releases page and upload it through
**Appearance → Themes → Add New → Upload**, replacing the current copy.

Nothing is lost, for the same reason as above.

---

## If updates do not appear

| Symptom | Cause |
| --- | --- |
| Nothing offered, no error | Six-hour cache — press **Check for updates now** |
| "No release could be read" | No release published yet, or the repo name is wrong |
| Offered forever, never completes | Version headers do not match the tag |
| Update installs but the site loses its theme | A source archive was attached instead of the built asset — check the Action ran |
| Action fails on "Version headers do not match" | You tagged without bumping all three places |

To check what the site sees, from any machine:

```bash
curl https://api.github.com/repos/Cod3ghost/salanaz/releases/latest
```

A `200` with JSON means the site will see it too. A `404` means no release has
been published yet.

---

## A note on the repository being public

Public is fine for this, and it makes updates simpler — no token, nothing to
expire. But it means anyone can read the code, so one rule matters more than it
otherwise would:

**Never commit real keys.** Paystack secrets, SMTP passwords and bank details
belong in `wp-config.php` or the Settings screen on the server, never in the
repository. `.gitignore` already excludes `.env` files and the uploads directory
where payment proofs and receipts are written.

Being public also means the payment logic is readable. That is not a weakness in
itself — the security here does not depend on nobody seeing it. Paystack
webhooks are rejected unless they carry a valid HMAC signature, every action
re-checks its capability, and uploads are validated by content rather than by
filename. Those hold whether or not the source is visible.

If you would rather keep it closed, make it private and add the token described
above — everything else works the same.

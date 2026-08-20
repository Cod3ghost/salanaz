# Updating the site from GitHub

Instead of zipping folders and uploading them, you tag a release and the site
offers the update in wp-admin like any other theme.

Your repository is **`Cod3ghost/salanaz`**, and it is **private** — which means
the site needs a token to read it. That setup is below.

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

### 1. Push this repository

```bash
git push -u origin main
```

If GitHub asks for a password, use a **personal access token**, not your account
password — GitHub stopped accepting passwords over HTTPS in 2021.

### 2. Create a token for the site

Because the repo is private, the site cannot read releases anonymously.

1. GitHub → **Settings → Developer settings → Personal access tokens →
   Fine-grained tokens → Generate new token**
2. **Repository access**: *Only select repositories* → `Cod3ghost/salanaz`
3. **Permissions** → Repository permissions → **Contents: Read-only**
4. Set an expiry you will actually remember. When it lapses, updates stop
   silently — the site simply stops seeing new releases.

Nothing else. Read-only access to one repository is all it needs.

### 3. Give the token to the site

Best, because it never touches the database or a backup:

```php
// wp-config.php, above the "stop editing" line
define( 'SALANAZ_GITHUB_REPO',  'Cod3ghost/salanaz' );
define( 'SALANAZ_GITHUB_TOKEN', 'github_pat_...' );
```

Or paste it into **Salanaz → Settings → Updates**. The constant wins if both are
set.

Then press **Check for updates now**. It will tell you the latest released
version and what you are running. If the token is wrong or expired, you get a
plain error rather than silence.

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
| "No release could be read" | Token missing, expired, or lacking Contents: Read |
| Offered forever, never completes | Version headers do not match the tag |
| Update installs but the site loses its theme | A source archive was attached instead of the built asset — check the Action ran |
| Action fails on "Version headers do not match" | You tagged without bumping all three places |

To confirm the token independently:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://api.github.com/repos/Cod3ghost/salanaz/releases/latest
```

A `200` with JSON means the site will work. A `404` means the token cannot see
the repository.

---

## A note on keeping it private

A private repo is the right call here — this code contains your business logic,
and the repository history will accumulate details about how payments are
verified.

Two things follow from it:

- **Never commit real keys.** Paystack secrets and the GitHub token belong in
  `wp-config.php` on the server, not in the repository. `.gitignore` already
  excludes `.env` files and the uploads directory.
- **The site's token is a credential.** If you paste it into the Settings screen
  it is stored in the database, so it will appear in database backups. The
  `wp-config.php` route avoids that.

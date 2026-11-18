# Admin panel — setup & deployment

A password-protected page at `/admin/` that lets the client edit every text
block (in all 4 languages), replace the photo used in each section, and
add/remove/reorder photos in the gallery — without touching code.

## How it works

- All editable content lives in `content.json` (project root). The public
  site (`index.html`) fetches it at runtime and overlays it on top of its
  built-in defaults, so the site still works even if `content.json` is
  missing or the admin backend isn't deployed.
- The admin panel (PHP) reads/writes that same `content.json`, and saves
  uploaded photos into `Ruban/web/uploads/`.
- Auth is a single shared password (bcrypt hash in `admin/config.php`,
  **not** committed to git) protecting a PHP session cookie scoped to `/admin/`.

Requires PHP 7.4 or newer (any current Hostinger shared hosting plan has this;
selectable under hPanel → Advanced → PHP Configuration if you need to change it).

## One-time setup (before first deploy)

1. Generate a password hash and a session secret. If you have SSH/terminal
   access to the server, or any computer with PHP installed, run:

   ```
   php -r "echo password_hash('choose-a-strong-password', PASSWORD_BCRYPT), PHP_EOL;"
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

   No PHP available anywhere? Ask whoever deployed this to generate the two
   values for you — they're just two short strings.

2. Copy `admin/config.example.php` to `admin/config.php` and paste in the
   two values:

   ```php
   <?php
   return [
       'password_hash' => '<paste the password_hash() output>',
       'session_secret' => '<paste the random hex string>',
   ];
   ```

3. **Do not commit `admin/config.php`** — it's already in `.gitignore`.
   Upload it to the server directly (FTP/cPanel File Manager), separately
   from the rest of the repo.

## Deploying to Hostinger (shared hosting)

1. Upload the whole project to your hosting root (`public_html/` or
   equivalent) via FTP or the cPanel File Manager — including the `admin/`
   folder, `content.json`, and the `admin/config.php` you created above.
2. Make sure these are **writable** by PHP (on Hostinger this is normally
   the default for files/folders you upload — no action needed unless your
   host shows permission errors):
   - `content.json`
   - `Ruban/web/uploads/` (create this empty folder if it doesn't exist yet)
3. Visit `https://yourdomain/admin/` and log in with the password you chose
   in step 1 above.
4. You can change the password any time from inside the admin panel
   ("Passwort ändern") — no redeploy needed.

## Using the panel

- **Language tabs** (top left) switch which language's text you're editing.
  Images and the gallery are shared across all languages.
- **Bilder** — every photo used in a page section; "Foto ersetzen" swaps it.
- **Galerie** — the scrolling photo gallery on the site. Add photos, delete
  them, tag them (Tag/Abend/Nacht/Gäste — controls the filter buttons on the
  site), and edit their description.
- Nothing is public until you click **Speichern** (Save) — it writes
  everything at once (all languages, images, gallery) to `content.json`.

## Known limitations (by design, to keep this maintainable)

- The two looping background **videos** (hero + reviews section) are not
  editable here — only their poster/thumbnail image is. Swapping the actual
  video requires a developer (it needs re-encoding to keep the site fast).
- Gallery photo descriptions are a single field, not translated per
  language — same behavior the site already had before this admin panel.
- Uploaded photos aren't automatically compressed/resized. For best
  performance, upload images that are already reasonably sized (roughly
  1200–1800px on the long edge) rather than raw multi-megabyte camera
  originals.

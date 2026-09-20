# Deploying on Spaceship / cPanel shared hosting

Everything here is plain PHP and MySQL — no SSH, Composer or Node needed.
These steps use `chatbot.dawood.top`, whose document root is the folder
`chatbot.dawood.top` in your cPanel home directory.

## 1. Upload the files

1. cPanel → **File Manager** → open the `chatbot.dawood.top` folder (it is the
   subdomain's document root, so the app's `index.php` belongs directly inside it,
   not in a sub-folder).
2. **Upload** the ZIP of this repository, then select it and press **Extract**.
3. Delete the ZIP afterwards. The folder should now contain `index.php`,
   `install.php`, `embed.php`, `.htaccess`, `api/`, `app/`, `assets/`, `storage/`.

If the archive extracts into a nested folder (e.g. `chatbot-main/`), open it, select
everything and **Move** it one level up so `index.php` sits in the document root.

Permissions: folders `755`, files `644`. The installer writes `app/config.php`, so
`app/` must be writable — `755` is enough when PHP runs as your cPanel user, which is
the norm on cPanel.

## 2. Create the database

cPanel → **MySQL® Databases**:

1. Create a database, e.g. `cpaneluser_chatbot`.
2. Create a user with a strong password.
3. **Add the user to the database** with *ALL PRIVILEGES*.

Note the full names — cPanel prefixes both with your account name.

## 3. Run the installer

Open `https://chatbot.dawood.top/install.php`.

- The page first checks PHP version, `pdo_mysql`, `mbstring`, outbound HTTPS and
  folder permissions.
- Fill in the database details, confirm the public URL (it must be the address your
  clients can reach — it is baked into the embed code), and set the admin account.
- Optionally paste your OpenAI API key; you can also add it later under **Settings**.

When it finishes:

1. **Delete `install.php` from the server.**
2. Sign in at `https://chatbot.dawood.top/`.
3. Change the password when prompted (the panel stays locked until you do).

## 4. Add your OpenAI key

**Settings → OpenAI**: paste the key from `platform.openai.com → API keys`, save, then
press **Test OpenAI connection**. A green message means the key, the network and the
model all work.

If your host blocks outbound connections (rare on Spaceship, common on very locked-down
plans), the test reports a connection error — ask support to allow outbound HTTPS to
`api.openai.com`.

Prefer to keep the key out of the database? Set it in cPanel → **MultiPHP INI Editor**
or a `.user.ini`/`.htaccess` environment variable named `OPENAI_API_KEY`; it takes
precedence over the stored one.

## 5. Add a website and its knowledge

1. **Websites → Add a website.** Give it a name and the domains it may run on
   (`acme.com` covers `www.acme.com` and other subdomains). Leave domains empty only
   while testing — an empty list lets any site embed that bot on your credits.
2. **Knowledge base.** Paste your FAQ, services, pricing and policies as separate
   documents, import key pages by URL, or upload TXT/MD/CSV/HTML/JSON files. Each save
   indexes the document immediately.
3. Use **Test retrieval** with a few real customer questions. If nothing matches,
   add more detail or lower the minimum match score under **AI & answers**.
4. **Design** tab: colours, wording, position, suggested questions. **Live preview**
   opens a sample page with the real widget.

## 6. Install the widget on the client site

**Embed code** tab → copy the snippet:

```html
<script src="https://chatbot.dawood.top/embed.php?k=cb_xxxxxxxxxxxx" async></script>
```

Paste it before `</head>` (or `</body>`) on every page of the client's site.

- **WordPress:** Appearance → Theme File Editor → `header.php`, or any "insert headers
  and footers" plugin.
- **Shopify:** Online Store → Themes → Edit code → `theme.liquid`, before `</head>`.
- **Wix / Squarespace:** the custom-code / header-injection panel.
- **Plain HTML:** in the shared header include.

Design and knowledge changes take effect without touching the client site again; the
embed script is cached for 5 minutes.

## Automatic deployment from GitHub

Rather than re-uploading files by hand after every change, let GitHub push them for
you. The credentials live in GitHub's encrypted secrets — nobody needs to hand the
cPanel password to anyone, and access can be revoked in one click.

### Option A — FTPS from GitHub Actions (works on every cPanel plan)

`.github/workflows/deploy.yml` is already in this repository. It lints the code, then
uploads it over FTPS on every push to `main`.

1. cPanel → **FTP Accounts** → create an account **scoped to the app folder**, e.g.
   directory `chatbot.dawood.top`. Do not use your main cPanel login.
2. GitHub → repository → **Settings → Secrets and variables → Actions → Secrets**,
   add:
   - `FTP_SERVER` — `chatbot.dawood.top` (or the server hostname cPanel shows under
     FTP Accounts → Configure FTP Client, e.g. `server44.shared.spaceship.host`)
   - `FTP_USERNAME` — the full FTP user, e.g. `deploy@chatbot.dawood.top`
   - `FTP_PASSWORD` — that account's password
3. On the **Variables** tab (same page), optionally add:
   - `FTP_SERVER_DIR` — target folder, default `chatbot.dawood.top/`. It is relative
     to wherever the FTP account lands, so an account scoped to that folder needs
     `./` instead.
   - `FTP_DRY_RUN` — set to `true` for the first run: the log lists what *would* be
     uploaded without touching the server. Delete it afterwards.
4. Push to `main` (or run the workflow manually from the **Actions** tab).

The workflow never uploads `app/config.php`, `storage/logs/`, `install.php` or the
docs, so your live credentials, logs and the deleted installer stay as they are.

### Option B — cPanel's own Git Version Control

If your plan has **Git™ Version Control** (and SSH for the initial clone):

1. `.cpanel.yml` in this repository already targets `$HOME/chatbot.dawood.top`.
2. cPanel → **Git™ Version Control** → **Create** → clone this repository.
3. After each push, open that screen and press **Update from Remote** → **Deploy HEAD
   Commit**. The tasks in `.cpanel.yml` copy the files into place.

### Database changes

You never need to re-run the installer after an update. The app carries a schema
version and runs any new migrations itself on the first admin page load after a
deployment.

### Rolling back

`git revert` the bad commit and push — the same pipeline redeploys the previous state.

## Maintenance

- **Conversations** stores every transcript; delete individual chats there.
- **Settings → Maintenance** clears old rate-limit rows (optional housekeeping).
- **Regenerate key** (Embed tab) immediately disables an embed code that leaked —
  remember to paste the new snippet on the client site.
- Back up by exporting the database in phpMyAdmin; the files are static.

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| Widget does not appear | Wrong site key, site is **paused**, or the page's domain is not in the allow-list. Open the browser console and load the `embed.php` URL directly — it says which. |
| Bot always replies with the fallback message | No OpenAI key, no matching knowledge, or the key has no credits. Check **Settings → Test connection** and **Test retrieval**. |
| "This chatbot is not enabled for this domain" | Add the domain under the website's **General** tab. |
| Documents stuck on "keyword only" | The embedding call failed (usually a missing key or blocked outbound HTTPS). Fix it, then **Re-index all**. |
| Links or the embed code point at the wrong address | `base_url` in `app/config.php` is stale — edit that one line, or delete the file and re-run the installer. |
| Subdomain shows a cPanel default page | The files landed in a sub-folder; `index.php` must sit directly in `chatbot.dawood.top/`. |
| Visitors hit "message limit" too soon | Raise *Messages per visitor per hour* under **AI & answers**. |
| "This chatbot is not enabled for this domain" on your own preview page | Fixed — the panel's own host is always allowed. Make sure the files are up to date. |
| Voice button missing | The browser blocks `MediaRecorder` on plain HTTP; the site must be HTTPS. It is also hidden when *Let visitors record voice messages* is off. |
| Voice note is not transcribed | Transcription needs the cURL extension and a working OpenAI key. |
| Attachment fails with 413 | Raise `upload_max_filesize` and `post_max_size` in cPanel → MultiPHP INI Editor. |

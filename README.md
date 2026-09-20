# Chatbot platform

A self-hosted, multi-website AI chat widget for **Spaceship / cPanel shared hosting**.
Plain PHP 8 + MySQL, no Composer, no build step, no background workers — upload the
files, run the installer, paste one `<script>` tag into a client's website.

```
Admin panel  →  add website  →  add knowledge  →  copy embed code  →  client site
                                                                      ↓
                                     visitor question → OpenAI embeddings (retrieval)
                                                      → OpenAI chat completion (answer)
```

## What you get

**Backend (login protected)**
- Dashboard with conversation, message and document counts.
- **Websites** — one entry per client site, each with its own key, knowledge base,
  design and allowed domains.
- **Knowledge base** — paste text, import a web page, or upload TXT/MD/CSV/HTML/JSON.
  Documents are chunked, embedded with `text-embedding-3-small` and searched by
  cosine similarity at answer time. A "test retrieval" box shows exactly which
  passages a question pulls in.
- **Design manager** — header wording, welcome message, suggested questions, six
  colours, light/dark theme, font, launcher icon/label, position and offsets, corner
  radius, avatar, auto-open, branding line. No code on the client side.
- **AI settings** — model, creativity, reply length, persona/system prompt, fallback
  message, strict-knowledge mode, passages per answer, minimum match score,
  remembered turns, per-visitor hourly message cap.
- **Conversations** — full transcripts of every visitor chat, filterable per website.
- **Settings** — OpenAI key (or `OPENAI_API_KEY` env var), API base URL, defaults,
  connection test, platform name.

**Widget (`embed.php`)**
- One async `<script>` tag, rendered in a shadow DOM so the client's CSS can never
  break it and it can never break theirs.
- Launcher bubble, chat panel, typing indicator, suggestion chips, message history in
  `localStorage`, mobile full-screen layout, keyboard support, `Escape` to close.
- JS API: `Chatbot.open()`, `.close()`, `.send('…')`, `.reset()`.

**Security**
- Password hashing with bcrypt, CSRF tokens on every form, login throttling.
- Per-site domain allow-list enforced as CORS + a server-side origin check.
- Per-visitor hourly rate limit, visitor IPs stored only as a salted hash.
- SSRF guard on URL imports, `app/` and `storage/` blocked by `.htaccess`.

## Install

See **[DEPLOYMENT.md](DEPLOYMENT.md)** for the step-by-step cPanel walkthrough.
Short version: upload the files, create a MySQL database in cPanel, open
`https://yourdomain.com/chatbot/install.php`, fill in the form, delete `install.php`.

## Layout

```
index.php            admin front controller (?r=route)
install.php          one-page installer - delete after use
embed.php            serves the widget + that site's config
api/chat.php         public chat endpoint (CORS limited to allowed domains)
assets/widget.js     the widget itself
assets/admin.css     admin styling
app/
  bootstrap.php      autoloader + config loading
  config.php         written by the installer (not in git)
  helpers.php        small view/request helpers
  controllers/       one file per route
  views/             layouts + admin pages
  lib/               Auth, ChatService, Chunker, Database, KnowledgeBase, OpenAi,
                     RateLimiter, Schema, Scraper, Settings, Site, Vector, View…
storage/logs/        PHP error log
```

## Costs

Every answer costs one embedding call for the question plus one chat completion;
indexing costs one embedding call per ~1200 characters. With `gpt-4o-mini` and
`text-embedding-3-small` a typical support answer is a fraction of a cent, billed to
the OpenAI credits on your own account.

## Local development

```bash
php -S 127.0.0.1:8080 -t .
```

Then open `http://127.0.0.1:8080/install.php` and point it at any MySQL/MariaDB
database.

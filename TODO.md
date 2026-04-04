# TODO.md

---

### - [x] 5. [[docs/toc.md]] Auto-generate table of contents in docs.php from H1 headings

After a file is rendered in `docs.php`, scan the content for H1 headings (lines starting with `#` in the raw markdown, or `<h1>` elements in the rendered HTML) and inject a small TOC block at the top of the content area.

**Implementation notes:**

- Run after `renderContent()` populates `#content-inner`.
- Query all `h1` elements inside `#content-inner` and add an `id` attribute to each (slugified from the heading text) if not already present.
- Build a TOC block: a `<nav id="toc">` with one `<a>` per H1, linking to `#slug`.
- Inject it as the first child of `#content-inner`.
- Style it in small font (`font-size: 0.82em`), using `var(--text-secondary)` and `var(--accent)` for links, with a bottom border to separate it from the content.
- Hide the TOC if fewer than 2 H1s are found (not worth showing for a single heading).
- Only applies to `.md` and `.txt` files — skip for `.mermaid` and `.csv`.

---

### - [x] 2. [[docs/themes.md]] Create multiple theme-*.css on root folders

---

### - [x] 4. [[docs/multi-host.md]] Support multiple Ollama hosts with automatic fallback

Replace the single `API_URL` string constant with an `OLLAMA_HOSTS` array of base URLs in `config.php`:

```php
define('OLLAMA_HOSTS', [
    'http://localhost:11434',
    'http://192.168.1.10:11434',
]);
```

**Implementation notes:**

- Drop `shell_exec('ollama list')` in `ollama.php` — it only works locally. Replace `loadModels()` with a REST call to `GET /api/tags` on the active host, which works both locally and remotely.
- Add `findWorkingHost()` in the `Ollama` class: iterate `OLLAMA_HOSTS`, call `GET {host}/api/tags`, return and cache the first host that responds as `$this->activeHost`.
- Derive all endpoint URLs dynamically: `$this->activeHost . '/api/generate'` and `$this->activeHost . '/api/tags'`.
- On `generateResponse()` curl failure, remove the failed host from the list and retry with the next one.
- Remove the old `API_URL` constant from `config.php`.

---

### - [x] 3. [[docs/offline-assets.md]] Download all external JS and CSS files to the project root for offline use

Currently loaded from CDN in `index.php`:
- `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css`
- `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js`
- `https://cdn.jsdelivr.net/npm/marked/marked.min.js`

Download them directly into the project root (`./`) and update the `<link>` and `<script>` tags in `index.php` to use local paths.

---

### - [x] 1. [[docs/config.php.md]] Create a constant on the config.php to force the chatbox only to one model

---

